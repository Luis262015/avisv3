<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SiatCufdCode;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatFacturacionService;
use App\Services\SiatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reglas del comprador y del pago que el SIN impone al emitir.
 *
 * Todas salen de rechazos reales del piloto (2026-08-12): son condiciones que no
 * están en el XSD, así que el XML se construye bien y aun así la factura se cae.
 * Se comprueban antes de consumir un correlativo, porque el número va dentro del
 * CUF y una factura mal formada ya no se puede recuperar.
 */
class SiatEmisionTest extends TestCase
{
    use RefreshDatabase;

    private CashShift $shift;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $user  = User::factory()->create();
        $store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $register = CashRegister::create([
            'store_id' => $store->id, 'name' => 'Caja 1', 'is_active' => true,
        ]);

        $this->shift = CashShift::create([
            'cash_register_id' => $register->id, 'user_id' => $user->id,
            'opening_amount' => 0, 'opened_at' => now(), 'status' => 'open',
        ]);

        SiatSetting::create([
            'store_id'            => $store->id,
            'nit'                 => '1234567890',
            'codigo_sistema'      => 'SISTEMA-DE-PRUEBA',
            'razon_social'        => 'EMPRESA DE PRUEBA SRL',
            'municipio'           => 'LA PAZ',
            // El XSD exige dirección no vacía; el CUFD real siempre la trae.
            'direccion'           => 'AV. SIEMPRE VIVA 123',
            'actividad_economica' => '4741100',
            'leyenda'             => 'Ley N° 453: Leyenda de prueba.',
            'ambiente'            => 'piloto',
            'modalidad'           => 2,
            'cuis'                => 'CUIS-DE-PRUEBA',
            'tipo_factura_default' => 1,
            'is_active'           => true,
        ]);

        SiatCufdCode::create([
            'store_id'       => $store->id,
            'codigo'         => 'CUFD-DE-PRUEBA',
            'codigo_control' => '23E26C80881BF74',
            'fecha_vigencia' => now()->addDay(),
            'consecutivo'    => 0,
            'estado'         => 'activo',
        ]);

        $this->product = Product::create([
            'name' => 'Laptop de prueba', 'slug' => 'laptop-de-prueba', 'sku' => 'LAP-1',
            'price' => 100, 'cost' => 70, 'stock' => 10, 'status' => 'active',
            'codigo_producto_sin' => 1001967, 'unidad_medida_sin' => 57,
        ]);
    }

    private function venta(string $pago = 'cash'): Sale
    {
        $sale = Sale::create([
            'cash_shift_id' => $this->shift->id, 'user_id' => $this->shift->user_id,
            'folio' => 'V-' . uniqid(), 'subtotal' => 100, 'total' => 100,
            'amount_paid' => 100, 'payment_method' => $pago, 'status' => 'completed',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $this->product->id,
            'quantity' => 1, 'price' => 100, 'discount' => 0, 'subtotal' => 100,
        ]);

        return $sale;
    }

    /** Ni el envío ni el correlativo deben llegar a consumirse. */
    private function sinEnvio(): void
    {
        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('recepcionFactura');
        });
    }

    // ─── Documento del comprador ─────────────────────────────────────────────

    /** El piloto responde `1048 ... Numero documento esperado distinto de 0`. */
    public function test_it_refuses_to_issue_without_a_buyer_document(): void
    {
        $this->sinEnvio();

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/documento/');

        app(SiatService::class)->createInvoice($this->venta(), ['nit_ci' => '0']);
    }

    public function test_it_does_not_burn_an_invoice_number_on_a_doomed_invoice(): void
    {
        $this->sinEnvio();
        $cufd = SiatCufdCode::firstOrFail();

        try {
            app(SiatService::class)->createInvoice($this->venta(), ['nit_ci' => '0']);
        } catch (SiatException) {
            // Esperado.
        }

        // El correlativo va dentro del CUF: gastarlo deja un hueco irrecuperable.
        $this->assertSame(0, $cufd->fresh()->consecutivo);
    }

    // ─── Pago con tarjeta ────────────────────────────────────────────────────

    /**
     * El piloto responde `1012 EL NUMERO DE TARJETA SOLO PUEDE SER ENVIADO CUANDO
     * EL METODO DE PAGO SEA CON TARJETA ... enviado null para metodo pago 2`.
     */
    public function test_a_card_payment_needs_the_card_number(): void
    {
        $this->sinEnvio();

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/número de tarjeta/');

        app(SiatService::class)->createInvoice($this->venta('card'), [
            'nit_ci' => '9876543', 'tipo_doc' => 1,
        ]);
    }

    public function test_it_stores_the_card_number_encrypted(): void
    {
        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionFactura')->andReturn([
                'codigoRecepcion' => 'REC-1', 'codigoEstado' => 908,
                'codigoDescripcion' => 'RECIBIDA', 'mensajes' => [], 'respuesta' => [],
            ]);
        });

        $invoice = app(SiatService::class)->createInvoice($this->venta('card'), [
            'nit_ci' => '9876543', 'tipo_doc' => 1, 'numero_tarjeta' => '4111111111111111',
        ]);

        $this->assertSame('4111111111111111', $invoice->numero_tarjeta);

        // En la base no puede quedar en claro.
        $crudo = \DB::table('siat_invoices')->where('id', $invoice->id)->value('numero_tarjeta');
        $this->assertNotSame('4111111111111111', $crudo);
        $this->assertStringNotContainsString('4111', (string) $crudo);
    }

    /** Y nunca debe viajar al navegador dentro de las props de Inertia. */
    public function test_the_card_number_is_hidden_from_serialization(): void
    {
        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionFactura')->andReturn([
                'codigoRecepcion' => 'REC-1', 'codigoEstado' => 908,
                'codigoDescripcion' => 'RECIBIDA', 'mensajes' => [], 'respuesta' => [],
            ]);
        });

        $invoice = app(SiatService::class)->createInvoice($this->venta('card'), [
            'nit_ci' => '9876543', 'tipo_doc' => 1, 'numero_tarjeta' => '4111111111111111',
        ]);

        $this->assertArrayNotHasKey('numero_tarjeta', $invoice->toArray());
    }

    public function test_cash_sales_do_not_need_a_card_number(): void
    {
        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionFactura')->andReturn([
                'codigoRecepcion' => 'REC-1', 'codigoEstado' => 908,
                'codigoDescripcion' => 'RECIBIDA', 'mensajes' => [], 'respuesta' => [],
            ]);
        });

        $invoice = app(SiatService::class)->createInvoice($this->venta(), [
            'nit_ci' => '9876543', 'tipo_doc' => 1,
        ]);

        $this->assertSame('enviada', $invoice->fresh()->estado, (string) $invoice->fresh()->mensaje_error);
        $this->assertNull($invoice->numero_tarjeta);
    }

    // ─── Tipo de factura ─────────────────────────────────────────────────────

    /** Con NIT la factura siempre da derecho a crédito fiscal. */
    public function test_a_buyer_with_nit_always_gets_credit(): void
    {
        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionFactura')->andReturn([
                'codigoRecepcion' => 'REC-1', 'codigoEstado' => 908,
                'codigoDescripcion' => 'RECIBIDA', 'mensajes' => [], 'respuesta' => [],
            ]);
        });

        $invoice = app(SiatService::class)->createInvoice($this->venta(), [
            'nit_ci' => '6923448010', 'tipo_doc' => SiatService::DOC_NIT, 'tipo_factura' => 2,
        ]);

        $this->assertSame(SiatService::FACTURA_CON_CF, $invoice->tipo_factura);
    }
}
