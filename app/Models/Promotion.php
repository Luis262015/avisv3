<?php

namespace App\Models;

use Carbon\CarbonImmutable;
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

    /**
     * El nombre de la tabla va explícito: las migraciones la crearon como
     * `promotion_product`, mientras que la convención de Eloquent es alfabética
     * (`product_promotion`). Sin decirlo, cualquier lectura de la relación
     * reventaba, y con ella el listado de promociones y el punto de venta.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'promotion_category');
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

    /**
     * El día de hoy según el negocio, no según el servidor.
     *
     * La aplicación corre en UTC y la tienda en hora de Bolivia (UTC-4). Con
     * `now()` a secas, una promoción que termina hoy se apagaba a las 20:00
     * locales —cuando en UTC ya es mañana—, en plena tarde de ventas.
     */
    public static function hoyDelNegocio(): string
    {
        return CarbonImmutable::now(config('siat.timezone', 'America/La_Paz'))->toDateString();
    }

    /** Promotions currently within their date window and under the usage limit. */
    public function scopeCurrent(Builder $query): Builder
    {
        $today = self::hoyDelNegocio();

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

        // Se comparan fechas como texto: `starts_at`/`ends_at` son columnas `date`
        // y su cast las deja a medianoche UTC, que no es la medianoche del negocio.
        $hoy = self::hoyDelNegocio();

        if ($this->starts_at && $this->starts_at->toDateString() > $hoy) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->toDateString() < $hoy) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }
        return true;
    }

    /** Libera el uso al anularse la venta que la consumió; nunca baja de cero. */
    public function decrementUsage(int $veces = 1): void
    {
        $usados = (int) $this->used_count;

        if ($usados <= 0 || $veces <= 0) {
            return;
        }

        $this->decrement('used_count', min($veces, $usados));
    }

    /** `$veces` > 1 cuando una misma venta aplica el mismo combo varias veces. */
    public function incrementUsage(int $veces = 1): void
    {
        if ($veces > 0) {
            $this->increment('used_count', $veces);
        }
    }

    /** Cuánto queda del cupo, o null si no tiene límite. */
    public function usosDisponibles(): ?int
    {
        if ($this->usage_limit === null) {
            return null;
        }

        return max(0, (int) $this->usage_limit - (int) $this->used_count);
    }
}
