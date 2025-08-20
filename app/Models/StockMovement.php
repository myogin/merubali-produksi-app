<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'movement_date',
        'item_type',
        'item_id',
        'batch_id',
        'qty',
        'uom',
        'movement_type',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'qty' => 'decimal:2',
    ];

    /**
     * Get the product (for finished goods movements).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'item_id')
            ->where('item_type', 'finished_goods');
    }

    /**
     * Get the packaging item (for packaging movements).
     */
    public function packagingItem(): BelongsTo
    {
        return $this->belongsTo(PackagingItem::class, 'item_id')
            ->where('item_type', 'packaging');
    }

    /**
     * Get the production batch (for finished goods movements).
     */
    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    /**
     * Get the receipt (for receipt reference).
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'reference_id')
            ->where('reference_type', 'receipt');
    }

    /**
     * Get the shipment (for shipment reference).
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'reference_id')
            ->where('reference_type', 'shipment');
    }

    /**
     * Get the production batch reference (for production reference).
     */
    public function productionBatchReference(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'reference_id')
            ->where('reference_type', 'production');
    }

    /**
     * Scope for packaging movements.
     */
    public function scopePackaging($query)
    {
        return $query->where('item_type', 'packaging');
    }

    /**
     * Scope for finished goods movements.
     */
    public function scopeFinishedGoods($query)
    {
        return $query->where('item_type', 'finished_goods');
    }

    /**
     * Scope for inbound movements.
     */
    public function scopeInbound($query)
    {
        return $query->where('movement_type', 'in');
    }

    /**
     * Scope for outbound movements.
     */
    public function scopeOutbound($query)
    {
        return $query->where('movement_type', 'out');
    }
}
