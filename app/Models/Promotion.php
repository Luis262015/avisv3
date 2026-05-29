<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'combo_price',
        'scope',
        'min_purchase',
        'buy_qty',
        'get_qty',
        'starts_at',
        'ends_at',
        'usage_limit',
        'used_count',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'value'        => 'decimal:2',
        'combo_price'  => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'starts_at'    => 'date',
        'ends_at'      => 'date',
        'is_active'    => 'boolean',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function comboItems(): HasMany
    {
        return $this->hasMany(ComboItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCombos(Builder $query): Builder
    {
        return $query->where('type', 'combo');
    }

    public function scopeDiscounts(Builder $query): Builder
    {
        return $query->where('type', '!=', 'combo');
    }

    /** Promotions currently within their date window and under the usage limit. */
    public function scopeCurrent(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->active()
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->where(fn($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'));
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $today = now()->startOfDay();
        if ($this->starts_at && $this->starts_at->gt($today)) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->lt($today)) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }
        return true;
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
