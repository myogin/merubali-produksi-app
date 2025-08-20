<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_id',
        'packaging_item_id',
        'qty_received',
        'uom',
        'notes',
    ];

    protected $casts = [
        'qty_received' => 'decimal:2',
    ];

    /**
     * Get the receipt that owns the receipt item.
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * Get the packaging item that belongs to the receipt item.
     */
    public function packagingItem(): BelongsTo
    {
        return $this->belongsTo(PackagingItem::class);
    }
}
