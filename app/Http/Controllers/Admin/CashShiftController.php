<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CashShiftRequest;
use App\Models\CashRegister;
use App\Models\CashShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CashShiftController extends Controller
{
    public function index(): Response
    {
        $myOpenShift = CashShift::where('user_id', Auth::id())
            ->where('status', 'open')
            ->with('cashRegister')
            ->first();

        $shifts = CashShift::with(['cashRegister.store', 'user'])
            ->withSum(
                ['sales as total_sales_amount' => fn($q) => $q->where('status', 'completed')],
                'total'
            )
            ->withSum('expenses as total_expenses_amount', 'amount')
            ->withSum('incomes as total_incomes_amount', 'amount')
            ->withSum('withdrawals as withdrawals_total', 'amount')
            ->latest()
            ->paginate(20);

        return Inertia::render('admin/cash-shifts/index', [
            'shifts'      => $shifts,
            'myOpenShift' => $myOpenShift
                ? ['id' => $myOpenShift->id, 'register_name' => $myOpenShift->cashRegister->name]
                : null,
        ]);
    }

    public function create()
    {
        $myOpenShift = CashShift::where('user_id', Auth::id())
            ->where('status', 'open')
            ->first();

        if ($myOpenShift) {
            return redirect()->route('admin.cash-shifts.show', $myOpenShift)
                ->with('error', 'Ya tienes un turno abierto. Debes cerrarlo antes de iniciar otro.');
        }

        return Inertia::render('admin/cash-shifts/create', [
            'registers' => CashRegister::with('store')
                ->where('is_active', true)
                ->whereDoesntHave('shifts', fn($q) => $q->where('status', 'open'))
                ->get(),
        ]);
    }

    public function store(CashShiftRequest $request)
    {
        // Un usuario solo puede tener un turno abierto a la vez
        $userHasOpenShift = CashShift::where('user_id', Auth::id())
            ->where('status', 'open')
            ->exists();

        if ($userHasOpenShift) {
            return back()->withErrors(['cash_register_id' => 'Ya tienes un turno abierto. Ciérralo antes de iniciar uno nuevo.']);
        }

        // La caja no puede tener otro turno abierto
        $registerHasOpenShift = CashShift::where('cash_register_id', $request->cash_register_id)
            ->where('status', 'open')
            ->exists();

        if ($registerHasOpenShift) {
            return back()->withErrors(['cash_register_id' => 'Ya existe un turno abierto en esta caja.']);
        }

        $shift = CashShift::create([
            ...$request->validated(),
            'user_id'    => Auth::id(),
            'opened_at'  => now(),
            'status'     => 'open',
        ]);

        return redirect()->route('admin.cash-shifts.show', $shift)
            ->with('success', 'Turno iniciado. ¡Listo para vender!');
    }

    public function show(CashShift $cashShift): Response
    {
        $cashShift->load(['cashRegister.store', 'user', 'sales', 'expenses', 'incomes', 'withdrawals']);

        $completedSales = $cashShift->sales->where('status', 'completed');

        // Desglose por método de pago (calculado desde los datos ya en memoria)
        $salesByMethod = $completedSales
            ->groupBy('payment_method')
            ->map(fn($g) => round($g->sum('total'), 2))
            ->toArray();

        $expensesByMethod = $cashShift->expenses
            ->groupBy('payment_method')
            ->map(fn($g) => round($g->sum('amount'), 2))
            ->toArray();

        $incomesByMethod = $cashShift->incomes
            ->groupBy('payment_method')
            ->map(fn($g) => round($g->sum('amount'), 2))
            ->toArray();

        return Inertia::render('admin/cash-shifts/show', [
            'shift'            => $cashShift,
            'totalSales'       => round($completedSales->sum('total'), 2),
            'salesCount'       => $completedSales->count(),
            'salesByMethod'    => $salesByMethod,
            'totalExpenses'    => round($cashShift->expenses->sum('amount'), 2),
            'expensesByMethod' => $expensesByMethod,
            'totalIncomes'     => round($cashShift->incomes->sum('amount'), 2),
            'incomesByMethod'  => $incomesByMethod,
            'withdrawalsTotal' => round($cashShift->withdrawals->sum('amount'), 2),
        ]);
    }

    public function close(Request $request, CashShift $cashShift)
    {
        $request->validate([
            'closing_amount' => ['required', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string'],
        ]);

        if (! $cashShift->isOpen()) {
            return back()->withErrors(['shift' => 'Este turno ya está cerrado.']);
        }

        $totalSales    = (float) $cashShift->sales()->where('status', 'completed')->sum('total');
        $totalIncomes  = (float) $cashShift->incomes()->sum('amount');
        $totalExpenses = (float) $cashShift->expenses()->sum('amount');
        $withdrawals   = (float) $cashShift->withdrawals()->sum('amount');

        $expected = (float) $cashShift->opening_amount + $totalSales + $totalIncomes - $totalExpenses - $withdrawals;

        $cashShift->update([
            'closing_amount'  => $request->closing_amount,
            'expected_amount' => $expected,
            'difference'      => (float) $request->closing_amount - $expected,
            'closed_at'       => now(),
            'status'          => 'closed',
            'notes'           => $request->notes,
        ]);

        return redirect()->route('admin.cash-shifts.show', $cashShift)->with('success', 'Turno cerrado.');
    }
}
