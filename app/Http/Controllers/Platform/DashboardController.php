<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\TenantResource;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\SubscriptionPayment;
use App\Models\Central\Tenant;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $subscriptions = Subscription::with('plan')->get();
        $current = $subscriptions->filter(fn (Subscription $s) => $s->isUsable());
        // Quien no tiene fecha de vencimiento no paga: es la empresa de la casa.
        $paying = $current->filter(fn (Subscription $s) => $s->status === SubscriptionStatus::Active && ! $s->plan->isFree() && $s->current_period_end !== null);

        return Inertia::render('platform/dashboard', [
            'stats' => [
                'tenants_active' => Tenant::where('status', TenantStatus::Active)->count(),
                'tenants_suspended' => Tenant::where('status', TenantStatus::Suspended)->count(),
                'paying' => $paying->count(),
                'trialing' => $current->where('status', SubscriptionStatus::Trialing)->count(),
                'overdue' => $subscriptions->reject(fn (Subscription $s) => $s->isUsable())->count(),
                // Ingreso mensual recurrente: lo anual se reparte en doce.
                'mrr' => round($paying->sum(fn (Subscription $s) => $s->billing_cycle === BillingCycle::Yearly
                    ? (float) $s->plan->price_yearly / 12
                    : (float) $s->plan->price_monthly), 2),
                'pending_payments' => SubscriptionPayment::pending()->count(),
                'signups_30d' => Tenant::where('created_at', '>=', now()->subDays(30))->count(),
            ],
            'revenue' => $this->revenueByMonth(),
            'planMix' => Plan::withCount('subscriptions')->orderBy('sort_order')->get()
                ->map(fn (Plan $plan) => ['name' => $plan->name, 'count' => $plan->subscriptions_count]),
            'pendingPayments' => PaymentResource::collection(
                SubscriptionPayment::pending()->with(['tenant', 'plan'])->oldest()->limit(6)->get(),
            )->resolve(),
            'expiring' => TenantResource::collection(
                Tenant::with('subscription.plan')
                    ->whereIn('id', $current->filter(fn (Subscription $s) => $s->isExpiringSoon())->pluck('tenant_id'))
                    ->get()
                    ->sortBy(fn (Tenant $t) => $t->subscription->daysLeft())
                    ->values(),
            )->resolve(),
            'recent' => TenantResource::collection(
                Tenant::with('subscription.plan')->latest()->limit(6)->get(),
            )->resolve(),
        ]);
    }

    /**
     * Cobros aprobados de los últimos seis meses, mes a mes.
     *
     * @return list<array{month: string, label: string, total: float}>
     */
    private function revenueByMonth(): array
    {
        $from = now()->startOfMonth()->subMonths(5);

        // Se agrupa en PHP: la función de fecha no es la misma en MySQL que en SQLite.
        $totals = SubscriptionPayment::approved()
            ->where('paid_at', '>=', $from)
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (SubscriptionPayment $p) => $p->paid_at->format('Y-m'))
            ->map(fn ($payments) => (float) $payments->sum('amount'));

        return collect(range(0, 5))
            ->map(fn (int $i) => $from->copy()->addMonths($i))
            ->map(fn (Carbon $month) => [
                'month' => $month->format('Y-m'),
                'label' => ucfirst($month->locale('es')->isoFormat('MMM')),
                'total' => $totals[$month->format('Y-m')] ?? 0.0,
            ])
            ->all();
    }
}
