<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PayablePaymentRequest;
use App\Http\Requests\Admin\PayableRequest;
use App\Models\Payable;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayableController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/payables/index', [
            'payables' => Payable::with(['user', 'supplier', 'purchase'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/payables/create', [
            'suppliers' => Supplier::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function store(PayableRequest $request)
    {
        $data    = $request->validated();
        $payable = Payable::create(array_merge($data, [
            'user_id' => Auth::id(),
            'balance' => $data['amount'],
            'status'  => 'pending',
        ]));

        return redirect()->route('admin.payables.show', $payable)->with('success', 'Cuenta por pagar registrada.');
    }

    public function show(Payable $payable): Response
    {
        $payable->load(['user', 'supplier', 'purchase', 'payments.user']);
        return Inertia::render('admin/payables/show', compact('payable'));
    }

    public function storePayment(PayablePaymentRequest $request, Payable $payable)
    {
        if (in_array($payable->status, ['paid', 'cancelled'])) {
            return back()->withErrors(['status' => 'Esta cuenta no admite más pagos.']);
        }

        DB::transaction(function () use ($request, $payable) {
            $amount = $request->amount;

            if ($amount > $payable->balance) {
                $amount = $payable->balance;
            }

            $payable->payments()->create(array_merge($request->validated(), [
                'user_id' => Auth::id(),
                'amount'  => $amount,
            ]));

            $newPaid    = $payable->amount_paid + $amount;
            $newBalance = $payable->amount - $newPaid;
            $status     = $newBalance <= 0 ? 'paid' : 'partial';

            $payable->update([
                'amount_paid' => $newPaid,
                'balance'     => max(0, $newBalance),
                'status'      => $status,
            ]);
        });

        return redirect()->route('admin.payables.show', $payable)->with('success', 'Pago registrado.');
    }

    public function cancel(Payable $payable)
    {
        if ($payable->status === 'paid') {
            return back()->withErrors(['status' => 'No se puede cancelar una cuenta ya pagada.']);
        }

        $payable->update(['status' => 'cancelled']);
        return redirect()->route('admin.payables.show', $payable)->with('success', 'Cuenta por pagar cancelada.');
    }
}
