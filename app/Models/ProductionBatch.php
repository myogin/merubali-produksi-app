<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ProductionBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_date',
        'po_number',
        'notes',
    ];

    protected $casts = [
        'production_date' => 'date',
    ];

    /**
     * Get the production batch items for the production batch.
     */
    public function productionBatchItems(): HasMany
    {
        return $this->hasMany(ProductionBatchItem::class);
    }

    /**
     * Get the stock movements for this batch through batch items.
     */
    public function stockMovements(): HasManyThrough
    {
        return $this->hasManyThrough(StockMovement::class, ProductionBatchItem::class, 'production_batch_id', 'batch_id');
    }

    /**
     * Get total produced quantity for this batch.
     */
    public function getTotalProduced()
    {
        return $this->productionBatchItems()->sum('qty_produced');
    }

    /**
     * Get total shipped quantity for this batch.
     */
    public function getTotalShipped()
    {
        return $this->productionBatchItems()->withSum('shipmentItems', 'qty_shipped')->get()->sum('shipment_items_sum_qty_shipped') ?? 0;
    }

    /**
     * Get remaining stock for this batch.
     */
    public function getRemainingStock()
    {
        return $this->getTotalProduced() - $this->getTotalShipped();
    }
}
