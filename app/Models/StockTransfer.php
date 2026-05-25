<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StockTransfer extends Model
{
    protected $fillable = [
        'folio',
        'from_store_id',
        'to_store_id',
        'user_id',
        'status',
        'notes',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function inventoryMovements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }
}
