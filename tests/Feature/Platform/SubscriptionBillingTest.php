<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Central\SubscriptionPayment;
use App\Models\Store;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * La renta: vencimientos, pagos y lo que cada plan deja hacer.
 */
class SubscriptionBillingTest extends PlatformTestCase
{
    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    public function test_aprobar_un_pago_deja_la_suscripcion_al_dia_por_un_periodo(): void
    {
        $acme = $this->provision('acme');
        $payment = $this->service()->submitPayment($acme, $this->plan('negocio'), BillingCycle::Monthly, PaymentMethod::Qr, 'TRX-1');

        $this->assertSame('199.00', $payment->amount);
        $this->assertSame(SubscriptionStatus::Trialing, $acme->subscription->status, 'Avisar del pago no cambia nada hasta que se aprueba.');

        $this->service()->approve($payment);

        $subscription = $acme->fresh()->subscription;
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertEqualsWithDelta(now()->addMonth()->timestamp, $subscription->current_period_end->timestamp, 5);
        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
    }

    public function test_renovar_antes_de_vencer_suma_al_periodo_en_curso(): void
    {
        $acme = $this->provision('acme');
        $plan = $this->plan('negocio');

        $this->service()->approve($this->service()->submitPayment($acme, $plan, BillingCycle::Monthly, PaymentMethod::Qr));
        $firstEnd = $acme->fresh()->subscription->current_period_end;

        $this->service()->approve($this->service()->submitPayment($acme->fresh(), $plan, BillingCycle::Monthly, PaymentMethod::Qr));

        $this->assertEqualsWithDelta(
            $firstEnd->copy()->addMonthNoOverflow()->timestamp,
            $acme->fresh()->subscription->current_period_end->timestamp,
            5,
        );
    }

    public function test_un_pago_ya_revisado_no_se_puede_volver_a_aprobar(): void
    {
        $acme = $this->provision('acme');
        $payment = $this->service()->submitPayment($acme, $this->plan('negocio'), BillingCycle::Monthly, PaymentMethod::Qr);
        $this->service()->approve($payment);

        $this->expectException(\DomainException::class);
        $this->service()->approve($payment->fresh());
    }

    public function test_con_la_renta_vencida_solo_se_llega_a_la_pagina_de_suscripcion(): void
    {
        $acme = $this->provision('acme');
        $owner = $this->ownerOf($acme);

        $this->actingAs($owner)->get($this->tenantUrl($acme, 'dashboard'))->assertOk();

        // Pasó la prueba y también la tolerancia.
        $this->travel(14 + (int) config('billing.grace_days') + 1)->days();

        $this->actingAs($owner)->get($this->tenantUrl($acme, 'dashboard'))->assertRedirect(route('subscription.show'));
        $this->actingAs($owner)->get($this->tenantUrl($acme, 'admin/products'))->assertRedirect(route('subscription.show'));
        $this->actingAs($owner)->get($this->tenantUrl($acme, 'suscripcion'))->assertOk();

        $this->assertSame(1, $this->service()->markOverdue());
        $this->assertSame(SubscriptionStatus::PastDue, $acme->fresh()->subscription->status);
    }

    public function test_durante_la_tolerancia_todavia_se_puede_operar(): void
    {
        $acme = $this->provision('acme');
        $owner = $this->ownerOf($acme);

        $this->travel(15)->days();

        $this->actingAs($owner)->get($this->tenantUrl($acme, 'dashboard'))->assertOk();
    }

    public function test_una_empresa_suspendida_no_opera_aunque_este_al_dia(): void
    {
        $acme = $this->provision('acme');
        $acme->update(['status' => 'suspended', 'suspension_reason' => 'Revisión de la cuenta']);

        $this->actingAs($this->ownerOf($acme))
            ->get($this->tenantUrl($acme, 'dashboard'))
            ->assertRedirect(route('subscription.show'));
    }

    public function test_el_plan_no_abre_los_modulos_que_no_incluye(): void
    {
        $gratis = $this->provision('gratis-sa', 'gratis');
        $negocio = $this->provision('negocio-sa', 'negocio');

        $this->actingAs($this->ownerOf($gratis))
            ->get($this->tenantUrl($gratis, 'admin/expenses'))
            ->assertRedirect(route('subscription.show'))
            ->assertSessionHas('error');

        // El núcleo lo tiene cualquier plan.
        $this->actingAs($this->ownerOf($gratis))->get($this->tenantUrl($gratis, 'admin/products'))->assertOk();

        $this->actingAs($this->ownerOf($negocio))->get($this->tenantUrl($negocio, 'admin/expenses'))->assertOk();
        $this->actingAs($this->ownerOf($negocio))
            ->get($this->tenantUrl($negocio, 'admin/employees'))
            ->assertRedirect(route('subscription.show'));
    }

    public function test_el_plan_no_deja_pasar_de_su_cupo(): void
    {
        $acme = $this->provision('acme', 'gratis');
        $owner = $this->ownerOf($acme);

        // El plan gratuito trae una tienda y ya nació con la casa matriz.
        $this->actingAs($owner)
            ->post($this->tenantUrl($acme, 'admin/stores'), ['name' => 'Sucursal 2', 'is_active' => true])
            ->assertSessionHas('error');

        $this->assertSame(1, $this->inTenant($acme, fn () => Store::count()));

        // Dos usuarios: la dueña y uno más; el tercero ya no entra.
        $nuevo = fn (string $email) => [
            'name' => 'Cajero', 'email' => $email, 'role' => 'vendedor',
            'password' => 'secreto-seguro-123', 'password_confirmation' => 'secreto-seguro-123',
        ];

        $this->actingAs($owner)->post($this->tenantUrl($acme, 'admin/users'), $nuevo('uno@acme.test'))->assertSessionHasNoErrors();
        $this->actingAs($owner)->post($this->tenantUrl($acme, 'admin/users'), $nuevo('dos@acme.test'))->assertSessionHas('error');

        $this->assertSame(2, $this->inTenant($acme, fn () => User::count()));
    }

    public function test_la_empresa_envia_su_comprobante_y_la_plataforma_lo_aprueba(): void
    {
        Storage::fake('central');

        $acme = $this->provision('acme');
        $owner = $this->ownerOf($acme);

        $this->actingAs($owner)->post($this->tenantUrl($acme, 'suscripcion/pagos'), [
            'plan' => 'negocio',
            'cycle' => 'yearly',
            'method' => 'qr',
            'reference' => 'QR-778899',
            'proof' => UploadedFile::fake()->image('comprobante.jpg'),
        ])->assertSessionHas('success');

        $payment = SubscriptionPayment::sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('1990.00', $payment->amount);
        Storage::disk('central')->assertExists($payment->proof_path);

        // Mientras haya uno por revisar no se acumulan avisos.
        $this->actingAs($owner)->post($this->tenantUrl($acme, 'suscripcion/pagos'), [
            'plan' => 'negocio', 'cycle' => 'yearly', 'method' => 'qr',
            'proof' => UploadedFile::fake()->image('otro.jpg'),
        ])->assertSessionHas('error');
        $this->assertSame(1, SubscriptionPayment::count());

        auth()->logout();

        $this->actingAs($this->platformAdmin(), 'platform')
            ->post(self::CENTRAL."/plataforma/pagos/{$payment->id}/aprobar")
            ->assertSessionHas('success');

        $subscription = $acme->fresh()->subscription;
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(BillingCycle::Yearly, $subscription->billing_cycle);
        $this->assertEqualsWithDelta(now()->addYear()->timestamp, $subscription->current_period_end->timestamp, 5);
    }

    public function test_solo_el_administrador_de_la_empresa_puede_pagar_o_cambiar_de_plan(): void
    {
        $acme = $this->provision('acme');

        $vendedor = $this->inTenant($acme, function () {
            $user = User::factory()->create();
            $user->assignRole('vendedor');

            return $user;
        });

        $this->actingAs($vendedor)->post($this->tenantUrl($acme, 'suscripcion/plan-gratuito'))->assertForbidden();
        $this->assertSame('negocio', $acme->fresh()->subscription->plan->slug);

        $this->actingAs($this->ownerOf($acme))->post($this->tenantUrl($acme, 'suscripcion/plan-gratuito'))->assertSessionHas('success');
        $this->assertSame('gratis', $acme->fresh()->subscription->plan->slug);
    }
}
