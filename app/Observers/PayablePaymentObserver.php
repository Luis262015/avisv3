<?php

namespace App\Observers;

use App\Models\PayablePayment;
use App\Models\Purchase;

class PayablePaymentObserver
{
    public function created(PayablePayment $payment): void
    {
        $this->syncPurchasePaymentStatus($payment);
    }

    private function syncPurchasePaymentStatus(PayablePayment $payment): void
    {
        $payable = $payment->payable;

        if (! $payable->purchase_id) {
            return;
        }

        $status = match ($payable->status) {
            'paid'    => 'paid',
            'partial' => 'partial',
            default   => 'unpaid',
        };

        Purchase::where('id', $payable->purchase_id)->update(['payment_status' => $status]);
    }
}
