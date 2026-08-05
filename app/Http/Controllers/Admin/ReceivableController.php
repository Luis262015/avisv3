<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReceivablePaymentRequest;
use App\Http\Requests\Admin\ReceivableRequest;
use App\Models\Customer;
use App\Models\Receivable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/receivables/index', [
            'receivables' => Receivable::with(['user', 'sale', 'customer:id,name'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/receivables/create', [
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(ReceivableRequest $request)
    {
        $data = $request->validated();
        $receivable = Receivable::create(array_merge($data, [
            'user_id' => Auth::id(),
            'balance' => $data['amount'],
            'status'  => 'pending',
        ]));

        return redirect()->route('admin.receivables.show', $receivable)->with('success', 'Cuenta por cobrar registrada.');
    }

    public function show(Receivable $receivable): Response
    {
        $receivable->load(['user', 'sale', 'customer', 'payments.user']);
        return Inertia::render('admin/receivables/show', compact('receivable'));
    }

    public function storePayment(ReceivablePaymentRequest $request, Receivable $receivable)
    {
        if (in_array($receivable->status, ['paid', 'cancelled'])) {
            return back()->withErrors(['status' => 'Esta cuenta no admite más pagos.']);
        }

        DB::transaction(function () use ($request, $receivable) {
            $amount = $request->amount;

            if ($amount > $receivable->balance) {
                $amount = $receivable->balance;
            }

            $receivable->payments()->create(array_merge($request->validated(), [
                'user_id' => Auth::id(),
                'amount'  => $amount,
            ]));

            $newPaid    = $receivable->amount_paid + $amount;
            $newBalance = $receivable->amount - $newPaid;
            $status     = $newBalance <= 0 ? 'paid' : 'partial';

            $receivable->update([
                'amount_paid' => $newPaid,
                'balance'     => max(0, $newBalance),
                'status'      => $status,
            ]);
        });

        return redirect()->route('admin.receivables.show', $receivable)->with('success', 'Pago registrado.');
    }

    public function cancel(Receivable $receivable)
    {
        if ($receivable->status === 'paid') {
            return back()->withErrors(['status' => 'No se puede cancelar una cuenta ya pagada.']);
        }

        if ((float) $receivable->amount_paid > 0) {
            return back()->withErrors([
                'status' => 'Esta cuenta tiene cobros registrados. Resuélvalos antes de cancelarla.',
            ]);
        }

        // El saldo se anula junto con la cuenta: si sigue en pie, los reportes de
        // cartera lo seguirían mostrando como deuda por cobrar.
        $receivable->update(['status' => 'cancelled', 'balance' => 0]);

        return redirect()->route('admin.receivables.show', $receivable)->with('success', 'Cuenta por cobrar cancelada.');
    }
}
