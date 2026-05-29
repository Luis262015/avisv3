<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    protected $fillable = [
        'user_id',
        'period_year',
        'period_month',
        'label',
        'pay_date',
        'status',
        'total_gross',
        'total_deductions',
        'total_net',
        'notes',
    ];

    protected $casts = [
        'period_year'      => 'integer',
        'period_month'     => 'integer',
        'pay_date'         => 'date',
        'total_gross'      => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net'        => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function recalculateTotals(): void
    {
        $this->update([
            'total_gross'      => $this->items()->sum('gross_salary'),
            'total_deductions' => $this->items()->sum('total_deductions'),
            'total_net'        => $this->items()->sum('net_salary'),
        ]);
    }
}
