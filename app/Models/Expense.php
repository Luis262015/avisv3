<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'cash_shift_id',
        'user_id',
        'category',
        'description',
        'amount',
        'payment_method',
        'reference',
        'date',
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
