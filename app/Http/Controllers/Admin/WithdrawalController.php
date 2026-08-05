<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\GuardsClosedCashShifts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WithdrawalRequest;
use App\Models\CashShift;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    use GuardsClosedCashShifts;

    public function index(): Response
    {
        return Inertia::render('admin/withdrawals/index', [
            'withdrawals' => Withdrawal::with(['user', 'cashShift.cashRegister'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/withdrawals/create', [
            'openShifts' => CashShift::where('status', 'open')
                ->with('cashRegister')
                ->get(['id', 'cash_register_id']),
        ]);
    }

    public function store(WithdrawalRequest $request)
    {
        Withdrawal::create(array_merge($request->validated(), ['user_id' => Auth::id()]));
        return redirect()->route('admin.withdrawals.index')->with('success', 'Retiro registrado.');
    }

    public function edit(Withdrawal $withdrawal): Response|\Illuminate\Http\RedirectResponse
    {
        if ($error = $this->closedShiftError($withdrawal)) {
            return redirect()->route("admin.withdrawals.index")->withErrors(["status" => $error]);
        }

        return Inertia::render('admin/withdrawals/edit', [
            'withdrawal' => $withdrawal,
            'openShifts' => CashShift::where('status', 'open')
                ->with('cashRegister')
                ->get(['id', 'cash_register_id']),
        ]);
    }

    public function update(WithdrawalRequest $request, Withdrawal $withdrawal)
    {
        if ($error = $this->closedShiftError($withdrawal)) {
            return back()->withErrors(['status' => $error]);
        }

        $withdrawal->update($request->validated());
        return redirect()->route('admin.withdrawals.index')->with('success', 'Retiro actualizado.');
    }
}
