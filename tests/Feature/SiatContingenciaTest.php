<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SiatCufdCode;
use App\Models\SiatEvento;
use App\Models\SiatInvoice;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Siat\CufGenerator;
use App\Services\Siat\SiatContingenciaService;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatFacturacionService;
use App\Services\Siat\SiatOperacionesService;
use App\Services\SiatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ciclo completo de contingencia: abrir el corte, facturar fuera de línea,
 * cerrarlo, declararlo al SIN y enviar el paquete.
 *
 * Los dos servicios que hablan SOAP van doblados; todo lo demás es real, incluida
 * la construcción del XML y su validación contra el XSD oficial, que es donde de
 * verdad se rompen estas cosas.
 */
class SiatContingenciaTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private SiatSetting $setting;
    private CashShift $shift;
    private Product $product;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create();
        $this->store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $register = CashRegister::create([
            'store_id' => $this->store->id, 'name' => 'Caja 1', 'is_active' => true,
        ]);

        $this->shift = CashShift::create([
            'cash_register_id' => $register->id, 'user_id' => $this->user->id,
            'opening_amount' => 0, 'opened_at' => now(), 'status' => 'open',
        ]);

        $this->setting = SiatSetting::create([
            'store_id'            => $this->store->id,
            'nit'                 => '1234567890',
            'codigo_sistema'      => 'SISTEMA-DE-PRUEBA',
            'razon_social'        => 'EMPRESA DE PRUEBA SRL',
            'municipio'           => 'LA PAZ',
            'direccion'           => 'AV. SIEMPRE VIVA 123',
            'actividad_economica' => '4741100',
            // Guardada para que construir el XML no tenga que preguntar al SIN.
            'leyenda'             => 'Ley N° 453: Leyenda de prueba.',
            'ambiente'            => 'piloto',
            'modalidad'           => 2,
            'cuis'                => 'CUIS-DE-PRUEBA',
            'is_active'           => true,
        ]);

        $this->product = Product::create([
            'name' => 'Coca Cola 2L', 'slug' => 'coca-cola-2l', 'sku' => 'CC-2L',
            'price' => 10, 'cost' => 6, 'stock' => 50, 'status' => 'active',
            'codigo_producto_sin' => 1001966, 'unidad_medida_sin' => 57,
        ]);

    }

    /**
     * Se resuelve en cada llamada y no en setUp: los dobles de los servicios SOAP
     * se instalan a mitad del test, y un servicio construido antes seguiría
     * apuntando a los reales.
     */
    private function contingencia(): SiatContingenciaService
    {
        return app(SiatContingenciaService::class);
    }

    private function cufdVigente(): SiatCufdCode
    {
        return SiatCufdCode::create([
            'store_id'       => $this->store->id,
            'codigo'         => 'CUFD-DEL-CORTE',
            'codigo_control' => '23E26C80881BF74',
            'direccion'      => 'ALTURA RELOJ DE LA PEREZ Nro. 819',
            'fecha_vigencia' => now()->addDay(),
            'consecutivo'    => 0,
            'estado'         => 'activo',
        ]);
    }

    private function venta(float $total = 20): Sale
    {
        $sale = Sale::create([
            'cash_shift_id' => $this->shift->id, 'user_id' => $this->user->id,
            'folio' => 'V-' . uniqid(), 'subtotal' => $total, 'total' => $total,
            'amount_paid' => $total, 'payment_method' => 'cash', 'status' => 'completed',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $this->product->id,
            'quantity' => 2, 'price' => $total / 2, 'discount' => 0, 'subtotal' => $total,
        ]);

        return $sale;
    }

    /**
     * El SIN rechaza el documento 0, así que toda factura necesita comprador.
     * Ver SiatEmisionTest para esa regla.
     *
     * @return array<string, string|int>
     */
    private function comprador(): array
    {
        return ['nit_ci' => '6923448010', 'tipo_doc' => 5, 'nombre' => 'CLIENTE DE PRUEBA'];
    }

    private function abrirCorte(): SiatEvento
    {
        $this->cufdVigente();

        // 2 = "Inaccesibilidad al servicio web de la Administración Tributaria".
        return $this->contingencia()->abrir($this->setting, 2, 'Caída del servicio del SIN');
    }

    // ─── Apertura ────────────────────────────────────────────────────────────

    public function test_it_needs_a_valid_cufd_to_open_a_blackout(): void
    {
        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/CUFD vigente/');

        $this->contingencia()->abrir($this->setting, 2, 'Sin internet');
    }

    /**
     * El CAFC NO se exige para abrir un corte: solo lo piden ciertos motivos de
     * evento. El piloto responde «1045 … Cafc esperado null» al motivo 2 si se
     * manda uno de más, así que exigirlo rompería el caso más común.
     */
    public function test_it_does_not_demand_a_cafc_to_open_a_blackout(): void
    {
        $this->cufdVigente();

        $evento = $this->contingencia()->abrir($this->setting, 2, 'Sin servicio web del SIN');

        $this->assertSame('abierto', $evento->estado);
        $this->assertNull($evento->cafc);
    }

    /** Cuando el motivo sí lo exige, el CAFC se guarda en el corte. */
    public function test_it_keeps_the_cafc_on_the_blackout_when_one_applies(): void
    {
        $this->cufdVigente();

        $evento = $this->contingencia()->abrir($this->setting, 5, 'Falla de software', cafc: 'CAFC12345');

        $this->assertSame('CAFC12345', $evento->cafc);
    }

    public function test_it_refuses_a_second_simultaneous_blackout(): void
    {
        $this->abrirCorte();

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/Ya hay un corte abierto/');

        $this->contingencia()->abrir($this->setting, 2, 'Otro corte');
    }

    public function test_it_records_the_cufd_in_force_during_the_blackout(): void
    {
        $evento = $this->abrirCorte();

        $this->assertSame('abierto', $evento->estado);
        $this->assertSame('CUFD-DEL-CORTE', $evento->cufdCode->codigo);
        $this->assertNull($evento->fecha_fin);
    }

    // ─── Emisión durante el corte ────────────────────────────────────────────

    public function test_invoices_issued_during_a_blackout_go_offline_and_are_not_sent(): void
    {
        $evento = $this->abrirCorte();
        $evento->update(['cafc' => 'CAFC12345']);

        // Si intentara enviar, el doble se quejaría de una llamada no esperada.
        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('recepcionFactura');
        });

        $invoice = app(SiatService::class)->createInvoice($this->venta(), $this->comprador());

        $this->assertSame('contingencia', $invoice->estado);
        $this->assertSame(CufGenerator::EMISION_OFFLINE, $invoice->tipo_emision);
        $this->assertSame('CAFC12345', $invoice->cafc);
        $this->assertSame($evento->id, $invoice->evento_id);
        // Numera contra el CUFD del corte, no contra uno nuevo pedido al SIN.
        $this->assertSame('CUFD-DEL-CORTE', $invoice->cufd);
    }

    /** El CUF de una factura offline no puede ser el de una en línea. */
    public function test_the_offline_cuf_declares_emission_type_two(): void
    {
        $this->abrirCorte();

        $this->mock(SiatFacturacionService::class, fn ($mock) => $mock->shouldNotReceive('recepcionFactura'));

        $invoice = app(SiatService::class)->createInvoice($this->venta(), $this->comprador());
        $enLinea = app(CufGenerator::class)->generate(
            nit: $this->setting->nit,
            fechaEmision: $invoice->fecha_emision,
            sucursal: 0, modalidad: 2,
            tipoEmision: CufGenerator::EMISION_ONLINE,
            tipoFactura: (int) $invoice->tipo_factura,
            tipoDocumentoSector: CufGenerator::SECTOR_COMPRA_VENTA,
            numeroFactura: (int) $invoice->numero_factura,
            puntoVenta: 0,
            codigoControl: '23E26C80881BF74',
        );

        $this->assertNotSame($enLinea, $invoice->cuf);
    }

    public function test_invoices_go_back_online_once_the_blackout_is_closed(): void
    {
        $evento = $this->abrirCorte();
        $this->contingencia()->cerrar($evento);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionFactura')->once()->andReturn([
                'codigoRecepcion' => 'REC-1', 'codigoEstado' => 908,
                'codigoDescripcion' => 'RECIBIDA', 'mensajes' => [], 'respuesta' => [],
            ]);
        });

        $invoice = app(SiatService::class)->createInvoice($this->venta(), $this->comprador());

        $this->assertSame('enviada', $invoice->fresh()->estado);
        $this->assertNull($invoice->cafc);
    }

    // ─── Declaración del evento ──────────────────────────────────────────────

    public function test_it_refuses_to_declare_a_blackout_that_is_still_open(): void
    {
        $evento = $this->abrirCorte();

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/Cierre el corte/');

        $this->contingencia()->declarar($evento, $this->setting);
    }

    public function test_declaring_stores_the_reception_code_the_sin_returns(): void
    {
        $evento = $this->abrirCorte();
        $this->contingencia()->cerrar($evento);

        $this->mock(SiatOperacionesService::class, function ($mock): void {
            $mock->shouldReceive('registrarEvento')->once()->andReturn('EVT-999');
        });

        $evento = $this->contingencia()->declarar($evento, $this->setting);

        $this->assertSame('registrado', $evento->estado);
        $this->assertSame('EVT-999', $evento->codigo_recepcion_evento);
    }

    /** Reintentar no debe declarar dos veces el mismo corte ante el SIN. */
    public function test_declaring_twice_does_not_call_the_sin_again(): void
    {
        $evento = $this->abrirCorte();
        $this->contingencia()->cerrar($evento);

        $this->mock(SiatOperacionesService::class, function ($mock): void {
            $mock->shouldReceive('registrarEvento')->once()->andReturn('EVT-999');
        });

        $evento = $this->contingencia()->declarar($evento, $this->setting);
        $this->contingencia()->declarar($evento, $this->setting);

        $this->assertSame('EVT-999', $evento->fresh()->codigo_recepcion_evento);
    }

    public function test_a_rejected_declaration_leaves_the_reason_on_the_event(): void
    {
        $evento = $this->abrirCorte();
        $this->contingencia()->cerrar($evento);

        $this->mock(SiatOperacionesService::class, function ($mock): void {
            $mock->shouldReceive('registrarEvento')
                ->andThrow(new SiatException('El SIN rechazó el registro: 981 EVENTO FUERA DE PLAZO'));
        });

        try {
            $this->contingencia()->declarar($evento, $this->setting);
            $this->fail('Se esperaba una SiatException.');
        } catch (SiatException) {
            // Esperado.
        }

        $this->assertSame('cerrado', $evento->fresh()->estado);
        $this->assertStringContainsString('FUERA DE PLAZO', (string) $evento->fresh()->mensaje_error);
    }

    // ─── Envío del paquete ───────────────────────────────────────────────────

    public function test_it_refuses_to_send_a_package_for_an_undeclared_blackout(): void
    {
        $evento = $this->abrirCorte();
        $this->contingencia()->cerrar($evento);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/todavía no está declarado/');

        $this->contingencia()->enviarPaquete($evento, $this->setting);
    }

    public function test_it_packs_the_blackout_invoices_and_marks_them_sent(): void
    {
        $evento = $this->abrirCorte();

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('recepcionFactura');
        });

        $siat = app(SiatService::class);
        $siat->createInvoice($this->venta(), $this->comprador());
        $siat->createInvoice($this->venta(), $this->comprador());

        $this->contingencia()->cerrar($evento);
        $evento->update(['estado' => 'registrado', 'codigo_recepcion_evento' => 'EVT-999']);

        // El doble se sustituye ahora: el paquete sí tiene que salir.
        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionPaqueteFactura')
                ->once()
                ->andReturn([
                    'codigoRecepcion' => 'PAQ-1', 'codigoEstado' => 901,
                    'codigoDescripcion' => 'PENDIENTE', 'mensajes' => [], 'respuesta' => [],
                ]);
        });

        $paquete = $this->contingencia()->enviarPaquete($evento->fresh(), $this->setting);

        $this->assertSame(2, $paquete->cantidad_facturas);
        $this->assertSame('enviado', $paquete->estado);
        $this->assertSame('PAQ-1', $paquete->codigo_recepcion);
        $this->assertSame(64, strlen((string) $paquete->hash_archivo));

        $this->assertSame(2, $paquete->invoices()->where('estado', 'enviada')->count());
        $this->assertSame(0, $evento->invoices()->where('estado', 'contingencia')->count());
    }

    public function test_a_rejected_package_is_recorded_instead_of_lost(): void
    {
        $evento = $this->abrirCorte();

        $this->mock(SiatFacturacionService::class, fn ($mock) => $mock->shouldNotReceive('recepcionFactura'));
        app(SiatService::class)->createInvoice($this->venta(), $this->comprador());

        $this->contingencia()->cerrar($evento);
        $evento->update(['estado' => 'registrado', 'codigo_recepcion_evento' => 'EVT-999']);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionPaqueteFactura')
                ->andThrow(new SiatException('El SIN rechazó la operación: 905 HASH NO COINCIDE'));
        });

        try {
            $this->contingencia()->enviarPaquete($evento->fresh(), $this->setting);
            $this->fail('Se esperaba una SiatException.');
        } catch (SiatException) {
            // Esperado.
        }

        $paquete = $evento->paquetes()->first();

        $this->assertNotNull($paquete, 'El paquete debe quedar registrado aunque el envío falle.');
        $this->assertSame('rechazado', $paquete->estado);
        $this->assertStringContainsString('HASH NO COINCIDE', (string) $paquete->mensaje_error);
        // Las facturas siguen pendientes: se pueden reintentar.
        $this->assertSame(1, $evento->invoices()->where('estado', 'contingencia')->count());
    }

    // ─── Emisión masiva ──────────────────────────────────────────────────────

    /**
     * En masiva el tipo de emisión va en el CUF, así que la decisión se toma al
     * emitir. Enviar por `recepcionFactura` una factura cuyo CUF dice "3" hace que
     * el SIN la rechace por incoherencia.
     */
    public function test_a_mass_point_of_sale_issues_with_emission_type_three_and_does_not_send(): void
    {
        $this->setting->update(['emision_masiva' => true]);
        $this->cufdVigente();

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('recepcionFactura');
        });

        $invoice = app(SiatService::class)->createInvoice($this->venta(), $this->comprador());

        $this->assertSame(SiatService::EMISION_MASIVA, $invoice->tipo_emision);
        $this->assertSame('pendiente', $invoice->estado);
        // No es contingencia: no hay corte ni CAFC de por medio.
        $this->assertNull($invoice->evento_id);
        $this->assertNull($invoice->cafc);
    }

    public function test_a_mass_invoice_cannot_be_resent_on_its_own(): void
    {
        $this->setting->update(['emision_masiva' => true]);
        $this->cufdVigente();

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('recepcionFactura');
        });

        $invoice = app(SiatService::class)->createInvoice($this->venta(), $this->comprador());

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/no se envía suelta/');

        app(SiatService::class)->resendInvoice($invoice);
    }

    public function test_it_sends_the_mass_batch_and_marks_the_invoices_sent(): void
    {
        $this->setting->update(['emision_masiva' => true]);
        $this->cufdVigente();

        $this->mock(SiatFacturacionService::class, fn ($mock) => $mock->shouldNotReceive('recepcionFactura'));

        $siat = app(SiatService::class);
        $siat->createInvoice($this->venta(), $this->comprador());
        $siat->createInvoice($this->venta(), $this->comprador());

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionMasivaFactura')->once()->andReturn([
                'codigoRecepcion' => 'MAS-1', 'codigoEstado' => 901,
                'codigoDescripcion' => 'PENDIENTE', 'mensajes' => [], 'respuesta' => [],
            ]);
        });

        $paquete = $this->contingencia()->enviarMasivo(
            $this->setting,
            SiatInvoice::where('estado', 'pendiente')->get(),
        );

        $this->assertSame('masivo', $paquete->tipo);
        $this->assertNull($paquete->evento_id, 'Un lote masivo no responde a ningún corte.');
        $this->assertSame(2, $paquete->cantidad_facturas);
        $this->assertSame(2, $paquete->invoices()->where('estado', 'enviada')->count());
    }

    public function test_validating_a_package_marks_it_accepted_when_the_sin_says_so(): void
    {
        $evento = $this->abrirCorte();

        $this->mock(SiatFacturacionService::class, fn ($mock) => $mock->shouldNotReceive('recepcionFactura'));
        app(SiatService::class)->createInvoice($this->venta(), $this->comprador());

        $this->contingencia()->cerrar($evento);
        $evento->update(['estado' => 'registrado', 'codigo_recepcion_evento' => 'EVT-999']);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionPaqueteFactura')->andReturn([
                'codigoRecepcion' => 'PAQ-1', 'codigoEstado' => 901,
                'codigoDescripcion' => 'PENDIENTE', 'mensajes' => [], 'respuesta' => [],
            ]);
            $mock->shouldReceive('validacionRecepcionPaquete')->once()->andReturn([
                'codigoRecepcion' => 'PAQ-1',
                'codigoEstado'    => SiatFacturacionService::ESTADO_VALIDADA,
                'codigoDescripcion' => 'VALIDADA', 'mensajes' => [], 'respuesta' => [],
            ]);
        });

        $paquete = $this->contingencia()->enviarPaquete($evento->fresh(), $this->setting);
        $this->contingencia()->validarPaquete($paquete, $this->setting);

        $this->assertSame('validado', $paquete->fresh()->estado);
        $this->assertNotNull($paquete->fresh()->validado_at);
    }
}
