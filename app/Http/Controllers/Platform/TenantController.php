<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\SignupRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\TenantResource;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Billing\PlanUsage;
use App\Services\Billing\SubscriptionService;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantDatabaseManager;
use App\Tenancy\TenantProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class TenantController extends Controller
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'status', 'plan']);

        $tenants = Tenant::query()
            ->with('subscription.plan')
            ->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['plan'] ?? null, fn ($q, $plan) => $q->whereHas('subscription', fn ($s) => $s->where('plan_id', $plan)))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('platform/tenants/index', [
            'tenants' => TenantResource::collection($tenants),
            'filters' => $filters,
            'plans' => Plan::orderBy('sort_order')->get(['id', 'name']),
            'statuses' => collect(TenantStatus::cases())->map(fn (TenantStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/tenants/create', [
            'plans' => PlanResource::collection(Plan::active()->orderBy('sort_order')->get())->resolve(),
            'baseDomain' => config('tenancy.base_domain'),
            'tenancyEnabled' => $this->tenancy->enabled(),
        ]);
    }

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $request->merge(['slug' => Str::lower(trim((string) $request->input('slug')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => SignupRequest::slugRules(),
            'nit' => ['nullable', 'string', 'regex:/^[0-9]{5,15}$/'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'string', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
            'password' => ['required', Password::defaults()],
            'plan_id' => ['required', Rule::exists(Plan::class, 'id')],
            'cycle' => ['required', Rule::enum(BillingCycle::class)],
        ]);

        set_time_limit(180);

        try {
            $tenant = $provisioner->provision(
                collect($data)->except(['plan_id', 'cycle'])->all(),
                Plan::findOrFail($data['plan_id']),
                BillingCycle::from($data['cycle']),
            );
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No se pudo crear la empresa: '.$e->getMessage());
        }

        return redirect()->route('platform.tenants.show', $tenant)->with('success', "{$tenant->name} ya tiene su espacio.");
    }

    public function show(Tenant $tenant, PlanUsage $usage): Response
    {
        $tenant->load('subscription.plan');
        $plan = $tenant->subscription?->plan;

        return Inertia::render('platform/tenants/show', [
            'tenant' => TenantResource::make($tenant)->resolve(),
            // Una base caída o a medio migrar no debe tumbar la ficha.
            'usage' => $plan && $tenant->status !== TenantStatus::Provisioning
                ? rescue(fn () => $this->tenancy->run($tenant, fn () => $usage->summary($plan)), [], report: false)
                : [],
            'payments' => PaymentResource::collection(
                $tenant->payments()->with(['plan', 'reviewer'])->latest()->limit(20)->get(),
            )->resolve(),
            'plans' => PlanResource::collection(Plan::active()->orderBy('sort_order')->get())->resolve(),
            'methods' => collect([PaymentMethod::Qr, PaymentMethod::Transfer, PaymentMethod::Cash])
                ->map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()]),
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'nit' => ['nullable', 'string', 'regex:/^[0-9]{5,15}$/'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'string', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
            'domain' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i', Rule::unique(Tenant::class, 'domain')->ignore($tenant->id)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenant->update($data);

        return back()->with('success', 'Datos de la empresa guardados.');
    }

    public function suspend(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:191']]);

        $tenant->update(['status' => TenantStatus::Suspended, 'suspension_reason' => $data['reason']]);

        return back()->with('success', "{$tenant->name} quedó suspendida.");
    }

    public function reactivate(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => TenantStatus::Active, 'suspension_reason' => null]);

        return back()->with('success', "{$tenant->name} vuelve a estar activa.");
    }

    public function changePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists(Plan::class, 'id')],
            'cycle' => ['required', Rule::enum(BillingCycle::class)],
            'until' => ['nullable', 'date', 'after:today', 'before:2038-01-01'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        $this->subscriptions->changePlan(
            $tenant,
            $plan,
            BillingCycle::from($data['cycle']),
            isset($data['until']) ? Carbon::parse($data['until'])->endOfDay() : null,
        );

        return back()->with('success', "{$tenant->name} pasó al plan {$plan->name}.");
    }

    public function extend(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['until' => ['required', 'date', 'after:today', 'before:2038-01-01']]);

        abort_if($tenant->subscription === null, 404);

        $this->subscriptions->extend($tenant->subscription, Carbon::parse($data['until'])->endOfDay());

        return back()->with('success', 'Vencimiento actualizado.');
    }

    /**
     * Baja definitiva: se elimina la base de datos de la empresa. Exige
     * escribir su dirección para que no ocurra por un clic de más.
     */
    public function destroy(Request $request, Tenant $tenant, TenantDatabaseManager $databases): RedirectResponse
    {
        $request->validate(['confirm' => ['required', Rule::in([$tenant->slug])]], [
            'confirm.in' => 'Escribe la dirección de la empresa tal cual para confirmar.',
        ]);

        if ($tenant->is_legacy) {
            return back()->with('error', 'Esta empresa comparte la base de la plataforma y no se puede eliminar desde aquí.');
        }

        $name = $tenant->name;
        $databases->drop($tenant);
        $tenant->delete();

        return redirect()->route('platform.tenants.index')->with('success', "{$name} y sus datos fueron eliminados.");
    }
}
