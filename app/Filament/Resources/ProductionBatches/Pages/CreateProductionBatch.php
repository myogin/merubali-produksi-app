<?php

namespace App\Filament\Resources\ProductionBatches\Pages;

use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Models\Bom;
use App\Models\StockMovement;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProductionBatch extends CreateRecord
{
    protected static string $resource = ProductionBatchResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Validate stock availability for all production batch items
        $this->validateStockAvailability($data);

        // Create the production batch record (header)
        $productionBatchData = [
            'production_date' => $data['production_date'],
            'po_number' => $data['po_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        $record = static::getModel()::create($productionBatchData);

        // Create production batch items and generate stock movements
        if (isset($data['productionBatchItems'])) {
            foreach ($data['productionBatchItems'] as $itemData) {
                $productionBatchItem = $record->productionBatchItems()->create([
                    'batch_code' => $itemData['batch_code'],
                    'product_id' => $itemData['product_id'],
                    'qty_produced' => $itemData['qty_produced'],
                    'uom' => $itemData['uom'] ?? 'cartons',
                    'notes' => $itemData['notes'] ?? null,
                ]);

                // Generate stock movements for this item
                $this->generateStockMovements($record, $productionBatchItem);
            }
        }

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
        $boms = Bom::where('product_id', $productionBatchItem->product_id)->get();

        // Create outbound movements for packaging materials
        foreach ($boms as $bom) {
            $requiredQty = $bom->qty_per_unit * $productionBatchItem->qty_produced;

            StockMovement::create([
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
        }

        // Create inbound movement for finished goods
        StockMovement::create([
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
