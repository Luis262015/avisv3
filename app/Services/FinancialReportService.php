<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use App\Models\Payable;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Resolve the [from, to] Carbon range from the requested period preset.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public function resolveRange(array $filters): array
    {
        $period = $filters['period'] ?? 'month';
        $now    = Carbon::now();

        [$from, $to] = match ($period) {
            'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'year'    => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom'  => [
                isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : $now->copy()->startOfMonth(),
                isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : $now->copy()->endOfDay(),
            ],
            default   => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        return [$from, $to, $period === 'custom' ? 'custom' : $period];
    }

    /**
     * Top-level financial summary (cash-flow style result statement).
     */
    public function summary(array $filters): array
    {
        [$from, $to] = $this->resolveRange($filters);

        $sales       = (float) $this->salesQuery($from, $to, $filters)->sum('total');
        $incomes     = (float) $this->dateScoped(Income::query(), $from, $to, $filters)->sum('amount');
        $purchases   = (float) $this->purchasesQuery($from, $to, $filters)->sum('total');
        $expenses    = (float) $this->dateScoped(Expense::query(), $from, $to, $filters)->sum('amount');
        $withdrawals = (float) $this->dateScoped(Withdrawal::query(), $from, $to, $filters)->sum('amount');

        $totalIncome  = $sales + $incomes;
        $totalOutflow = $purchases + $expenses + $withdrawals;

        return [
            'sales'        => $sales,
            'incomes'      => $incomes,
            'purchases'    => $purchases,
            'expenses'     => $expenses,
            'withdrawals'  => $withdrawals,
            'total_income' => $totalIncome,
            'total_outflow' => $totalOutflow,
            'net_result'   => $totalIncome - $totalOutflow,
        ];
    }

    /**
     * Accounts receivable: outstanding balance now + amounts generated in period.
     */
    public function receivables(array $filters): array
    {
        [$from, $to] = $this->resolveRange($filters);

        $generated = Receivable::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['pending', 'partial', 'paid']);

        $outstanding = Receivable::query()->whereIn('status', ['pending', 'partial']);

        return [
            'generated_count'   => (clone $generated)->count(),
            'generated_amount'  => (float) (clone $generated)->sum('amount'),
            'outstanding_count' => (clone $outstanding)->count(),
            'outstanding_balance' => (float) (clone $outstanding)->sum('balance'),
            'overdue_balance'   => (float) (clone $outstanding)
                ->whereDate('due_date', '<', Carbon::today())
                ->sum('balance'),
            'collected_in_period' => $this->paymentsInPeriod('receivable_payments', $from, $to),
        ];
    }

    /**
     * Accounts payable: outstanding balance now + amounts generated in period.
     */
    public function payables(array $filters): array
    {
        [$from, $to] = $this->resolveRange($filters);

        $generated = Payable::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['pending', 'partial', 'paid']);

        $outstanding = Payable::query()->whereIn('status', ['pending', 'partial']);

        return [
            'generated_count'   => (clone $generated)->count(),
            'generated_amount'  => (float) (clone $generated)->sum('amount'),
            'outstanding_count' => (clone $outstanding)->count(),
            'outstanding_balance' => (float) (clone $outstanding)->sum('balance'),
            'overdue_balance'   => (float) (clone $outstanding)
                ->whereDate('due_date', '<', Carbon::today())
                ->sum('balance'),
            'paid_in_period'    => $this->paymentsInPeriod('payable_payments', $from, $to),
        ];
    }

    /**
     * Expense breakdown by category for the period.
     */
    public function expensesByCategory(array $filters): Collection
    {
        [$from, $to] = $this->resolveRange($filters);

        return $this->dateScoped(Expense::query(), $from, $to, $filters)
            ->select('category', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();
    }

    /**
     * Income breakdown by category for the period.
     */
    public function incomesByCategory(array $filters): Collection
    {
        [$from, $to] = $this->resolveRange($filters);

        return $this->dateScoped(Income::query(), $from, $to, $filters)
            ->select('category', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();
    }

    /**
     * Combined monthly evolution of every financial flow within the period.
     */
    public function evolution(array $filters): Collection
    {
        [$from, $to] = $this->resolveRange($filters);

        $months = [];

        $merge = function (string $key, $rows) use (&$months) {
            foreach ($rows as $row) {
                $month = $row->month;
                $months[$month] ??= [
                    'month' => $month, 'sales' => 0, 'incomes' => 0,
                    'purchases' => 0, 'expenses' => 0, 'withdrawals' => 0,
                ];
                $months[$month][$key] = (float) $row->total_amount;
            }
        };

        $merge('sales', $this->monthlySum($this->salesQuery($from, $to, $filters), 'sales.created_at', 'total'));
        $merge('purchases', $this->monthlySum($this->purchasesQuery($from, $to, $filters), 'date', 'total'));
        $merge('incomes', $this->monthlySum($this->dateScoped(Income::query(), $from, $to, $filters), 'date', 'amount'));
        $merge('expenses', $this->monthlySum($this->dateScoped(Expense::query(), $from, $to, $filters), 'date', 'amount'));
        $merge('withdrawals', $this->monthlySum($this->dateScoped(Withdrawal::query(), $from, $to, $filters), 'date', 'amount'));

        return collect($months)
            ->sortKeys()
            ->values()
            ->map(function (array $m) {
                $m['net_result'] = ($m['sales'] + $m['incomes']) - ($m['purchases'] + $m['expenses'] + $m['withdrawals']);
                return $m;
            });
    }

    // ── Internal query builders ─────────────────────────────────────────────

    private function salesQuery(Carbon $from, Carbon $to, array $filters): Builder
    {
        return Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['store_id'] ?? null, fn($q, $v) => $q->whereHas(
                'cashShift.cashRegister',
                fn($q2) => $q2->where('store_id', $v)
            ));
    }

    private function purchasesQuery(Carbon $from, Carbon $to, array $filters): Builder
    {
        return Purchase::query()
            ->whereIn('status', ['received', 'partial'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($filters['store_id'] ?? null, fn($q, $v) => $q->where('store_id', $v));
    }

    /**
     * Scope a cash-shift-bound model (expense/income/withdrawal) by date + store.
     */
    private function dateScoped(Builder $query, Carbon $from, Carbon $to, array $filters): Builder
    {
        return $query
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($filters['store_id'] ?? null, fn($q, $v) => $q->whereHas(
                'cashShift.cashRegister',
                fn($q2) => $q2->where('store_id', $v)
            ));
    }

    /**
     * Group a query by YYYY-MM and sum the given column.
     */
    private function monthlySum(Builder $query, string $dateColumn, string $sumColumn): Collection
    {
        $group = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$dateColumn})"
            : "DATE_FORMAT({$dateColumn}, '%Y-%m')";

        return $query
            ->selectRaw("{$group} as month, SUM({$sumColumn}) as total_amount")
            ->groupByRaw($group)
            ->get();
    }

    private function paymentsInPeriod(string $table, Carbon $from, Carbon $to): float
    {
        return (float) DB::table($table)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
    }
}
