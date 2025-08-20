<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bom extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'packaging_item_id',
        'qty_per_unit',
        'uom',
    ];

    protected $casts = [
        'qty_per_unit' => 'decimal:3',
    ];

    /**
     * Get the product that owns the BOM.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the packaging item that belongs to the BOM.
     */
    public function packagingItem(): BelongsTo
    {
        return $this->belongsTo(PackagingItem::class);
    }

    /**
     * Calculate total packaging requirement for given production quantity.
     */
    public function calculateRequirement(int $productionQty): float
    {
        return $this->qty_per_unit * $productionQty;
    }
}
