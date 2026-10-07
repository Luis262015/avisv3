<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\TenantStatus;
use App\Models\Central\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Tenant extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'name', 'slug', 'domain', 'database', 'is_legacy', 'nit', 'owner_name',
        'owner_email', 'phone', 'city', 'status', 'suspension_reason', 'notes',
    ];

    protected $hidden = ['access_token', 'access_token_expires_at'];

    protected $casts = [
        'status' => TenantStatus::class,
        'is_legacy' => 'boolean',
        'access_token_expires_at' => 'datetime',
    ];

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('slug', 'like', $like)
            ->orWhere('owner_email', 'like', $like)
            ->orWhere('nit', 'like', $like));
    }

    public function host(): string
    {
        return $this->domain ?: $this->slug.'.'.config('tenancy.base_domain');
    }

    /**
     * Dirección absoluta dentro del espacio de la empresa.
     *
     * Esquema y puerto se copian de la petición en curso: en desarrollo el
     * servidor no escucha en el 80 y APP_URL no lo sabe.
     */
    public function url(string $path = '/'): string
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        $scheme = $request?->getScheme() ?? (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http');
        $port = $request?->getPort() ?? parse_url((string) config('app.url'), PHP_URL_PORT);
        $standard = $port === null || ($scheme === 'http' && (int) $port === 80) || ($scheme === 'https' && (int) $port === 443);

        return $scheme.'://'.$this->host().($standard ? '' : ':'.$port).'/'.ltrim($path, '/');
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }
}
