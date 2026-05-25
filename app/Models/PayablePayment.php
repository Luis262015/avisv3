<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayablePayment extends Model
{
    protected $fillable = [
        'payable_id',
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

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
