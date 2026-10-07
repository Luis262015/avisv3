<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Central\SubscriptionPayment;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aviso de la pasarela: el cobro en línea se confirmó o falló.
 */
final class GatewayWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, GatewayManager $gateways, SubscriptionService $subscriptions): JsonResponse
    {
        $driver = $gateways->get($gateway);
        abort_if($driver === null, 404);

        $notification = $driver->parseNotification($request);
        abort_if($notification === null, 400);

        $payment = SubscriptionPayment::where('gateway', $gateway)
            ->where('gateway_reference', $notification->reference)
            ->first();

        // Los proveedores reintentan el mismo aviso: lo ya resuelto se
        // contesta con éxito y no se toca.
        if ($payment === null || $payment->status !== PaymentStatus::Pending) {
            return response()->json(['ok' => true]);
        }

        $payment->update(['gateway_payload' => $notification->payload]);

        $notification->paid
            ? $subscriptions->approve($payment)
            : $subscriptions->reject($payment, 'El proveedor de pagos no confirmó el cobro.');

        return response()->json(['ok' => true]);
    }
}
