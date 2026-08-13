<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Sale;
use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatFacturacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Acciones de la ficha de factura que la interfaz ya invoca: reenviar al SIN y
 * consultar el estado real de una factura recibida.
 */
class SiatInvoiceActionsTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $register = CashRegister::create([
            'store_id' => $this->store->id, 'name' => 'Caja 1', 'is_active' => true,
        ]);

        $shift = CashShift::create([
            'cash_register_id' => $register->id, 'user_id' => $user->id,
            'opening_amount' => 0, 'opened_at' => now(), 'status' => 'open',
        ]);

        $this->sale = Sale::create([
            'cash_shift_id' => $shift->id, 'user_id' => $user->id, 'folio' => 'V-0001',
            'subtotal' => 100, 'total' => 100, 'amount_paid' => 100,
            'payment_method' => 'cash', 'status' => 'completed',
        ]);
    }

    private function setting(string $ambiente): SiatSetting
    {
        return SiatSetting::create([
            'store_id'            => $this->store->id,
            'nit'                 => '1234567890',
            'razon_social'        => 'EMPRESA DE PRUEBA SRL',
            'municipio'           => 'LA PAZ',
            'actividad_economica' => '4741100',
            'ambiente'            => $ambiente,
            'cuis'                => 'CUIS-DE-PRUEBA',
            'is_active'           => true,
        ]);
    }

    private function invoice(array $atributos = []): SiatInvoice
    {
        $cufd = SiatCufdCode::create([
            'store_id'       => $this->store->id,
            'codigo'         => 'CUFD-DE-PRUEBA',
            'codigo_control' => '23E26C80881BF74',
            'fecha_vigencia' => now()->addDay(),
            'consecutivo'    => 1,
            'estado'         => 'activo',
        ]);

        return SiatInvoice::create(array_merge([
            'sale_id'         => $this->sale->id,
            'store_id'        => $this->store->id,
            'cufd_code_id'    => $cufd->id,
            'numero_factura'  => 1,
            'fecha_emision'   => now(),
            'cuf'             => '1D9B8B69B433E44A906A66F0556EC41622D2A8A9A8123E26C80881BF74',
            'cufd'            => $cufd->codigo,
            'importe_total'   => 100,
            'importe_base_cf' => 100,
            'tipo_factura'    => 2,
            'estado'          => 'pendiente',
        ], $atributos));
    }

    /** En simulado no hay a quién enviar: se marca localmente y se avisa de ello. */
    public function test_resending_in_simulated_mode_does_not_pretend_to_reach_the_sin(): void
    {
        $this->setting('simulado');
        $invoice = $this->invoice();

        $this->post("/admin/siat/invoices/{$invoice->id}/resend")
            ->assertSessionHasNoErrors();

        $this->assertSame('enviada', $invoice->fresh()->estado);
        $this->assertStringContainsString('sin validez fiscal', session('success'));
    }

    public function test_it_refuses_to_resend_an_annulled_invoice(): void
    {
        $this->setting('piloto');
        $invoice = $this->invoice(['estado' => 'anulada', 'anulado_at' => now()]);

        $this->post("/admin/siat/invoices/{$invoice->id}/resend")
            ->assertSessionHasErrors('siat');

        $this->assertSame('anulada', $invoice->fresh()->estado);
    }

    public function test_it_reports_the_state_the_sin_returns(): void
    {
        $this->setting('piloto');
        $invoice = $this->invoice(['estado' => 'enviada', 'codigo_recepcion' => 'REC-1']);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('verificacionEstadoFactura')
                ->once()
                ->andReturn([
                    'codigoRecepcion'   => 'REC-1',
                    'codigoEstado'      => SiatFacturacionService::ESTADO_VALIDADA,
                    'codigoDescripcion' => 'VALIDADA',
                    'mensajes'          => [],
                    'respuesta'         => [],
                ]);
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/check-status")
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('VALIDADA', session('success'));
    }

    /** Un fallo del SIN tiene que llegar al usuario, no quedarse en un 500. */
    public function test_it_surfaces_a_sin_failure_when_checking_the_state(): void
    {
        $this->setting('piloto');
        $invoice = $this->invoice(['estado' => 'enviada']);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('verificacionEstadoFactura')
                ->andThrow(new SiatException('El SIN rechazó la operación: 994 CUF INEXISTENTE'));
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/check-status")
            ->assertSessionHasErrors('siat');
    }

    public function test_checking_the_state_needs_an_active_configuration(): void
    {
        $invoice = $this->invoice(['estado' => 'enviada']);

        $this->post("/admin/siat/invoices/{$invoice->id}/check-status")
            ->assertSessionHasErrors('siat');
    }

    // ─── Reversión de anulación ──────────────────────────────────────────────

    public function test_it_reverts_a_cancellation_and_the_invoice_is_valid_again(): void
    {
        $this->setting('piloto');
        $invoice = $this->invoice([
            'estado' => 'anulada', 'anulado_at' => now(), 'motivo_anulacion' => 'Datos incorrectos',
        ]);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('reversionAnulacionFactura')->once()->andReturn([
                'codigoRecepcion' => null, 'codigoEstado' => 906,
                'codigoDescripcion' => 'REVERSION DE ANULACION CONFIRMADA',
                'mensajes' => [], 'respuesta' => [],
            ]);
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/revert-cancellation")
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('enviada', $invoice->estado);
        $this->assertNull($invoice->anulado_at);
        $this->assertNull($invoice->motivo_anulacion);
    }

    public function test_it_only_reverts_invoices_that_are_actually_cancelled(): void
    {
        $this->setting('piloto');
        $invoice = $this->invoice(['estado' => 'enviada']);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('reversionAnulacionFactura');
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/revert-cancellation")
            ->assertSessionHasErrors('siat');
    }

    /**
     * Fuera de plazo el SIN rechaza la reversión, y entonces la factura tiene que
     * seguir anulada en local: darla por vigente aquí mientras el SIN la mantiene
     * anulada es la peor de las dos incoherencias posibles.
     */
    public function test_a_rejected_reversal_leaves_the_invoice_cancelled(): void
    {
        $this->setting('piloto');
        $invoice = $this->invoice(['estado' => 'anulada', 'anulado_at' => now()]);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('reversionAnulacionFactura')
                ->andThrow(new SiatException('El SIN rechazó la operación: 906 FUERA DE PLAZO'));
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/revert-cancellation")
            ->assertSessionHasErrors('siat');

        $this->assertSame('anulada', $invoice->fresh()->estado);
        $this->assertNotNull($invoice->fresh()->anulado_at);
    }
}
