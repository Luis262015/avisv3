<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Central\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SubscriptionPayment */
final class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'billing_cycle_label' => $this->billing_cycle->label(),
            'method_label' => $this->method->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reference' => $this->reference,
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'has_proof' => $this->proof_path !== null,
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'plan' => $this->whenLoaded('plan', fn () => ['id' => $this->plan->id, 'name' => $this->plan->name]),
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'slug' => $this->tenant->slug,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
        ];
    }
}
