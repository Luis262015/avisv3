<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways;

final readonly class GatewayNotification
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public string $reference,
        public bool $paid,
        public array $payload = [],
    ) {}
}
