<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensual',
            self::Yearly => 'Anual',
        };
    }

    public function months(): int
    {
        return $this === self::Yearly ? 12 : 1;
    }
}
