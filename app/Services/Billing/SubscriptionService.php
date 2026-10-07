<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Central\Plan;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\Subscription;
use App\Models\Central\SubscriptionPayment;
use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El ciclo de vida de la renta: alta, cambios de plan, pagos y vencimientos.
 */
final class SubscriptionService
{
    public const PROOF_DISK = 'central';

    public function __construct(private readonly Tenancy $tenancy) {}

    /** Suscripción inicial de una empresa recién creada. */
    public function start(Tenant $tenant, Plan $plan, BillingCycle $cycle): Subscription
    {
        $attributes = match (true) {
            $plan->isFree() => [
                'status' => SubscriptionStatus::Active,
                'current_period_start' => now(),
                'current_period_end' => null,
            ],
            $plan->trial_days > 0 => [
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => now()->addDays($plan->trial_days),
            ],
            // De pago y sin prueba: no hay acceso hasta el primer pago.
            default => [
                'status' => SubscriptionStatus::PastDue,
                'current_period_end' => now(),
            ],
        };

        return $this->write($tenant, $plan, $cycle, $attributes);
    }

    /**
     * Cambio de plan decidido por la plataforma, sin pago de por medio.
     */
    public function changePlan(Tenant $tenant, Plan $plan, BillingCycle $cycle, ?Carbon $until = null): Subscription
    {
        return $this->write($tenant, $plan, $cycle, [
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => $plan->isFree() ? null : ($until ?? now()->addMonthsNoOverflow($cycle->months())),
        ]);
    }

    /** La empresa deja su plan de pago y se queda con el gratuito. */
    public function switchToFree(Tenant $tenant): Subscription
    {
        $free = Plan::offered()->get()->first(fn (Plan $plan) => $plan->isFree());

        if ($free === null) {
            throw new DomainException('No hay un plan gratuito disponible.');
        }

        return $this->changePlan($tenant, $free, BillingCycle::Monthly);
    }

    /** Mueve la fecha de vencimiento: una cortesía o una corrección. */
    public function extend(Subscription $subscription, Carbon $until): Subscription
    {
        $subscription->update(match ($subscription->status) {
            SubscriptionStatus::Trialing => ['trial_ends_at' => $until],
            default => ['status' => SubscriptionStatus::Active, 'current_period_end' => $until],
        });

        return $subscription;
    }

    /**
     * La empresa avisa que pagó. Queda por revisar: nada cambia en su acceso
     * hasta que la plataforma lo aprueba.
     */
    public function submitPayment(
        Tenant $tenant,
        Plan $plan,
        BillingCycle $cycle,
        PaymentMethod $method,
        ?string $reference = null,
        ?UploadedFile $proof = null,
        ?string $notes = null,
    ): SubscriptionPayment {
        if ($plan->isFree()) {
            throw new DomainException('El plan gratuito no requiere pago.');
        }

        return SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'amount' => $plan->priceFor($cycle),
            'currency' => $plan->currency,
            'method' => $method,
            'status' => PaymentStatus::Pending,
            'reference' => $reference,
            'proof_path' => $proof?->store("payments/{$tenant->id}", self::PROOF_DISK),
            'notes' => $notes,
        ]);
    }

    /**
     * Aprueba un pago y pone al día la suscripción.
     *
     * Si renueva el mismo plan antes de vencer, el periodo nuevo empieza
     * donde acaba el actual: pagar por adelantado no regala ni quita días.
     */
    public function approve(SubscriptionPayment $payment, ?PlatformAdmin $reviewer = null): SubscriptionPayment
    {
        $this->assertPending($payment);

        return DB::connection($this->tenancy->centralConnection())->transaction(function () use ($payment, $reviewer) {
            $tenant = $payment->tenant;
            $current = $tenant->subscription;

            $renews = $current !== null
                && $current->plan_id === $payment->plan_id
                && $current->status === SubscriptionStatus::Active
                && $current->current_period_end?->isFuture();

            $start = $renews ? $current->current_period_end->copy() : now();
            $end = $start->copy()->addMonthsNoOverflow($payment->billing_cycle->months());

            $payment->update([
                'status' => PaymentStatus::Approved,
                'paid_at' => $payment->paid_at ?? now(),
                'period_start' => $start,
                'period_end' => $end,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
            ]);

            $this->write($tenant, $payment->plan, $payment->billing_cycle, [
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $renews ? $current->current_period_start : $start,
                'current_period_end' => $end,
            ]);

            return $payment;
        });
    }

    public function reject(SubscriptionPayment $payment, string $reason, ?PlatformAdmin $reviewer = null): SubscriptionPayment
    {
        $this->assertPending($payment);

        $payment->update([
            'status' => PaymentStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
        ]);

        return $payment;
    }

    /** Pago que la plataforma registra por su cuenta y nace aprobado. */
    public function recordPayment(
        Tenant $tenant,
        Plan $plan,
        BillingCycle $cycle,
        PaymentMethod $method,
        float $amount,
        ?string $reference,
        ?PlatformAdmin $reviewer,
    ): SubscriptionPayment {
        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'currency' => $plan->currency,
            'method' => $method,
            'status' => PaymentStatus::Pending,
            'reference' => $reference,
        ]);

        return $this->approve($payment, $reviewer);
    }

    /**
     * Marca como vencidas las suscripciones que agotaron su plazo y la
     * tolerancia. El acceso ya se corta por fecha; esto deja el estado
     * guardado diciendo lo mismo, para los listados y los filtros.
     */
    public function markOverdue(): int
    {
        $limit = now()->subDays((int) config('billing.grace_days'));

        return Subscription::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('status', SubscriptionStatus::Trialing)->where('trial_ends_at', '<', $limit))
                ->orWhere(fn ($q) => $q->where('status', SubscriptionStatus::Active)->where('current_period_end', '<', $limit)))
            ->update(['status' => SubscriptionStatus::PastDue]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function write(Tenant $tenant, Plan $plan, BillingCycle $cycle, array $attributes): Subscription
    {
        $subscription = Subscription::updateOrCreate(['tenant_id' => $tenant->id], array_merge([
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'trial_ends_at' => null,
            'current_period_start' => null,
            'current_period_end' => null,
            'cancelled_at' => null,
        ], $attributes));

        $tenant->setRelation('subscription', $subscription);

        return $subscription;
    }

    private function assertPending(SubscriptionPayment $payment): void
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw new DomainException('Este pago ya fue revisado.');
        }
    }
}
