<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackagingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'packaging_code',
        'name',
        'base_uom',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the BOMs that use this packaging item.
     */
    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    /**
     * Get the receipt items for this packaging item.
     */
    public function receiptItems(): HasMany
    {
        return $this->hasMany(ReceiptItem::class);
    }

    /**
     * Get the stock movements for this packaging item.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id')
            ->where('item_type', 'packaging');
    }

    /**
     * Get current stock for this packaging item.
     */
    public function getCurrentStock()
    {
        return $this->stockMovements()
            ->selectRaw('SUM(CASE WHEN movement_type = "in" THEN qty ELSE -qty END) as current_stock')
            ->value('current_stock') ?? 0;
    }
}
