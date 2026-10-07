<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Enums\BillingCycle;
use App\Enums\TenantStatus;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Store;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

/**
 * Da de alta una empresa de principio a fin: registro central, base de datos
 * propia, roles, su primer administrador, su primera tienda y su suscripción.
 */
final class TenantProvisioner
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly TenantDatabaseManager $databases,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * @param  array{name: string, slug: string, owner_name: string, owner_email: string, password: string, nit?: ?string, phone?: ?string, city?: ?string}  $data
     */
    public function provision(array $data, Plan $plan, BillingCycle $cycle): Tenant
    {
        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'database' => $this->databases->nameFor($data['slug']),
            'nit' => $data['nit'] ?? null,
            'owner_name' => $data['owner_name'],
            'owner_email' => $data['owner_email'],
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'status' => TenantStatus::Provisioning,
        ]);

        try {
            $this->databases->create($tenant);

            $this->tenancy->run($tenant, function () use ($data): void {
                $this->migrate();
                $this->seed($data);
            });

            $this->subscriptions->start($tenant, $plan, $cycle);
            $tenant->update(['status' => TenantStatus::Active]);
        } catch (Throwable $e) {
            // Un alta a medias dejaría el subdominio tomado y una base
            // huérfana: se deshace todo y el interesado puede reintentar.
            $this->tenancy->end();
            rescue(fn () => $this->databases->drop($tenant));
            $tenant->delete();

            throw $e;
        }

        return $tenant;
    }

    /** Aplica en la empresa en curso las migraciones que le falten. */
    public function migrate(): void
    {
        Artisan::call('migrate', [
            '--database' => Tenancy::CONNECTION,
            '--path' => database_path('migrations'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    /**
     * Enlace de un solo uso para que quien acaba de registrarse entre sin
     * volver a escribir su contraseña en otro dominio.
     */
    public function issueAccessUrl(Tenant $tenant): string
    {
        $token = Str::random(48);

        $tenant->forceFill([
            'access_token' => hash('sha256', $token),
            'access_token_expires_at' => now()->addMinutes(10),
        ])->save();

        return $tenant->url('bienvenida/'.$token);
    }

    /** @param  array<string, mixed>  $data */
    private function seed(array $data): void
    {
        (new RolesPermissionsSeeder)->seedRolesAndPermissions();

        $admin = new User([
            'name' => $data['owner_name'],
            'email' => $data['owner_email'],
            'password' => $data['password'],
        ]);
        $admin->email_verified_at = now();
        $admin->save();
        $admin->assignRole('admin');

        Store::create([
            'name' => 'Casa matriz',
            'phone' => $data['phone'] ?? null,
            'email' => $data['owner_email'],
            'rfc' => $data['nit'] ?? null,
            'is_active' => true,
        ]);
    }
}
