<?php

namespace App\Filament\Resources\ProductionBatches\Pages;

use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Models\Bom;
use App\Models\StockMovement;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
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

            $boms = Bom::where('product_id', $productionBatchItem->product_id)->get();
            Log::info("Found " . $boms->count() . " BOM entries for product ID: {$productionBatchItem->product_id}");

            // Create outbound movements for packaging materials
            foreach ($boms as $bom) {
                $requiredQty = $bom->qty_per_unit * $productionBatchItem->qty_produced;
                Log::info("Creating outbound movement for packaging item {$bom->packaging_item_id}, qty: {$requiredQty}");

                $outboundMovement = StockMovement::create([
                    'movement_date' => $productionBatch->production_date,
                    'item_type' => 'packaging',
                    'item_id' => $bom->packaging_item_id,
                    'batch_id' => null,
                    'qty' => $requiredQty,
                    'uom' => $bom->uom,
                    'movement_type' => 'out',
                    'reference_type' => 'production',
                    'reference_id' => $productionBatch->id,
                    'notes' => "Production consumption for batch {$productionBatchItem->batch_code}",
                ]);
                Log::info("Created outbound movement ID: {$outboundMovement->id}");
            }

            // Create inbound movement for finished goods
            Log::info("Creating inbound movement for product {$productionBatchItem->product_id}, qty: {$productionBatchItem->qty_produced}");

            $inboundMovement = StockMovement::create([
                'movement_date' => $productionBatch->production_date,
                'item_type' => 'finished_goods',
                'item_id' => $productionBatchItem->product_id,
                'batch_id' => $productionBatchItem->id, // Reference the production batch item
                'qty' => $productionBatchItem->qty_produced,
                'uom' => 'cartons',
                'movement_type' => 'in',
                'reference_type' => 'production',
                'reference_id' => $productionBatch->id,
                'notes' => "Production output for batch {$productionBatchItem->batch_code}",
            ]);
            Log::info("Created inbound movement ID: {$inboundMovement->id}");

            Log::info("Stock movement generation completed for batch item: {$productionBatchItem->batch_code}");
        } catch (\Exception $e) {
            Log::error("Error generating stock movements for batch {$productionBatchItem->batch_code}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
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
