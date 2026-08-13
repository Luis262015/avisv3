<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Siat\RegistroCompraXml;
use App\Services\Siat\RegistroComprasService;
use App\Services\Siat\SiatComprasService;
use App\Services\Siat\SiatException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registro de Compras: el contribuyente declara lo que compró.
 *
 * El XML se valida de verdad contra `registroCompra.xsd`, que es el XSD oficial
 * del SIN; lo único doblado es la llamada SOAP.
 */
class SiatRegistroComprasTest extends TestCase
{
    use RefreshDatabase;

    private SiatSetting $setting;
    private Supplier $proveedor;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $store      = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $this->setting = SiatSetting::create([
            'store_id'            => $store->id,
            'nit'                 => '1234567890',
            'codigo_sistema'      => 'SISTEMA-DE-PRUEBA',
            'razon_social'        => 'EMPRESA DE PRUEBA SRL',
            'municipio'           => 'LA PAZ',
            'actividad_economica' => '4741100',
            'ambiente'            => 'piloto',
            'cuis'                => 'CUIS-DE-PRUEBA',
            'is_active'           => true,
        ]);

        $this->proveedor = Supplier::create([
            'name' => 'PROVEEDOR DE PRUEBA SRL', 'rfc' => '1020304050', 'is_active' => true,
        ]);
    }

    private function compra(array $atributos = []): Purchase
    {
        return Purchase::create(array_merge([
            'supplier_id'         => $this->proveedor->id,
            'user_id'             => $this->user->id,
            'folio'               => 'C-' . uniqid(),
            'date'                => now()->toDateString(),
            'invoice_number'      => '12345',
            'invoice_date'        => now()->toDateString(),
            'codigo_autorizacion' => 'ABC123DEF456',
            'numero_dui_dim'      => '0',
            'tipo_compra'         => 1,
            'subtotal'            => 100,
            'total'               => 100,
            'credito_fiscal'      => 13,
            'status'              => 'received',
        ], $atributos));
    }

    private function xml(): RegistroCompraXml
    {
        return app(RegistroCompraXml::class);
    }

    // ─── Construcción del XML ────────────────────────────────────────────────

    public function test_it_produces_xml_that_validates_against_the_official_xsd(): void
    {
        $xml = $this->xml()->build($this->compra(), 1);

        $this->assertStringContainsString('<registroCompra>', $xml);
        $this->assertStringContainsString('<nitEmisor>1020304050</nitEmisor>', $xml);
        $this->assertStringContainsString('<razonSocialEmisor>PROVEEDOR DE PRUEBA SRL</razonSocialEmisor>', $xml);
        $this->assertStringContainsString('<numeroFactura>12345</numeroFactura>', $xml);
    }

    /** El emisor es el proveedor, no la tienda: es el reverso de una factura. */
    public function test_the_issuer_is_the_supplier_and_not_the_shop(): void
    {
        $xml = $this->xml()->build($this->compra(), 1);

        $this->assertStringNotContainsString('<nitEmisor>1234567890</nitEmisor>', $xml);
    }

    /** Los datos propios de la compra mandan sobre la ficha del proveedor. */
    public function test_the_purchase_can_override_the_supplier_details(): void
    {
        $xml = $this->xml()->build($this->compra([
            'nit_proveedor'          => '9998887',
            'razon_social_proveedor' => 'OTRO NOMBRE SRL',
        ]), 1);

        $this->assertStringContainsString('<nitEmisor>9998887</nitEmisor>', $xml);
        $this->assertStringContainsString('<razonSocialEmisor>OTRO NOMBRE SRL</razonSocialEmisor>', $xml);
    }

    /** El XSD declara `numeroDuiDim` con minLength 1: en compras internas va "0". */
    public function test_an_internal_purchase_declares_zero_as_dui_dim(): void
    {
        $xml = $this->xml()->build($this->compra(['numero_dui_dim' => null]), 1);

        $this->assertStringContainsString('<numeroDuiDim>0</numeroDuiDim>', $xml);
    }

    public function test_the_taxable_base_discounts_what_gives_no_credit(): void
    {
        $xml = $this->xml()->build($this->compra([
            'total' => 100, 'importe_ice' => 10, 'importes_exentos' => 5,
        ]), 1);

        // 100 - 10 de ICE - 5 exentos = 85 sujetos a IVA.
        $this->assertStringContainsString('<montoTotalSujetoIva>85.00</montoTotalSujetoIva>', $xml);
    }

    public function test_it_uses_the_supplier_invoice_date_and_not_the_registration_date(): void
    {
        $xml = $this->xml()->build($this->compra([
            'invoice_date' => '2026-03-15',
            'date'         => '2026-08-01',
        ]), 1);

        $this->assertStringContainsString('<fechaEmision>2026-03-15T00:00:00</fechaEmision>', $xml);
    }

    // ─── Datos que faltan ────────────────────────────────────────────────────

    public function test_it_lists_everything_missing_at_once(): void
    {
        $compra = $this->compra([
            'codigo_autorizacion' => null,
            'invoice_number'      => null,
            'tipo_compra'         => null,
        ]);

        $problemas = $this->xml()->problemas($compra);

        $this->assertCount(3, $problemas);
        $this->assertStringContainsString('código de autorización', implode(' ', $problemas));
        $this->assertStringContainsString('número de factura', implode(' ', $problemas));
        $this->assertStringContainsString('tipo de compra', implode(' ', $problemas));
    }

    /** Un número de factura con letras no es declarable: el XSD lo quiere entero. */
    public function test_a_non_numeric_invoice_number_is_reported_as_a_problem(): void
    {
        $problemas = $this->xml()->problemas($this->compra(['invoice_number' => 'FAC-A/2026']));

        $this->assertNotEmpty($problemas);
        $this->assertStringContainsString('no es numérico', implode(' ', $problemas));
    }

    public function test_a_supplier_without_tax_id_is_reported(): void
    {
        $this->proveedor->update(['rfc' => null]);

        $problemas = $this->xml()->problemas($this->compra()->fresh());

        $this->assertStringContainsString('NIT', implode(' ', $problemas));
    }

    // ─── Envío del paquete ───────────────────────────────────────────────────

    public function test_it_refuses_to_send_a_purchase_that_is_not_declarable(): void
    {
        $compra = $this->compra(['codigo_autorizacion' => null]);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/no se puede declarar/');

        app(RegistroComprasService::class)->enviarPeriodo(
            $this->setting, collect([$compra]), 2026, 8,
        );
    }

    public function test_it_refuses_an_empty_period(): void
    {
        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/No hay compras/');

        app(RegistroComprasService::class)->enviarPeriodo($this->setting, collect(), 2026, 8);
    }

    public function test_it_sends_the_period_and_links_the_purchases_to_the_package(): void
    {
        $compras = collect([$this->compra(), $this->compra(['invoice_number' => '999'])]);

        $this->mock(SiatComprasService::class, function ($mock): void {
            $mock->shouldReceive('recepcionPaqueteCompras')
                ->once()
                ->andReturn([
                    'codigoRecepcion' => 'COMP-1', 'codigoEstado' => 5152,
                    'codigoDescripcion' => '', 'mensajes' => [], 'respuesta' => [],
                ]);
        });

        $paquete = app(RegistroComprasService::class)->enviarPeriodo($this->setting, $compras, 2026, 8);

        $this->assertSame('compras', $paquete->tipo);
        $this->assertSame(2026, $paquete->gestion);
        $this->assertSame(8, $paquete->periodo);
        $this->assertSame(2, $paquete->cantidad_facturas);
        $this->assertSame('enviado', $paquete->estado);

        foreach ($compras as $compra) {
            $this->assertSame($paquete->id, $compra->fresh()->paquete_id);
        }
    }

    /** Si el SIN rechaza el lote, queda constancia de qué iba dentro. */
    public function test_a_rejected_period_is_recorded_instead_of_lost(): void
    {
        $this->mock(SiatComprasService::class, function ($mock): void {
            $mock->shouldReceive('recepcionPaqueteCompras')
                ->andThrow(new SiatException('El SIN rechazó la operación: 5153 PERIODO CERRADO'));
        });

        try {
            app(RegistroComprasService::class)->enviarPeriodo($this->setting, collect([$this->compra()]), 2026, 8);
            $this->fail('Se esperaba una SiatException.');
        } catch (SiatException) {
            // Esperado.
        }

        $paquete = \App\Models\SiatPaquete::where('tipo', 'compras')->firstOrFail();

        $this->assertSame('rechazado', $paquete->estado);
        $this->assertStringContainsString('PERIODO CERRADO', (string) $paquete->mensaje_error);
    }
}
