<?php

namespace App\Filament\Resources\Shipments\Pages;

use App\Filament\Resources\Shipments\ShipmentResource;
use App\Models\ProductionBatchItem;
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

        // Note: Shipment items are handled automatically by Filament's relationship repeater
        // The items will be created after this method returns

        return $record;
    }

    protected function afterCreate(): void
    {
        // Generate stock movements after the record and its relationships are created
        $this->generateStockMovements($this->record);
    }

    protected function validateStockAvailability(array $data): void
    {
        // Get shipment items from the form data
        $shipmentItems = $data['shipmentItems'] ?? [];

        if (empty($shipmentItems)) {
            return;
        }

        $insufficientItems = [];

        foreach ($shipmentItems as $itemData) {
            $batchItem = ProductionBatchItem::find($itemData['production_batch_item_id']);
            if (!$batchItem) {
                continue;
            }

            $remainingStock = $batchItem->getRemainingStock();
            $qtyToShip = $itemData['qty_shipped'];

            if ($qtyToShip > $remainingStock) {
                $insufficientItems[] = [
                    'batch' => $batchItem->batch_code,
                    'product' => $batchItem->product->name,
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
                'item_id' => $item->productionBatchItem->product_id,
                'batch_id' => $item->production_batch_item_id,
                'qty' => $item->qty_shipped,
                'uom' => $item->uom ?? 'cartons', // Default to 'cartons' if uom is null
                'movement_type' => 'out',
                'reference_type' => 'shipment',
                'reference_id' => $record->id,
                'notes' => "Shipment {$record->shipment_number} - Batch {$item->productionBatchItem->batch_code}",
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
