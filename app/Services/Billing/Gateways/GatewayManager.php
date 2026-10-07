<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways;

use Illuminate\Contracts\Container\Container;

final class GatewayManager
{
    public function __construct(private readonly Container $container) {}

    /** La pasarela configurada, o null si solo hay cobro manual. */
    public function active(): ?PaymentGateway
    {
        return $this->get((string) config('billing.gateway'));
    }

    public function get(string $name): ?PaymentGateway
    {
        $class = config("billing.gateways.{$name}");

        return $class ? $this->container->make($class) : null;
    }

    public function activeName(): ?string
    {
        return $this->active() ? (string) config('billing.gateway') : null;
    }
}
