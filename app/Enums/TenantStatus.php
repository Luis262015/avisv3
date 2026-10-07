<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Creándose',
            self::Active => 'Activa',
            self::Suspended => 'Suspendida',
            self::Cancelled => 'Dada de baja',
        };
    }
}
