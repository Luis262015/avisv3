<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\GuardsClosedCashShifts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IncomeRequest;
use App\Models\CashShift;
use App\Models\Store;
use App\Models\Income;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class IncomeController extends Controller
{
    use GuardsClosedCashShifts;

    public function index(): Response
    {
        return Inertia::render('admin/incomes/index', [
            'incomes' => Income::with(['user', 'cashShift.cashRegister'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/incomes/create', [
            'openShifts' => CashShift::where('status', 'open')
                ->with('cashRegister')
                ->get(['id', 'cash_register_id']),
            'stores'     => Store::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(IncomeRequest $request)
    {
        Income::create(array_merge($request->validated(), ['user_id' => Auth::id()]));
        return redirect()->route('admin.incomes.index')->with('success', 'Ingreso registrado.');
    }

    public function edit(Income $income): Response|\Illuminate\Http\RedirectResponse
    {
        if ($error = $this->closedShiftError($income)) {
            return redirect()->route("admin.incomes.index")->withErrors(["status" => $error]);
        }

        return Inertia::render('admin/incomes/edit', [
            'income'     => $income,
            'openShifts' => CashShift::where('status', 'open')
                ->with('cashRegister')
                ->get(['id', 'cash_register_id']),
            'stores'     => Store::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(IncomeRequest $request, Income $income)
    {
        if ($error = $this->closedShiftError($income)) {
            return back()->withErrors(['status' => $error]);
        }

        $income->update($request->validated());
        return redirect()->route('admin.incomes.index')->with('success', 'Ingreso actualizado.');
    }
}
