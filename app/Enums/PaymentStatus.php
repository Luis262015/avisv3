<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por revisar',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
        };
    }
}
