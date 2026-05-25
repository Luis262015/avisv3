<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IncomeRequest;
use App\Models\CashShift;
use App\Models\Income;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class IncomeController extends Controller
{
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
        ]);
    }

    public function store(IncomeRequest $request)
    {
        Income::create(array_merge($request->validated(), ['user_id' => Auth::id()]));
        return redirect()->route('admin.incomes.index')->with('success', 'Ingreso registrado.');
    }

    public function edit(Income $income): Response
    {
        return Inertia::render('admin/incomes/edit', [
            'income'     => $income,
            'openShifts' => CashShift::where('status', 'open')
                ->with('cashRegister')
                ->get(['id', 'cash_register_id']),
        ]);
    }

    public function update(IncomeRequest $request, Income $income)
    {
        $income->update($request->validated());
        return redirect()->route('admin.incomes.index')->with('success', 'Ingreso actualizado.');
    }
}
