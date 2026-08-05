<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receivable extends Model
{
    protected $fillable = [
        'sale_id',
        'customer_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'description',
        'amount',
        'amount_paid',
        'balance',
        'due_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance'     => 'decimal:2',
        'due_date'    => 'date',
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

    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }

    /** Cuentas que todavía representan deuda viva. */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'partial']);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date->isPast();
    }

    /**
     * Nombre a mostrar: el del cliente del padrón si está enlazado, o el texto
     * libre para deudores ocasionales.
     */
    public function getDisplayCustomerAttribute(): string
    {
        return $this->customer?->name ?? $this->customer_name ?? 'Sin cliente';
    }
}
