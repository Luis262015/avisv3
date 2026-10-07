<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decide, por el host, a qué empresa pertenece la petición.
 *
 * Va antes que la sesión: con el driver de base de datos las sesiones viven
 * en la base de cada empresa, y hay que estar ya en ella cuando se abren.
 */
final class IdentifyTenant
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenancy->enabled()) {
            return $next($request);
        }

        $host = strtolower($request->getHost());

        if (in_array($host, config('tenancy.central_domains'), true)) {
            // Con procesos de larga vida (Octane, pruebas) puede quedar puesta
            // la empresa de la petición anterior.
            $this->tenancy->end();

            return $next($request);
        }

        $tenant = $this->resolve($host);

        abort_if($tenant === null, 404, 'No existe ninguna empresa en esta dirección.');

        $this->tenancy->initialize($tenant);

        return $next($request);
    }

    private function resolve(string $host): ?Tenant
    {
        $suffix = '.'.config('tenancy.base_domain');

        $query = Tenant::query()->with('subscription.plan');

        if (str_ends_with($host, $suffix)) {
            $slug = substr($host, 0, -strlen($suffix));

            // Un subdominio de varios niveles no es de nadie.
            if ($slug !== '' && ! str_contains($slug, '.')) {
                return $query->where('slug', $slug)->first();
            }
        }

        return $query->where('domain', $host)->first();
    }
}
