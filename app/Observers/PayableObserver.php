<?php

namespace App\Observers;

use App\Models\Payable;
use App\Models\Purchase;

class PayableObserver
{
    /**
     * Mantiene sincronizado purchases.payment_status con el estado de la CxP.
     *
     * Se observa Payable (y no PayablePayment) porque el pago se crea ANTES de
     * recalcular el estado de la cuenta: observando el pago siempre se leería el
     * estado anterior y la compra jamás pasaría a 'partial' / 'paid'.
     */
    public function updated(Payable $payable): void
    {
        if (! $payable->wasChanged('status')) {
            return;
        }

        $this->syncPurchase($payable);
    }

    public function created(Payable $payable): void
    {
        $this->syncPurchase($payable);
    }

    private function syncPurchase(Payable $payable): void
    {
        if (! $payable->purchase_id) {
            return;
        }

        $status = match ($payable->status) {
            'paid'      => 'paid',
            'partial'   => 'partial',
            'cancelled' => 'cancelled',
            default     => 'unpaid',
        };

        Purchase::where('id', $payable->purchase_id)
            ->update(['payment_status' => $status]);
    }
}
