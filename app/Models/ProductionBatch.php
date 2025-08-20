<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_code',
        'production_date',
        'po_number',
        'product_id',
        'qty_produced',
        'uom',
        'notes',
    ];

    protected $casts = [
        'production_date' => 'date',
        'qty_produced' => 'decimal:2',
    ];

    /**
     * Get the product that owns the production batch.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the shipment items for this batch.
     */
    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /**
     * Get the stock movements for this batch.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'batch_id');
    }

    /**
     * Get current stock for this batch.
     */
    public function getCurrentStock()
    {
        return $this->stockMovements()
            ->where('item_type', 'finished_goods')
            ->selectRaw('SUM(CASE WHEN movement_type = "in" THEN qty ELSE -qty END) as current_stock')
            ->value('current_stock') ?? 0;
    }

    /**
     * Get total shipped quantity for this batch.
     */
    public function getTotalShipped()
    {
        return $this->shipmentItems()->sum('qty_shipped');
    }

    /**
     * Get remaining stock for this batch.
     */
    public function getRemainingStock()
    {
        return $this->qty_produced - $this->getTotalShipped();
    }
}
