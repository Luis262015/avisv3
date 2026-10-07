<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Central\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionPayment extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'tenant_id', 'plan_id', 'billing_cycle', 'amount', 'currency', 'method',
        'status', 'reference', 'proof_path', 'notes', 'rejection_reason',
        'gateway', 'gateway_reference', 'gateway_payload', 'period_start',
        'period_end', 'paid_at', 'reviewed_by', 'reviewed_at',
    ];

    protected $hidden = ['gateway_payload'];

    protected $casts = [
        'billing_cycle' => BillingCycle::class,
        'method' => PaymentMethod::class,
        'status' => PaymentStatus::class,
        'amount' => 'decimal:2',
        'gateway_payload' => 'array',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'paid_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Pending);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Approved);
    }
}
