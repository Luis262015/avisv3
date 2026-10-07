<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Brand;
use App\Models\Central\Tenant;
use App\Models\Store;
use App\Models\User;
use App\Tenancy\TenantDatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Alta de empresas y aislamiento entre ellas.
 */
class TenantProvisioningTest extends PlatformTestCase
{
    private function signupData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ferretería El Tornillo',
            'slug' => 'el-tornillo',
            'nit' => '1023456789',
            'owner_name' => 'María Quispe',
            'owner_email' => 'maria@eltornillo.bo',
            'phone' => '70012345',
            'city' => 'La Paz',
            'password' => 'secreto-seguro-123',
            'password_confirmation' => 'secreto-seguro-123',
            'plan' => 'negocio',
            'cycle' => 'monthly',
            'terms' => true,
        ], $overrides);
    }

    public function test_el_alta_crea_la_empresa_con_su_base_su_administrador_y_su_prueba(): void
    {
        $response = $this->post(self::CENTRAL.'/registro', $this->signupData());

        $tenant = Tenant::where('slug', 'el-tornillo')->firstOrFail();

        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertSame(SubscriptionStatus::Trialing, $tenant->subscription->status);
        $this->assertSame('negocio', $tenant->subscription->plan->slug);
        $this->assertEqualsWithDelta(14, $tenant->subscription->daysLeft(), 1);

        // Sale del dominio central hacia el de la empresa, con entrada de un uso.
        $response->assertRedirect();
        $this->assertStringStartsWith('http://el-tornillo.avis.test/bienvenida/', $response->headers->get('Location'));

        $this->inTenant($tenant, function (): void {
            $admin = User::where('email', 'maria@eltornillo.bo')->firstOrFail();

            $this->assertTrue($admin->hasRole('admin'));
            $this->assertSame(1, User::count(), 'No debe colarse la cuenta admin@admin.com de la instalación de una sola empresa.');
            $this->assertSame('Casa matriz', Store::sole()->name);
        });
    }

    public function test_el_plan_gratuito_queda_activo_y_sin_vencimiento(): void
    {
        $this->post(self::CENTRAL.'/registro', $this->signupData(['plan' => 'gratis']));

        $subscription = Tenant::where('slug', 'el-tornillo')->firstOrFail()->subscription;

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->endsAt());
        $this->assertTrue($subscription->isUsable());
    }

    public function test_el_enlace_de_bienvenida_entra_una_sola_vez(): void
    {
        $location = $this->post(self::CENTRAL.'/registro', $this->signupData())->headers->get('Location');

        $this->get($location)->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        auth()->logout();

        $this->get($location)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_rechaza_direcciones_reservadas_repetidas_o_mal_escritas(): void
    {
        $this->provision('acme');

        foreach (['acme', 'www', 'Con Espacios', '-guion', 'ab'] as $slug) {
            $this->post(self::CENTRAL.'/registro', $this->signupData(['slug' => $slug]))
                ->assertSessionHasErrors('slug');
        }

        $this->assertSame(1, Tenant::count());
    }

    public function test_los_datos_de_una_empresa_no_se_ven_desde_otra(): void
    {
        $acme = $this->provision('acme');
        $beta = $this->provision('beta');

        $this->inTenant($acme, fn () => Brand::create(['name' => 'Marca de Acme']));

        $this->assertSame(0, $this->inTenant($beta, fn () => Brand::count()));
        $this->assertSame(1, $this->inTenant($acme, fn () => Brand::count()));
    }

    public function test_la_base_de_la_empresa_no_recibe_las_tablas_de_la_plataforma(): void
    {
        $acme = $this->provision('acme');

        $this->inTenant($acme, function (): void {
            $this->assertTrue(Schema::hasTable('sales'));
            $this->assertFalse(Schema::hasTable('tenants'));
            $this->assertFalse(Schema::hasTable('plans'));
        });
    }

    public function test_el_sistema_no_se_sirve_en_el_dominio_central_ni_el_panel_en_el_de_una_empresa(): void
    {
        $acme = $this->provision('acme');

        $this->get(self::CENTRAL.'/admin/products')->assertRedirect(route('central.access'));
        $this->get(self::CENTRAL.'/login')->assertRedirect(route('central.access'));

        $this->get($this->tenantUrl($acme, 'plataforma/ingresar'))->assertNotFound();
        $this->get($this->tenantUrl($acme, 'registro'))->assertNotFound();
        $this->get($this->tenantUrl($acme, 'login'))->assertOk();
    }

    public function test_un_subdominio_sin_empresa_no_existe(): void
    {
        $this->get($this->tenantUrl('nadie', 'login'))->assertNotFound();
    }

    public function test_ingresar_lleva_a_la_direccion_de_la_empresa(): void
    {
        $this->provision('acme');

        $this->post(self::CENTRAL.'/ingresar', ['slug' => 'https://ACME.avis.test/login'])
            ->assertRedirect('http://acme.avis.test/login');

        $this->post(self::CENTRAL.'/ingresar', ['slug' => 'no-existe'])->assertSessionHasErrors('slug');
    }

    public function test_el_registro_por_defecto_ya_no_existe(): void
    {
        $acme = $this->provision('acme');

        $this->get($this->tenantUrl($acme, 'register'))->assertNotFound();
        $this->post($this->tenantUrl($acme, 'register'), [
            'name' => 'Intruso', 'email' => 'x@x.test', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertSame(1, $this->inTenant($acme, fn () => User::count()));
    }

    public function test_si_el_alta_falla_no_queda_nada_a_medias(): void
    {
        // Sin contraseña el administrador no se puede guardar: falla cuando
        // la base de la empresa ya está creada y migrada.
        try {
            $this->provision('rota', 'gratis', ['password' => null]);
            $this->fail('El alta debió fallar.');
        } catch (QueryException) {
            // esperado
        }

        $this->assertSame(0, Tenant::count());
        $this->assertFalse(app(TenantDatabaseManager::class)->exists('avis_rota'));

        // Y la dirección queda libre para reintentar.
        $this->assertSame(TenantStatus::Active, $this->provision('rota', 'gratis')->status);
    }
}
