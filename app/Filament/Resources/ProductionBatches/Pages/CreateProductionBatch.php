<?php

namespace App\Filament\Resources\ProductionBatches\Pages;

use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Models\Bom;
use App\Models\StockMovement;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateProductionBatch extends CreateRecord
{
    protected static string $resource = ProductionBatchResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        Log::info("=== PRODUCTION BATCH CREATION STARTED ===");
        Log::info("Received data: " . json_encode($data, JSON_PRETTY_PRINT));

        // Extract production batch items from data before validation
        $productionBatchItems = $data['productionBatchItems'] ?? [];

        // Validate stock availability for all production batch items
        $this->validateStockAvailability($data);

        // Remove productionBatchItems from data to avoid mass assignment issues
        unset($data['productionBatchItems']);

        // Wrap everything in a database transaction
        return DB::transaction(function () use ($data, $productionBatchItems) {
            // Create the production batch record (header)
            Log::info("Creating production batch with data: " . json_encode($data, JSON_PRETTY_PRINT));
            $record = static::getModel()::create($data);
            Log::info("Created production batch ID: {$record->id}");

            // Create production batch items and generate stock movements
            if (!empty($productionBatchItems)) {
                Log::info("Found " . count($productionBatchItems) . " production batch items to process");

                foreach ($productionBatchItems as $index => $itemData) {
                    Log::info("Processing item {$index}: " . json_encode($itemData, JSON_PRETTY_PRINT));

                    $productionBatchItem = $record->productionBatchItems()->create([
                        'batch_code' => $itemData['batch_code'],
                        'product_id' => $itemData['product_id'],
                        'qty_produced' => $itemData['qty_produced'],
                        'uom' => $itemData['uom'] ?? 'cartons',
                        'notes' => $itemData['notes'] ?? null,
                    ]);

                    Log::info("Created production batch item ID: {$productionBatchItem->id}");

                    // Generate stock movements for this item
                    Log::info("About to generate stock movements for item {$productionBatchItem->id}");
                    $this->generateStockMovements($record, $productionBatchItem);
                    Log::info("Completed stock movements for item {$productionBatchItem->id}");
                }
            } else {
                Log::warning("No productionBatchItems found in data!");
            }

            Log::info("=== PRODUCTION BATCH CREATION COMPLETED ===");
            return $record;
        });
    }

    protected function afterCreate(): void
    {
        Log::info("=== AFTER CREATE HOOK TRIGGERED ===");

        // Get the created record
        $record = $this->getRecord();
        Log::info("Record ID: {$record->id}");

        // Check if production batch items were created by Filament's relationship handling
        $productionBatchItems = $record->productionBatchItems()->get();
        Log::info("Found " . $productionBatchItems->count() . " production batch items created by relationship");

        if ($productionBatchItems->count() > 0) {
            Log::info("Processing production batch items created by Filament relationship handling");

            DB::transaction(function () use ($record, $productionBatchItems) {
                foreach ($productionBatchItems as $productionBatchItem) {
                    Log::info("Processing batch item: {$productionBatchItem->batch_code} (ID: {$productionBatchItem->id})");

                    // Check if stock movements already exist for this item
                    $existingMovements = StockMovement::where('reference_type', 'production')
                        ->where('reference_id', $record->id)
                        ->where('notes', 'like', "%{$productionBatchItem->batch_code}%")
                        ->count();

                    if ($existingMovements > 0) {
                        Log::info("Stock movements already exist for batch {$productionBatchItem->batch_code}, skipping");
                        continue;
                    }

                    // Generate stock movements for this item
                    Log::info("Generating stock movements for batch item: {$productionBatchItem->batch_code}");
                    $this->generateStockMovements($record, $productionBatchItem);
                    Log::info("Completed stock movements for batch item: {$productionBatchItem->batch_code}");
                }
            });
        }

        Log::info("=== AFTER CREATE HOOK COMPLETED ===");
    }

    protected function validateStockAvailability(array $data): void
    {
        if (!isset($data['productionBatchItems'])) {
            return;
        }

        $allInsufficientItems = [];

        foreach ($data['productionBatchItems'] as $index => $itemData) {
            $productId = $itemData['product_id'];
            $qtyToProduce = $itemData['qty_produced'];
            $batchCode = $itemData['batch_code'] ?? "Item " . ($index + 1);

            $boms = Bom::where('product_id', $productId)->with('packagingItem')->get();

            if ($boms->isEmpty()) {
                Notification::make()
                    ->title('BOM Not Found')
                    ->body("No Bill of Materials found for the product in {$batchCode}.")
                    ->danger()
                    ->send();

                $this->halt();
            }

            $insufficientItems = [];

            foreach ($boms as $bom) {
                $requiredQty = $bom->qty_per_unit * $qtyToProduce;

                // Get current stock
                $currentStock = StockMovement::where('item_type', 'packaging')
                    ->where('item_id', $bom->packaging_item_id)
                    ->selectRaw('SUM(CASE WHEN movement_type = "in" THEN qty ELSE -qty END) as current_stock')
                    ->value('current_stock') ?? 0;

                if ($currentStock < $requiredQty) {
                    $insufficientItems[] = [
                        'batch_code' => $batchCode,
                        'item' => $bom->packagingItem->name,
                        'required' => $requiredQty,
                        'available' => $currentStock,
                        'shortage' => $requiredQty - $currentStock,
                    ];
                }
            }

            $allInsufficientItems = array_merge($allInsufficientItems, $insufficientItems);
        }

        if (!empty($allInsufficientItems)) {
            $message = 'Insufficient stock for the following items:' . PHP_EOL;
            foreach ($allInsufficientItems as $item) {
                $message .= "• {$item['batch_code']} - {$item['item']}: Need {$item['required']}, Available {$item['available']} (Short: {$item['shortage']})" . PHP_EOL;
            }

            Notification::make()
                ->title('Insufficient Stock')
                ->body($message)
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function generateStockMovements(Model $productionBatch, Model $productionBatchItem): void
    {
        try {
            Log::info("Starting stock movement generation for batch item: {$productionBatchItem->batch_code}");

            $boms = Bom::where('product_id', $productionBatchItem->product_id)->with('packagingItem')->get();
            Log::info("Found " . $boms->count() . " BOM entries for product ID: {$productionBatchItem->product_id}");

            if ($boms->isEmpty()) {
                throw new \Exception("No BOM entries found for product ID: {$productionBatchItem->product_id}");
            }

            $createdMovements = [];

            // Create outbound movements for packaging materials
            foreach ($boms as $bom) {
                $requiredQty = $bom->qty_per_unit * $productionBatchItem->qty_produced;
                Log::info("Creating outbound movement for packaging item {$bom->packaging_item_id} ({$bom->packagingItem->name}), qty: {$requiredQty}");

                $outboundMovement = StockMovement::create([
                    'movement_date' => $productionBatch->production_date,
                    'item_type' => 'packaging',
                    'item_id' => $bom->packaging_item_id,
                    'batch_id' => null,
                    'qty' => $requiredQty,
                    'uom' => $bom->uom ?? $bom->packagingItem->base_uom,
                    'movement_type' => 'out',
                    'reference_type' => 'production',
                    'reference_id' => $productionBatch->id,
                    'notes' => "Production consumption for batch {$productionBatchItem->batch_code}",
                ]);

                if (!$outboundMovement || !$outboundMovement->id) {
                    throw new \Exception("Failed to create outbound movement for packaging item {$bom->packaging_item_id}");
                }

                $createdMovements[] = $outboundMovement;
                Log::info("Created outbound movement ID: {$outboundMovement->id}");

                // Verify the movement was saved correctly
                $this->verifyStockMovement($outboundMovement, 'packaging', 'out', $requiredQty);
            }

            // Create inbound movement for finished goods
            Log::info("Creating inbound movement for product {$productionBatchItem->product_id}, qty: {$productionBatchItem->qty_produced}");

            $inboundMovement = StockMovement::create([
                'movement_date' => $productionBatch->production_date,
                'item_type' => 'finished_goods',
                'item_id' => $productionBatchItem->product_id,
                'batch_id' => $productionBatchItem->id, // Reference the production batch item
                'qty' => $productionBatchItem->qty_produced,
                'uom' => $productionBatchItem->uom ?? 'cartons',
                'movement_type' => 'in',
                'reference_type' => 'production',
                'reference_id' => $productionBatch->id,
                'notes' => "Production output for batch {$productionBatchItem->batch_code}",
            ]);

            if (!$inboundMovement || !$inboundMovement->id) {
                throw new \Exception("Failed to create inbound movement for product {$productionBatchItem->product_id}");
            }

            $createdMovements[] = $inboundMovement;
            Log::info("Created inbound movement ID: {$inboundMovement->id}");

            // Verify the movement was saved correctly
            $this->verifyStockMovement($inboundMovement, 'finished_goods', 'in', $productionBatchItem->qty_produced);

            Log::info("Stock movement generation completed for batch item: {$productionBatchItem->batch_code}. Created " . count($createdMovements) . " movements.");
        } catch (\Exception $e) {
            Log::error("Error generating stock movements for batch {$productionBatchItem->batch_code}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            // Show user-friendly error notification
            Notification::make()
                ->title('Stock Movement Error')
                ->body("Failed to create stock movements for batch {$productionBatchItem->batch_code}: " . $e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function verifyStockMovement(StockMovement $movement, string $expectedItemType, string $expectedMovementType, int $expectedQty): void
    {
        // Refresh the model to ensure we have the latest data
        $movement->refresh();

        if ($movement->item_type !== $expectedItemType) {
            throw new \Exception("Stock movement item_type mismatch. Expected: {$expectedItemType}, Got: {$movement->item_type}");
        }

        if ($movement->movement_type !== $expectedMovementType) {
            throw new \Exception("Stock movement movement_type mismatch. Expected: {$expectedMovementType}, Got: {$movement->movement_type}");
        }

        if ((int)$movement->qty !== $expectedQty) {
            throw new \Exception("Stock movement qty mismatch. Expected: {$expectedQty}, Got: {$movement->qty}");
        }

        Log::info("Stock movement verification passed for ID: {$movement->id}");
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Production batch created successfully';
    }
}
