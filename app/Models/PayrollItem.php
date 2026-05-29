<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_id',
        'employee_id',
        'base_salary',
        'worked_days',
        'antiquity_bonus',
        'overtime_amount',
        'other_earnings',
        'gross_salary',
        'afp_deduction',
        'rc_iva_deduction',
        'loans_deduction',
        'other_deductions',
        'total_deductions',
        'net_salary',
        'notes',
    ];

    protected $casts = [
        'base_salary'      => 'decimal:2',
        'worked_days'      => 'integer',
        'antiquity_bonus'  => 'decimal:2',
        'overtime_amount'  => 'decimal:2',
        'other_earnings'   => 'decimal:2',
        'gross_salary'     => 'decimal:2',
        'afp_deduction'    => 'decimal:2',
        'rc_iva_deduction' => 'decimal:2',
        'loans_deduction'  => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary'       => 'decimal:2',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
