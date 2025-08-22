<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'production_batch_item_id',
        'qty_shipped',
        'uom',
        'notes',
    ];

    protected $casts = [
        'qty_shipped' => 'decimal:2',
    ];

    /**
     * Get the shipment that owns the shipment item.
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Get the production batch item that belongs to the shipment item.
     */
    public function productionBatchItem(): BelongsTo
    {
        return $this->belongsTo(ProductionBatchItem::class);
    }
}
