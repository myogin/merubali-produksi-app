<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the BOMs for the product.
     */
    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    /**
     * Get the production batches for the product.
     */
    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class);
    }

    /**
     * Get the stock movements for the product.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id')
            ->where('item_type', 'finished_goods');
    }

    /**
     * Get current stock for this product across all batches.
     */
    public function getCurrentStock()
    {
        return $this->stockMovements()
            ->selectRaw('SUM(CASE WHEN movement_type = "in" THEN qty ELSE -qty END) as current_stock')
            ->value('current_stock') ?? 0;
    }
}
