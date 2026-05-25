<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'user_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'reference_id',
        'reference_type',
        'reason',
    ];

    protected $casts = [
        'quantity'     => 'decimal:2',
        'stock_before' => 'integer',
        'stock_after'  => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
