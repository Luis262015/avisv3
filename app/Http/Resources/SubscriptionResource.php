<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\SubscriptionStatus;
use App\Models\Central\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
final class SubscriptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $usable = $this->isUsable();

        return [
            'id' => $this->id,
            // Lo que cuenta es si da acceso hoy, no lo último que se guardó.
            'status' => $usable ? $this->status->value : SubscriptionStatus::PastDue->value,
            'status_label' => $usable ? $this->status->label() : SubscriptionStatus::PastDue->label(),
            'billing_cycle' => $this->billing_cycle->value,
            'billing_cycle_label' => $this->billing_cycle->label(),
            'ends_at' => $this->endsAt()?->toIso8601String(),
            'days_left' => $this->daysLeft(),
            'is_usable' => $usable,
            'is_expiring_soon' => $usable && $this->isExpiringSoon(),
            'plan' => PlanResource::make($this->whenLoaded('plan')),
        ];
    }
}
