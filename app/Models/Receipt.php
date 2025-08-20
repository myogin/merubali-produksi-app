<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'supplier_name',
        'delivery_note_url',
        'notes',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    /**
     * Get the receipt items for the receipt.
     */
    public function receiptItems(): HasMany
    {
        return $this->hasMany(ReceiptItem::class);
    }

    /**
     * Get the stock movements for this receipt.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', 'receipt');
    }
}
