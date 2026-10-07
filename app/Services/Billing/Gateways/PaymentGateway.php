<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways;

use App\Models\Central\SubscriptionPayment;
use Illuminate\Http\Request;

/**
 * Lo que debe saber hacer una pasarela para cobrar la renta en línea.
 *
 * El cobro manual por QR o transferencia no pasa por aquí. Para conectar un
 * proveedor: implementa esta interfaz, regístrala en config/billing.php y
 * pon su clave en BILLING_GATEWAY. El resto del flujo —crear el pago, recibir
 * el aviso, aprobarlo y renovar la suscripción— ya está armado.
 */
interface PaymentGateway
{
    /** Nombre que ve quien paga, p. ej. «Tarjeta o QR en línea». */
    public function label(): string;

    /**
     * Abre el cobro en el proveedor y devuelve adónde enviar a quien paga.
     * Debe dejar en `$payment->gateway_reference` el identificador con el que
     * el proveedor avisará después.
     */
    public function checkoutUrl(SubscriptionPayment $payment, string $returnUrl): string;

    /**
     * Interpreta el aviso del proveedor. Debe verificar su firma y devolver
     * null si no es auténtico o no corresponde a un cobro.
     */
    public function parseNotification(Request $request): ?GatewayNotification;
}
