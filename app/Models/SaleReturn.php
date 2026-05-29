<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $fillable = [
        'sale_id',
        'customer_id',
        'user_id',
        'folio',
        'date',
        'reason',
        'refund_method',
        'refund_amount',
        'status',
        'restock',
        'notes',
    ];

    protected $casts = [
        'date'          => 'date',
        'refund_amount' => 'decimal:2',
        'restock'       => 'boolean',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public static function nextFolio(): string
    {
        $last = static::lockForUpdate()->max('id') ?? 0;
        return 'DEV-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }
}
