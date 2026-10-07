<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas del sistema de cada empresa: solo existen dentro de una.
 */
final class EnsureTenantContext
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenancy->enabled()) {
            return $next($request);
        }

        $tenant = $this->tenancy->tenant();

        if ($tenant === null) {
            // En el dominio central no hay a qué empresa entrar: se pregunta.
            return $request->isMethod('GET') ? redirect()->route('central.access') : abort(404);
        }

        abort_if($tenant->status === TenantStatus::Provisioning, 503, 'Tu espacio todavía se está preparando. Vuelve a intentar en un momento.');
        abort_if($tenant->status === TenantStatus::Cancelled, 410, 'Esta empresa fue dada de baja.');

        return $next($request);
    }
}
