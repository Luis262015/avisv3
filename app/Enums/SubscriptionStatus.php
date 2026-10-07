<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'En prueba',
            self::Active => 'Al día',
            self::PastDue => 'Vencida',
            self::Cancelled => 'Cancelada',
        };
    }
}
