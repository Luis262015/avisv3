<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\BillingCycle;
use App\Models\Central\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Plan extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'slug', 'name', 'tagline', 'price_monthly', 'price_yearly', 'currency',
        'trial_days', 'max_stores', 'max_users', 'max_products',
        'max_invoices_month', 'modules', 'is_public', 'is_featured',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'trial_days' => 'integer',
        'max_stores' => 'integer',
        'max_users' => 'integer',
        'max_products' => 'integer',
        'max_invoices_month' => 'integer',
        'modules' => 'array',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Los que se ofrecen en el sitio público. */
    public function scopeOffered(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_public', true)->orderBy('sort_order');
    }

    public function isFree(): bool
    {
        return (float) $this->price_monthly <= 0 && (float) $this->price_yearly <= 0;
    }

    public function priceFor(BillingCycle $cycle): float
    {
        return (float) ($cycle === BillingCycle::Yearly ? $this->price_yearly : $this->price_monthly);
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modules ?? [], true);
    }

    /** Cupo del plan para un recurso; null si no tiene tope. */
    public function limitFor(string $resource): ?int
    {
        $column = config("plans.limits.{$resource}.column");

        return $column ? $this->{$column} : null;
    }
}
