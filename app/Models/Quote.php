<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'sale_id',
        'folio',
        'date',
        'valid_until',
        'status',
        'subtotal',
        'tax',
        'discount',
        'total',
        'notes',
    ];

    protected $casts = [
        'date'        => 'date',
        'valid_until' => 'date',
        'subtotal'    => 'decimal:2',
        'tax'         => 'decimal:2',
        'discount'    => 'decimal:2',
        'total'       => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function isExpired(): bool
    {
        return $this->valid_until
            && ! in_array($this->status, ['accepted', 'converted', 'cancelled', 'rejected'])
            && $this->valid_until->isPast();
    }

    public static function nextFolio(): string
    {
        $last = static::lockForUpdate()->max('id') ?? 0;
        return 'COT-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }
}
