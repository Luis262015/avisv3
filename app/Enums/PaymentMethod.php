<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Qr = 'qr';
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Gateway = 'gateway';

    public function label(): string
    {
        return match ($this) {
            self::Qr => 'QR',
            self::Transfer => 'Transferencia',
            self::Cash => 'Efectivo',
            self::Gateway => 'Pago en línea',
        };
    }
}
