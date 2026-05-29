<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesOrder extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'quote_id',
        'sale_id',
        'folio',
        'date',
        'expected_date',
        'status',
        'payment_status',
        'subtotal',
        'tax',
        'discount',
        'total',
        'shipping_address',
        'notes',
    ];

    protected $casts = [
        'date'          => 'date',
        'expected_date' => 'date',
        'subtotal'      => 'decimal:2',
        'tax'           => 'decimal:2',
        'discount'      => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public static function nextFolio(): string
    {
        $last = static::lockForUpdate()->max('id') ?? 0;
        return 'PED-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }
}
