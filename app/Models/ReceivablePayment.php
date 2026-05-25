<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivablePayment extends Model
{
    protected $fillable = [
        'receivable_id',
        'user_id',
        'amount',
        'payment_method',
        'date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date'   => 'date',
    ];

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
