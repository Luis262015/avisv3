<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Central\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('platform/plans/index', [
            'plans' => PlanResource::collection(Plan::withCount('subscriptions')->orderBy('sort_order')->get())->resolve(),
            'modules' => config('plans.modules'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/plans/form', [
            'plan' => null,
            'modules' => config('plans.modules'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::create($this->validated($request));

        return redirect()->route('platform.plans.index')->with('success', "Plan {$plan->name} creado.");
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('platform/plans/form', [
            'plan' => PlanResource::make($plan)->resolve(),
            'modules' => config('plans.modules'),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));

        return redirect()->route('platform.plans.index')->with('success', "Plan {$plan->name} guardado.");
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        // Un plan con empresas dentro no se borra: se desactiva y deja de ofrecerse.
        if ($plan->subscriptions()->exists() || $plan->payments()->exists()) {
            return back()->with('error', "El plan {$plan->name} tiene empresas o pagos asociados. Desactívalo para dejar de ofrecerlo.");
        }

        $plan->delete();

        return back()->with('success', "Plan {$plan->name} eliminado.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:60', Rule::unique(Plan::class, 'slug')->ignore($plan?->id)],
            'tagline' => ['nullable', 'string', 'max:191'],
            'price_monthly' => ['required', 'numeric', 'min:0', 'max:999999'],
            'price_yearly' => ['required', 'numeric', 'min:0', 'max:999999'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_stores' => ['nullable', 'integer', 'min:1'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_products' => ['nullable', 'integer', 'min:1'],
            'max_invoices_month' => ['nullable', 'integer', 'min:1'],
            'modules' => ['array'],
            'modules.*' => [Rule::in(array_keys(config('plans.modules')))],
            'is_public' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $data['modules'] = array_values($data['modules'] ?? []);

        return $data;
    }
}
