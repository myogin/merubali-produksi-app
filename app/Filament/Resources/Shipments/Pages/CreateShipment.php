<?php

namespace App\Filament\Resources\Shipments\Pages;

use App\Filament\Resources\Shipments\ShipmentResource;
use App\Models\ProductionBatch;
use App\Models\StockMovement;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateShipment extends CreateRecord
{
    protected static string $resource = ShipmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Validate stock availability for all items before creating the record
        $this->validateStockAvailability($data);

        // Create the shipment record
        $record = static::getModel()::create([
            'shipment_number' => $data['shipment_number'],
            'shipment_date' => $data['shipment_date'],
            'destination' => $data['destination'],
            'delivery_note_number' => $data['delivery_note_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        // Create shipment items
        foreach ($data['shipmentItems'] as $itemData) {
            $record->shipmentItems()->create($itemData);
        }

        // Generate stock movements
        $this->generateStockMovements($record);

        return $record;
    }

    protected function validateStockAvailability(array $data): void
    {
        $insufficientItems = [];

        foreach ($data['shipmentItems'] as $itemData) {
            $batch = ProductionBatch::find($itemData['production_batch_id']);
            if (!$batch) {
                continue;
            }

            $remainingStock = $batch->getRemainingStock();
            $qtyToShip = $itemData['qty_shipped'];

            if ($qtyToShip > $remainingStock) {
                $insufficientItems[] = [
                    'batch' => $batch->batch_code,
                    'product' => $batch->product->name,
                    'requested' => $qtyToShip,
                    'available' => $remainingStock,
                    'shortage' => $qtyToShip - $remainingStock,
                ];
            }
        }

        if (!empty($insufficientItems)) {
            $message = 'Insufficient stock for the following batches:' . PHP_EOL;
            foreach ($insufficientItems as $item) {
                $message .= "• {$item['batch']} ({$item['product']}): Need {$item['requested']}, Available {$item['available']} (Short: {$item['shortage']})" . PHP_EOL;
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
        foreach ($record->shipmentItems as $item) {
            // Create outbound movement for finished goods
            StockMovement::create([
                'movement_date' => $record->shipment_date,
                'item_type' => 'finished_goods',
                'item_id' => $item->productionBatch->product_id,
                'batch_id' => $item->production_batch_id,
                'qty' => $item->qty_shipped,
                'uom' => $item->uom,
                'movement_type' => 'out',
                'reference_type' => 'shipment',
                'reference_id' => $record->id,
                'notes' => "Shipment {$record->shipment_number} - Batch {$item->productionBatch->batch_code}",
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Shipment created successfully';
    }
}
