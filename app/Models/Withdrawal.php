<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'cash_shift_id',
        'user_id',
        'amount',
        'reason',
        'date',
        'authorized_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date'   => 'date',
    ];

    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
