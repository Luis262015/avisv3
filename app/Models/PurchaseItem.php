<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'received_quantity',
        'cost',
        'subtotal',
    ];

    protected $casts = [
        'quantity'          => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'cost'              => 'decimal:2',
        'subtotal'          => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pendingQuantity(): float
    {
        return max(0, (float) $this->quantity - (float) ($this->received_quantity ?? 0));
    }
}
