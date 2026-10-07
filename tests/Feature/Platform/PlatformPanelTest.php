<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Central\Plan;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\PlatformSetting;
use App\Models\Central\SubscriptionPayment;
use App\Models\Central\Tenant;
use App\Services\Billing\SubscriptionService;
use App\Tenancy\TenantDatabaseManager;

/**
 * El panel desde el que se administra la renta del sistema.
 */
class PlatformPanelTest extends PlatformTestCase
{
    private function panel(string $path = ''): string
    {
        return self::CENTRAL.'/plataforma'.($path ? '/'.$path : '');
    }

    public function test_sin_sesion_el_panel_manda_a_su_propio_acceso(): void
    {
        $this->get($this->panel())->assertRedirect(route('platform.login'));
        $this->get($this->panel('empresas'))->assertRedirect(route('platform.login'));
    }

    public function test_el_usuario_de_una_empresa_no_entra_al_panel(): void
    {
        $acme = $this->provision('acme');

        $this->actingAs($this->ownerOf($acme))->get($this->panel())->assertRedirect(route('platform.login'));
    }

    public function test_el_administrador_entra_con_sus_credenciales(): void
    {
        $this->platformAdmin();

        $this->post($this->panel('ingresar'), ['email' => 'root@avis.test', 'password' => 'mala'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('platform');

        $this->post($this->panel('ingresar'), ['email' => 'root@avis.test', 'password' => 'secreto-seguro-123'])
            ->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticated('platform');
    }

    public function test_todas_las_pantallas_del_panel_cargan(): void
    {
        $acme = $this->provision('acme');
        $this->actingAs($this->platformAdmin(), 'platform');

        foreach (['', 'empresas', 'empresas/create', "empresas/{$acme->id}", 'planes', 'planes/create', 'planes/'.$this->plan('negocio')->id.'/edit', 'pagos', 'ajustes', 'administradores'] as $path) {
            $this->get($this->panel($path))->assertOk();
        }
    }

    public function test_el_resumen_cuenta_empresas_e_ingreso_recurrente(): void
    {
        $this->provision('acme');
        $beta = $this->provision('beta');
        $this->provision('gamma', 'gratis');

        $this->actingAs($admin = $this->platformAdmin(), 'platform')->post($this->panel('pagos'), [
            'tenant_id' => $beta->id,
            'plan_id' => $this->plan('negocio')->id,
            'cycle' => 'monthly',
            'method' => 'cash',
            'amount' => 199,
        ])->assertSessionHas('success');

        $this->get($this->panel())->assertInertia(fn ($page) => $page
            ->where('stats.tenants_active', 3)
            ->where('stats.paying', 1)
            ->where('stats.trialing', 1)
            ->where('stats.mrr', 199)
            ->where('stats.pending_payments', 0));

        $this->assertSame($admin->id, SubscriptionPayment::sole()->reviewed_by);
    }

    public function test_crea_edita_y_protege_los_planes(): void
    {
        $this->provision('acme');
        $this->actingAs($this->platformAdmin(), 'platform');

        $this->post($this->panel('planes'), [
            'name' => 'Plan Mayorista',
            'price_monthly' => 320, 'price_yearly' => 3200, 'trial_days' => 7,
            'max_stores' => 5, 'max_users' => null, 'max_products' => null, 'max_invoices_month' => 4000,
            'modules' => ['facturacion', 'finanzas'],
            'is_public' => true, 'is_featured' => false, 'is_active' => true, 'sort_order' => 4,
        ])->assertRedirect(route('platform.plans.index'));

        $plan = Plan::where('slug', 'plan-mayorista')->firstOrFail();
        $this->assertSame(['facturacion', 'finanzas'], $plan->modules);
        $this->assertNull($plan->max_users);

        $this->post($this->panel('planes'), ['name' => 'Malo', 'price_monthly' => 1, 'price_yearly' => 1, 'trial_days' => 0, 'sort_order' => 1, 'modules' => ['inventado']])
            ->assertSessionHasErrors('modules.0');

        // Con empresas dentro no se elimina; vacío sí.
        $this->delete($this->panel('planes/'.$this->plan('negocio')->id))->assertSessionHas('error');
        $this->assertNotNull($this->plan('negocio'));

        $this->delete($this->panel("planes/{$plan->id}"))->assertSessionHas('success');
        $this->assertNull(Plan::find($plan->id));
    }

    public function test_suspende_reactiva_y_cambia_de_plan_a_una_empresa(): void
    {
        $acme = $this->provision('acme');
        $this->actingAs($this->platformAdmin(), 'platform');

        $this->post($this->panel("empresas/{$acme->id}/suspender"), ['reason' => 'Pago observado'])->assertSessionHas('success');
        $this->assertSame(TenantStatus::Suspended, $acme->fresh()->status);

        $this->post($this->panel("empresas/{$acme->id}/reactivar"))->assertSessionHas('success');
        $this->assertSame(TenantStatus::Active, $acme->fresh()->status);

        $this->post($this->panel("empresas/{$acme->id}/plan"), ['plan_id' => $this->plan('empresa')->id, 'cycle' => 'yearly'])
            ->assertSessionHas('success');

        $subscription = $acme->fresh()->subscription;
        $this->assertSame('empresa', $subscription->plan->slug);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_da_de_alta_una_empresa_desde_el_panel(): void
    {
        $this->actingAs($this->platformAdmin(), 'platform')->post($this->panel('empresas'), [
            'name' => 'Distribuidora Andina', 'slug' => 'andina',
            'owner_name' => 'Juan Mamani', 'owner_email' => 'juan@andina.bo',
            'password' => 'secreto-seguro-123',
            'plan_id' => $this->plan('empresa')->id, 'cycle' => 'monthly',
        ])->assertSessionHas('success');

        $this->assertSame(TenantStatus::Active, Tenant::where('slug', 'andina')->firstOrFail()->status);
    }

    public function test_eliminar_una_empresa_exige_escribir_su_direccion_y_borra_su_base(): void
    {
        $acme = $this->provision('acme');
        $databases = app(TenantDatabaseManager::class);
        $this->actingAs($this->platformAdmin(), 'platform');

        $this->delete($this->panel("empresas/{$acme->id}"), ['confirm' => 'otra'])->assertSessionHasErrors('confirm');
        $this->assertTrue($databases->exists($acme->database));

        $this->delete($this->panel("empresas/{$acme->id}"), ['confirm' => 'acme'])->assertRedirect(route('platform.tenants.index'));
        $this->assertNull(Tenant::find($acme->id));
        $this->assertFalse($databases->exists($acme->database));
    }

    public function test_rechazar_un_pago_guarda_el_motivo_y_no_toca_la_suscripcion(): void
    {
        $acme = $this->provision('acme');
        $payment = app(SubscriptionService::class)
            ->submitPayment($acme, $this->plan('negocio'), BillingCycle::Monthly, PaymentMethod::Transfer);

        $this->actingAs($this->platformAdmin(), 'platform');

        $this->post($this->panel("pagos/{$payment->id}/rechazar"), [])->assertSessionHasErrors('reason');
        $this->post($this->panel("pagos/{$payment->id}/rechazar"), ['reason' => 'El monto no coincide'])->assertSessionHas('success');

        $this->assertSame('El monto no coincide', $payment->fresh()->rejection_reason);
        $this->assertSame(SubscriptionStatus::Trialing, $acme->fresh()->subscription->status);
    }

    public function test_guarda_los_datos_de_cobro_y_gestiona_administradores(): void
    {
        $this->actingAs($root = $this->platformAdmin(), 'platform');

        $this->post($this->panel('ajustes'), ['bank_name' => 'Banco Unión', 'bank_account' => '1-234567', 'support_whatsapp' => '59170012345'])
            ->assertSessionHas('success');
        $this->assertSame('Banco Unión', PlatformSetting::values()['bank_name']);

        $this->post($this->panel('administradores'), [
            'name' => 'Segundo', 'email' => 'dos@avis.test',
            'password' => 'secreto-seguro-123', 'password_confirmation' => 'secreto-seguro-123',
        ])->assertSessionHas('success');

        $this->delete($this->panel("administradores/{$root->id}"))->assertSessionHas('error');
        $this->delete($this->panel('administradores/'.PlatformAdmin::where('email', 'dos@avis.test')->value('id')))->assertSessionHas('success');
        $this->assertSame(1, PlatformAdmin::count());
    }
}
