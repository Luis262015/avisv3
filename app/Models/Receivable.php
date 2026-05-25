<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receivable extends Model
{
    protected $fillable = [
        'sale_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date->isPast();
    }
}
