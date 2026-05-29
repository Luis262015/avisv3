<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'quantity_received',
        'cost',
        'subtotal',
    ];

    protected $casts = [
        'quantity'          => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'cost'              => 'decimal:2',
        'subtotal'          => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pendingQuantity(): float
    {
        return max(0, (float) $this->quantity - (float) $this->quantity_received);
    }
}
