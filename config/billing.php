<?php

return [

    // Días de tolerancia tras el vencimiento antes de bloquear el acceso.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 3),

    // Con cuántos días de anticipación se avisa del vencimiento.
    'warning_days' => (int) env('BILLING_WARNING_DAYS', 7),

    /*
    | Pasarela de pago en línea. Sin valor, solo se cobra por QR o
    | transferencia con comprobante. Para conectar una, implementa
    | App\Services\Billing\Gateways\PaymentGateway y regístrala abajo.
    */
    'gateway' => env('BILLING_GATEWAY'),

    'gateways' => [
        // 'mi-pasarela' => App\Services\Billing\Gateways\MiPasarelaGateway::class,
    ],
];
