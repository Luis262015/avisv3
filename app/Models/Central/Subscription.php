<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Central\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

final class Subscription extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'tenant_id', 'plan_id', 'status', 'billing_cycle', 'trial_ends_at',
        'current_period_start', 'current_period_end', 'cancelled_at',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'billing_cycle' => BillingCycle::class,
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** Fecha en que deja de estar cubierta; null si no vence. */
    public function endsAt(): ?Carbon
    {
        return $this->status === SubscriptionStatus::Trialing
            ? $this->trial_ends_at
            : $this->current_period_end;
    }

    /**
     * ¿Da acceso al sistema ahora mismo?
     *
     * Se decide por fechas y no por el estado guardado: así el acceso se
     * corta a tiempo aunque la tarea programada que marca los vencidos no
     * haya corrido.
     */
    public function isUsable(): bool
    {
        if (! in_array($this->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
            return false;
        }

        $end = $this->endsAt();

        return $end === null || $end->copy()->addDays((int) config('billing.grace_days'))->isFuture();
    }

    /** Días que faltan para el vencimiento; negativo si ya pasó, null si no vence. */
    public function daysLeft(): ?int
    {
        $end = $this->endsAt();

        return $end === null ? null : (int) floor(now()->diffInDays($end, false));
    }

    public function isExpiringSoon(): bool
    {
        $days = $this->daysLeft();

        return $days !== null && $days <= (int) config('billing.warning_days');
    }
}
