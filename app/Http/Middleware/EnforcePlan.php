<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Plan;
use App\Services\Billing\PlanUsage;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hace valer lo que el plan de la empresa incluye: qué módulos puede abrir y
 * cuántas tiendas, usuarios, productos y facturas puede tener.
 *
 * El mapa de qué ruta pertenece a qué módulo está en config/plans.php, así
 * que añadir una sección a un módulo no exige tocar las rutas.
 */
final class EnforcePlan
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly PlanUsage $usage,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plan = $this->tenancy->tenant()?->subscription?->plan;

        // Sin arrendamiento no hay plan que hacer valer: todo está incluido.
        if ($plan === null) {
            return $next($request);
        }

        if ($module = $this->moduleFor($request->path())) {
            if (! $plan->hasModule($module)) {
                return $this->deny($request, sprintf(
                    'Tu plan %s no incluye %s. Cambia de plan para usarlo.',
                    $plan->name,
                    config("plans.modules.{$module}.label"),
                ));
            }
        }

        // Por índice y no con notación de puntos: el nombre de la ruta ya los trae.
        if ($resource = config('plans.route_limits')[$request->route()?->getName()] ?? null) {
            if (! $this->usage->hasRoomFor($plan, $resource)) {
                return $this->deny($request, $this->limitMessage($plan, $resource));
            }
        }

        return $next($request);
    }

    private function moduleFor(string $path): ?string
    {
        foreach (config('plans.route_modules') as $prefix => $module) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $module;
            }
        }

        return null;
    }

    private function limitMessage(Plan $plan, string $resource): string
    {
        $limit = $plan->limitFor($resource);
        $meta = config("plans.limits.{$resource}");

        return $resource === 'invoices'
            ? sprintf('Tu plan %s permite %d facturas al mes y ya las emitiste. Cambia de plan para seguir facturando.', $plan->name, $limit)
            : sprintf('Tu plan %s permite hasta %d en «%s» y ya llegaste. Cambia de plan para agregar más.', $plan->name, $limit, $meta['label']);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(403, $message);
        }

        // Una página a la que el plan no llega lleva a donde se cambia de
        // plan; un alta rechazada devuelve al formulario sin perder lo escrito.
        return $request->isMethod('GET')
            ? redirect()->route('subscription.show')->with('error', $message)
            : back()->withInput()->with('error', $message);
    }
}
