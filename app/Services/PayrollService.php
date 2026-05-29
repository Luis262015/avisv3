<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    /** Aporte laboral total a las AFP (Bolivia): 12.71 %. */
    private const AFP_RATE = 0.1271;

    /** Alícuota del RC-IVA dependientes. */
    private const RC_IVA_RATE = 0.13;

    /** Salario mínimo nacional de referencia (Bs). */
    private const MIN_WAGE = 2500.0;

    private const MONTHS = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * Crea la planilla del periodo y genera un ítem por cada empleado activo
     * con los cálculos sugeridos (Bolivia). Los montos quedan editables.
     */
    public function generate(array $data, int $userId): Payroll
    {
        $year  = (int) $data['period_year'];
        $month = (int) $data['period_month'];

        if (Payroll::where('period_year', $year)->where('period_month', $month)->exists()) {
            throw ValidationException::withMessages([
                'period_month' => 'Ya existe una planilla para ese periodo.',
            ]);
        }

        return DB::transaction(function () use ($data, $userId, $year, $month) {
            $payroll = Payroll::create([
                'user_id'      => $userId,
                'period_year'  => $year,
                'period_month' => $month,
                'label'        => (self::MONTHS[$month] ?? $month) . " {$year}",
                'pay_date'     => $data['pay_date'] ?? null,
                'status'       => 'draft',
                'notes'        => $data['notes'] ?? null,
            ]);

            $periodEnd = now()->setDate($year, $month, 1)->endOfMonth();

            Employee::active()->get()->each(function (Employee $employee) use ($payroll, $periodEnd) {
                $payroll->items()->create(
                    $this->computeItem($employee, $periodEnd)
                );
            });

            $payroll->recalculateTotals();

            return $payroll;
        });
    }

    /**
     * Recalcula un ítem ya existente tras una edición manual de los conceptos
     * variables (días trabajados, horas extra, bonos, préstamos).
     */
    public function recalculateItem(PayrollItem $item, array $data): PayrollItem
    {
        $base    = (float) $item->base_salary;
        $days    = (int) $data['worked_days'];
        $earned  = round($base * min($days, 30) / 30, 2);

        $antiquity = (float) $data['antiquity_bonus'];
        $overtime  = (float) $data['overtime_amount'];
        $other     = (float) $data['other_earnings'];

        $gross = round($earned + $antiquity + $overtime + $other, 2);

        $afp   = round($gross * self::AFP_RATE, 2);
        $rcIva = $this->rcIva($gross, $afp);
        $loans = (float) $data['loans_deduction'];
        $otherD = (float) $data['other_deductions'];

        $totalDeductions = round($afp + $rcIva + $loans + $otherD, 2);

        $item->update([
            'worked_days'      => $days,
            'antiquity_bonus'  => $antiquity,
            'overtime_amount'  => $overtime,
            'other_earnings'   => $other,
            'gross_salary'     => $gross,
            'afp_deduction'    => $afp,
            'rc_iva_deduction' => $rcIva,
            'loans_deduction'  => $loans,
            'other_deductions' => $otherD,
            'total_deductions' => $totalDeductions,
            'net_salary'       => round($gross - $totalDeductions, 2),
            'notes'            => $data['notes'] ?? $item->notes,
        ]);

        $item->payroll->recalculateTotals();

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function computeItem(Employee $employee, \DateTimeInterface $periodEnd): array
    {
        $base      = (float) $employee->base_salary;
        $antiquity = $this->antiquityBonus($employee, $periodEnd);
        $gross     = round($base + $antiquity, 2);
        $afp       = round($gross * self::AFP_RATE, 2);
        $rcIva     = $this->rcIva($gross, $afp);
        $totalDed  = round($afp + $rcIva, 2);

        return [
            'employee_id'      => $employee->id,
            'base_salary'      => $base,
            'worked_days'      => 30,
            'antiquity_bonus'  => $antiquity,
            'overtime_amount'  => 0,
            'other_earnings'   => 0,
            'gross_salary'     => $gross,
            'afp_deduction'    => $afp,
            'rc_iva_deduction' => $rcIva,
            'loans_deduction'  => 0,
            'other_deductions' => 0,
            'total_deductions' => $totalDed,
            'net_salary'       => round($gross - $totalDed, 2),
        ];
    }

    /**
     * Bono de antigüedad: porcentaje por tramo de años de servicio aplicado
     * sobre 3 salarios mínimos nacionales (escala vigente en Bolivia).
     */
    private function antiquityBonus(Employee $employee, \DateTimeInterface $asOf): float
    {
        $years = $employee->yearsOfService($asOf);
        $pct   = $this->antiquityRate($years);

        if ($pct <= 0) {
            return 0;
        }

        return round(3 * self::MIN_WAGE * $pct, 2);
    }

    private function antiquityRate(int $years): float
    {
        return match (true) {
            $years >= 25 => 0.50,
            $years >= 20 => 0.42,
            $years >= 15 => 0.34,
            $years >= 11 => 0.26,
            $years >= 8  => 0.18,
            $years >= 5  => 0.11,
            $years >= 2  => 0.05,
            default      => 0.0,
        };
    }

    /**
     * RC-IVA dependientes: 13 % sobre (ganado − AFP − 2 salarios mínimos),
     * cuando el resultado es positivo.
     */
    private function rcIva(float $gross, float $afp): float
    {
        $taxable = $gross - $afp - (2 * self::MIN_WAGE);

        return $taxable > 0 ? round($taxable * self::RC_IVA_RATE, 2) : 0.0;
    }
}
