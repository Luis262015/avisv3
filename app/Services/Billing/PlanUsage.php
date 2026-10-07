<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Central\Plan;
use App\Models\Product;
use App\Models\SiatInvoice;
use App\Models\Store;
use App\Models\User;

/**
 * Cuánto lleva consumido la empresa en curso de cada cupo de su plan.
 */
final class PlanUsage
{
    public function count(string $resource): int
    {
        return match ($resource) {
            'stores' => Store::count(),
            'users' => User::count(),
            'products' => Product::count(),
            'invoices' => SiatInvoice::where('created_at', '>=', now()->startOfMonth())->count(),
            default => 0,
        };
    }

    public function hasRoomFor(Plan $plan, string $resource): bool
    {
        $limit = $plan->limitFor($resource);

        return $limit === null || $this->count($resource) < $limit;
    }

    /** @return list<array{key: string, label: string, used: int, limit: ?int}> */
    public function summary(Plan $plan): array
    {
        return collect(config('plans.limits'))
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'used' => $this->count($key),
                'limit' => $plan->limitFor($key),
            ])
            ->values()
            ->all();
    }
}
