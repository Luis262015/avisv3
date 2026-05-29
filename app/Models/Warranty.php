<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warranty extends Model
{
    protected $fillable = [
        'sale_id',
        'sale_item_id',
        'product_id',
        'customer_id',
        'folio',
        'serial_number',
        'start_date',
        'end_date',
        'terms',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class);
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->end_date->isPast();
    }

    public static function nextFolio(): string
    {
        $last = static::lockForUpdate()->max('id') ?? 0;
        return 'GAR-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }
}
