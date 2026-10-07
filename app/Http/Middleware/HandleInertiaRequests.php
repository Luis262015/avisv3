<?php

namespace App\Http\Middleware;

use App\Http\Resources\SubscriptionResource;
use App\Models\CashShift;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // El panel de la plataforma no tiene usuarios de empresa, ni roles,
        // ni caja: comparte solo lo suyo.
        if ($request->is('plataforma', 'plataforma/*')) {
            return array_merge(parent::share($request), [
                'name'          => config('app.name'),
                'platformAdmin' => fn () => $request->user('platform')?->only(['id', 'name', 'email']),
                'flash'         => $this->flash($request),
            ]);
        }

        return array_merge(parent::share($request), [
            'name'  => config('app.name'),
            'auth'  => [
                'user'        => $request->user(),
                'roles'       => $request->user()?->getRoleNames() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name') ?? [],
            ],
            'flash' => $this->flash($request),
            'tenant' => fn () => $this->tenant(),
            'activeCashShift' => function () use ($request) {
                if (! $request->user()) {
                    return null;
                }
                $shift = CashShift::where('user_id', $request->user()->id)
                    ->where('status', 'open')
                    ->with('cashRegister:id,name')
                    ->select(['id', 'cash_register_id'])
                    ->first();

                return $shift ? [
                    'id'            => $shift->id,
                    'register_name' => $shift->cashRegister->name,
                ] : null;
            },
        ]);
    }

    /** @return array<string, mixed> */
    private function flash(Request $request): array
    {
        return [
            'success'       => $request->session()->get('success'),
            'error'         => $request->session()->get('error'),
            'print_receipt' => $request->session()->get('print_receipt'),
        ];
    }

    /**
     * La empresa en curso y su plan; null cuando el sistema corre para una
     * sola empresa, sin arrendamiento. El menú usa los módulos para no
     * ofrecer lo que el plan no incluye.
     *
     * @return array<string, mixed>|null
     */
    private function tenant(): ?array
    {
        $tenant = app(Tenancy::class)->tenant();

        if ($tenant === null) {
            return null;
        }

        $subscription = $tenant->subscription?->loadMissing('plan');

        return [
            'name'         => $tenant->name,
            'slug'         => $tenant->slug,
            'suspended'    => $tenant->status === \App\Enums\TenantStatus::Suspended,
            'modules'      => $subscription?->plan->modules ?? [],
            'subscription' => $subscription ? SubscriptionResource::make($subscription)->resolve() : null,
        ];
    }
}
