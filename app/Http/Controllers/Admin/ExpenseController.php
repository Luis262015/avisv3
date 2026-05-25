<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExpenseRequest;
use App\Models\CashShift;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/expenses/index', [
            'expenses' => Expense::with(['user', 'cashShift.cashRegister'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/expenses/create', [
            'openShifts' => CashShift::where('status', 'open')
                ->with('cashRegister')
                ->get(['id', 'cash_register_id']),
        ]);
    }

    public function store(ExpenseRequest $request)
    {
        Expense::create(array_merge($request->validated(), ['user_id' => Auth::id()]));
        return redirect()->route('admin.expenses.index')->with('success', 'Gasto registrado.');
    }

    public function edit(Expense $expense): Response
    {
        return Inertia::render('admin/expenses/edit', [
            'expense'    => $expense,
            'openShifts' => CashShift::where('status', 'open')
                ->with('cashRegister')
                ->get(['id', 'cash_register_id']),
        ]);
    }

    public function update(ExpenseRequest $request, Expense $expense)
    {
        $expense->update($request->validated());
        return redirect()->route('admin.expenses.index')->with('success', 'Gasto actualizado.');
    }
}
