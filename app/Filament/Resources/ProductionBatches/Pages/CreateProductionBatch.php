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
        // Validate stock availability before creating the record
        $this->validateStockAvailability($data);

        // Create the production batch record
        $record = static::getModel()::create($data);

        // Generate stock movements
        $this->generateStockMovements($record);

        return $record;
    }

    protected function validateStockAvailability(array $data): void
    {
        $productId = $data['product_id'];
        $qtyToProduce = $data['qty_produced'];

        $boms = Bom::where('product_id', $productId)->with('packagingItem')->get();

        if ($boms->isEmpty()) {
            Notification::make()
                ->title('BOM Not Found')
                ->body('No Bill of Materials found for the selected product.')
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
                    'item' => $bom->packagingItem->name,
                    'required' => $requiredQty,
                    'available' => $currentStock,
                    'shortage' => $requiredQty - $currentStock,
                ];
            }
        }

        if (!empty($insufficientItems)) {
            $message = 'Insufficient stock for the following items:' . PHP_EOL;
            foreach ($insufficientItems as $item) {
                $message .= "• {$item['item']}: Need {$item['required']}, Available {$item['available']} (Short: {$item['shortage']})" . PHP_EOL;
            }

            Notification::make()
                ->title('Insufficient Stock')
                ->body($message)
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function generateStockMovements(Model $record): void
    {
        $boms = Bom::where('product_id', $record->product_id)->get();

        // Create outbound movements for packaging materials
        foreach ($boms as $bom) {
            $requiredQty = $bom->qty_per_unit * $record->qty_produced;

            StockMovement::create([
                'movement_date' => $record->production_date,
                'item_type' => 'packaging',
                'item_id' => $bom->packaging_item_id,
                'batch_id' => null,
                'qty' => $requiredQty,
                'uom' => $bom->uom,
                'movement_type' => 'out',
                'reference_type' => 'production',
                'reference_id' => $record->id,
                'notes' => "Production consumption for batch {$record->batch_code}",
            ]);
        }

        // Create inbound movement for finished goods
        StockMovement::create([
            'movement_date' => $record->production_date,
            'item_type' => 'finished_goods',
            'item_id' => $record->product_id,
            'batch_id' => $record->id,
            'qty' => $record->qty_produced,
            'uom' => $record->uom,
            'movement_type' => 'in',
            'reference_type' => 'production',
            'reference_id' => $record->id,
            'notes' => "Production output for batch {$record->batch_code}",
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
