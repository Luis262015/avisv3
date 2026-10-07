<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sin renta al día no se opera, pero tampoco se echa a nadie a la calle: se
 * le lleva a la página donde puede ver qué pasa y ponerse al día.
 */
final class EnsureSubscriptionUsable
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenancy->tenant();

        if ($tenant === null) {
            return $next($request);
        }

        $blocked = $tenant->status === TenantStatus::Suspended
            || ! ($tenant->subscription?->isUsable() ?? false);

        if (! $blocked) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(402, 'La suscripción de la empresa no está al día.');
        }

        return redirect()->route('subscription.show');
    }
}
