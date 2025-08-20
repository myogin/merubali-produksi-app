<?php

namespace App\Filament\Resources\Receipts\Pages;

use App\Filament\Resources\Receipts\ReceiptResource;
use App\Models\StockMovement;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReceipt extends CreateRecord
{
    protected static string $resource = ReceiptResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Create the receipt record first
        $record = static::getModel()::create([
            'receipt_number' => $data['receipt_number'],
            'receipt_date' => $data['receipt_date'],
            'supplier_name' => $data['supplier_name'],
            'delivery_note_url' => $data['delivery_note_url'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $record;
    }

    protected function afterCreate(): void
    {
        // Generate stock movements after the record and its relationships are created
        $this->generateStockMovements($this->record);
    }

    protected function generateStockMovements(Model $record): void
    {
        foreach ($record->receiptItems as $item) {
            StockMovement::create([
                'movement_date' => $record->receipt_date,
                'item_type' => 'packaging',
                'item_id' => $item->packaging_item_id,
                'batch_id' => null,
                'qty' => $item->qty_received,
                'uom' => $item->uom,
                'movement_type' => 'in',
                'reference_type' => 'receipt',
                'reference_id' => $record->id,
                'notes' => "Receipt {$record->receipt_number} - {$item->packagingItem->name}",
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Receipt created successfully';
    }
}
