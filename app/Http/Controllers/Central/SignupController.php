<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\BillingCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\SignupRequest;
use App\Http\Resources\PlanResource;
use App\Models\Central\Plan;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

final class SignupController extends Controller
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function create(Request $request): Response
    {
        abort_unless($this->tenancy->enabled(), 404);

        $plans = Plan::offered()->get();

        return Inertia::render('central/signup', [
            'plans' => PlanResource::collection($plans)->resolve(),
            'selectedPlan' => $plans->firstWhere('slug', $request->query('plan'))?->slug ?? $plans->first()?->slug,
            'selectedCycle' => BillingCycle::tryFrom((string) $request->query('ciclo'))?->value ?? BillingCycle::Monthly->value,
            'baseDomain' => config('tenancy.base_domain'),
        ]);
    }

    public function store(SignupRequest $request, TenantProvisioner $provisioner): HttpResponse
    {
        abort_unless($this->tenancy->enabled(), 404);

        // Crear la base y sus tablas tarda bastante más que una petición común.
        set_time_limit(180);

        $plan = Plan::where('slug', $request->validated('plan'))->firstOrFail();

        try {
            $tenant = $provisioner->provision(
                $request->safe()->except(['plan', 'cycle', 'terms', 'password_confirmation']),
                $plan,
                BillingCycle::from($request->validated('cycle')),
            );
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'name' => 'No pudimos preparar tu espacio. Vuelve a intentarlo en un momento; si se repite, escríbenos.',
            ]);
        }

        // Otro host: tiene que ser una navegación completa, no una visita de Inertia.
        return Inertia::location($provisioner->issueAccessUrl($tenant));
    }
}
