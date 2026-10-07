<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Central\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
final class TenantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'host' => $this->host(),
            'url' => $this->url('login'),
            'database' => $this->database,
            'is_legacy' => $this->is_legacy,
            'nit' => $this->nit,
            'owner_name' => $this->owner_name,
            'owner_email' => $this->owner_email,
            'phone' => $this->phone,
            'city' => $this->city,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'suspension_reason' => $this->suspension_reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'subscription' => SubscriptionResource::make($this->whenLoaded('subscription')),
        ];
    }
}
