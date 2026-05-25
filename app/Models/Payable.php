<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payable extends Model
{
    protected $fillable = [
        'purchase_id',
        'supplier_id',
        'user_id',
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

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayablePayment::class);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date->isPast();
    }
}
