<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    protected $fillable = [
        'cash_shift_id',
        'user_id',
        'customer_id',
        'promotion_id',
        'folio',
        'subtotal',
        'tax',
        'discount',
        'total',
        'amount_paid',
        'change_amount',
        'payment_method',
        'status',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by_user_id',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'tax'           => 'decimal:2',
        'discount'      => 'decimal:2',
        'total'         => 'decimal:2',
        'amount_paid'   => 'decimal:2',
        'change_amount' => 'decimal:2',
        'cancelled_at'  => 'datetime',
    ];

    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function inventoryMovements(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }

    public function siatInvoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SiatInvoice::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
