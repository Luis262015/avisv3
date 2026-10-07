<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Central\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plan */
final class PlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'price_monthly' => (float) $this->price_monthly,
            'price_yearly' => (float) $this->price_yearly,
            'currency' => $this->currency,
            'trial_days' => $this->trial_days,
            'limits' => [
                'stores' => $this->max_stores,
                'users' => $this->max_users,
                'products' => $this->max_products,
                'invoices' => $this->max_invoices_month,
            ],
            'modules' => $this->modules ?? [],
            'is_free' => $this->isFree(),
            'is_public' => $this->is_public,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'subscriptions_count' => $this->whenCounted('subscriptions'),
        ];
    }
}
