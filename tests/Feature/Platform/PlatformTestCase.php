<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\BillingCycle;
use App\Models\Central\Plan;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantProvisioner;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Base de las pruebas del arrendamiento: lo enciende, con el sitio en
 * `avis.test` y cada empresa en `{slug}.avis.test`, cada una en su archivo
 * SQLite aparte.
 */
abstract class PlatformTestCase extends TestCase
{
    use RefreshDatabase;

    protected const CENTRAL = 'http://avis.test';

    private string $tenantsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantsPath = storage_path('framework/testing/tenants-'.uniqid());

        config([
            'tenancy.enabled' => true,
            'tenancy.base_domain' => 'avis.test',
            'tenancy.central_domains' => ['avis.test'],
            'tenancy.sqlite_path' => $this->tenantsPath,
        ]);

        $this->seed(PlatformSeeder::class);
    }

    protected function tearDown(): void
    {
        app(Tenancy::class)->end();
        // En Windows el archivo no se puede borrar con la conexión abierta.
        DB::purge(Tenancy::CONNECTION);
        File::deleteDirectory($this->tenantsPath);

        parent::tearDown();
    }

    protected function plan(string $slug): Plan
    {
        return Plan::where('slug', $slug)->firstOrFail();
    }

    protected function provision(string $slug = 'acme', string $plan = 'negocio', array $overrides = []): Tenant
    {
        return app(TenantProvisioner::class)->provision(array_merge([
            'name' => ucfirst($slug).' SRL',
            'slug' => $slug,
            'owner_name' => 'Dueña de '.ucfirst($slug),
            'owner_email' => "admin@{$slug}.test",
            'password' => 'secreto-seguro-123',
        ], $overrides), $this->plan($plan), BillingCycle::Monthly);
    }

    protected function tenantUrl(Tenant|string $tenant, string $path = '/'): string
    {
        $slug = $tenant instanceof Tenant ? $tenant->slug : $tenant;

        return "http://{$slug}.avis.test/".ltrim($path, '/');
    }

    /** Ejecuta algo dentro de la base de una empresa. */
    protected function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return app(Tenancy::class)->run($tenant, $callback);
    }

    /** El administrador con el que nació la empresa. */
    protected function ownerOf(Tenant $tenant): User
    {
        return $this->inTenant($tenant, fn () => User::where('email', $tenant->owner_email)->firstOrFail());
    }

    protected function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'Plataforma', 'email' => 'root@avis.test', 'password' => 'secreto-seguro-123']);
    }
}
