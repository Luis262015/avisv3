<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\Training;
use Illuminate\Support\Facades\DB;

class HrReportService
{
    /**
     * Indicadores generales de la plantilla.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $active = Employee::where('status', 'active')->count();
        $total  = Employee::count();

        $hiredThisYear = Employee::whereYear('hire_date', now()->year)->count();
        $terminatedThisYear = Employee::whereYear('termination_date', now()->year)->count();

        // Rotación = bajas del año / plantilla promedio.
        $avgHeadcount = max(($active + $terminatedThisYear) / 2, 1);
        $turnover = round(($terminatedThisYear / $avgHeadcount) * 100, 1);

        return [
            'active_employees'     => $active,
            'total_employees'      => $total,
            'hired_this_year'      => $hiredThisYear,
            'terminated_this_year' => $terminatedThisYear,
            'turnover_rate'        => $turnover,
            'avg_base_salary'      => round((float) Employee::where('status', 'active')->avg('base_salary'), 2),
            'on_leave'             => Employee::where('status', 'on_leave')->count(),
            'pending_leaves'       => LeaveRequest::where('status', 'pending')->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headcountByDepartment(): array
    {
        return Employee::query()
            ->where('employees.status', 'active')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->groupBy('departments.id', 'departments.name')
            ->select([
                DB::raw("COALESCE(departments.name, 'Sin asignar') as department"),
                DB::raw('COUNT(*) as total'),
                DB::raw('ROUND(AVG(employees.base_salary), 2) as avg_salary'),
            ])
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    /**
     * @return array<int, array{label:string, total:int}>
     */
    public function contractDistribution(): array
    {
        return Employee::query()
            ->where('status', 'active')
            ->groupBy('contract_type')
            ->select(['contract_type as label', DB::raw('COUNT(*) as total')])
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'total' => (int) $r->total])
            ->toArray();
    }

    /**
     * Costo de nómina mes a mes del año indicado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function payrollEvolution(int $year): array
    {
        return Payroll::query()
            ->where('period_year', $year)
            ->orderBy('period_month')
            ->get(['label', 'period_month', 'total_gross', 'total_deductions', 'total_net', 'status'])
            ->map(fn ($p) => [
                'label'            => $p->label,
                'month'            => $p->period_month,
                'total_gross'      => (float) $p->total_gross,
                'total_deductions' => (float) $p->total_deductions,
                'total_net'        => (float) $p->total_net,
                'status'           => $p->status,
            ])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function trainingSummary(int $year): array
    {
        return Training::query()
            ->whereYear('start_date', $year)
            ->withCount('employees')
            ->orderByDesc('start_date')
            ->get(['id', 'title', 'modality', 'status', 'hours', 'cost', 'start_date'])
            ->map(fn ($t) => [
                'id'              => $t->id,
                'title'           => $t->title,
                'modality'        => $t->modality,
                'status'          => $t->status,
                'hours'           => (float) $t->hours,
                'cost'            => (float) $t->cost,
                'participants'    => $t->employees_count,
                'start_date'      => $t->start_date?->format('Y-m-d'),
            ])
            ->toArray();
    }

    /**
     * Documentos que vencen dentro de los próximos N días (cumplimiento).
     *
     * @return array<int, array<string, mixed>>
     */
    public function expiringDocuments(int $days = 60): array
    {
        return EmployeeDocument::query()
            ->with('employee:id,first_name,last_name')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->startOfDay(), now()->addDays($days)])
            ->orderBy('expires_at')
            ->get()
            ->map(fn ($d) => [
                'id'         => $d->id,
                'employee'   => $d->employee?->full_name,
                'type'       => $d->type,
                'name'       => $d->name,
                'expires_at' => $d->expires_at?->format('Y-m-d'),
            ])
            ->toArray();
    }

    /**
     * Contratos a plazo fijo próximos a finalizar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function endingContracts(int $days = 60): array
    {
        return Employee::query()
            ->where('status', 'active')
            ->where('contract_type', 'fixed_term')
            ->whereNotNull('termination_date')
            ->whereBetween('termination_date', [now()->startOfDay(), now()->addDays($days)])
            ->orderBy('termination_date')
            ->get(['id', 'first_name', 'last_name', 'position', 'termination_date'])
            ->map(fn ($e) => [
                'id'               => $e->id,
                'employee'         => $e->full_name,
                'position'         => $e->position,
                'termination_date' => $e->termination_date?->format('Y-m-d'),
            ])
            ->toArray();
    }
}
