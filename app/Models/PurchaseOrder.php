<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'supplier_id',
        'store_id',
        'user_id',
        'folio',
        'date',
        'expected_date',
        'status',
        'subtotal',
        'tax',
        'total',
        'notes',
    ];

    protected $casts = [
        'date'          => 'date',
        'expected_date' => 'date',
        'subtotal'      => 'decimal:2',
        'tax'           => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'confirmed']);
    }

    public function pendingQuantityFor(int $productId): float
    {
        $item = $this->items->firstWhere('product_id', $productId);
        return $item ? max(0, (float) $item->quantity - (float) $item->quantity_received) : 0;
    }

    public static function nextFolio(): string
    {
        $last = static::lockForUpdate()->max('id') ?? 0;
        return 'OC-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }
}
