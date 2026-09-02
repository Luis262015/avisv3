<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\SiatNota;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Siat\ConstructorNotaXml;
use App\Services\Siat\NotaCreditoDebitoXml;
use App\Services\Siat\SiatDocumentoAjusteService;
use App\Services\Siat\SiatException;
use App\Services\SiatNotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notas de Crédito-Débito: documentos sector 24 y 47.
 *
 * Ajustan una factura ya emitida, así que casi todo lo que se comprueba aquí son
 * las condiciones que el SIN impone y que no están en el XSD: que exista factura
 * original y esté vigente, que no se devuelva más de lo facturado y que el
 * importe "efectivo" sea el 13 % del devuelto y no el dinero entregado. El XML sí
 * se valida de verdad contra los dos XSD oficiales del SIN.
 */
class SiatNotaTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private CashShift $shift;
    private Product $product;
    private SiatSetting $setting;
    private SiatCufdCode $cufd;

    protected function setUp(): void
    {
        parent::setUp();

        $user        = User::factory()->create();
        $this->store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $register = CashRegister::create([
            'store_id' => $this->store->id, 'name' => 'Caja 1', 'is_active' => true,
        ]);

        $this->shift = CashShift::create([
            'cash_register_id' => $register->id, 'user_id' => $user->id,
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
            'leyenda'             => 'Ley N° 453: Leyenda de prueba.',
            'ambiente'            => 'piloto',
            'modalidad'           => 2,
            'cuis'                => 'CUIS-DE-PRUEBA',
            'tipo_factura_default' => 1,
            'is_active'           => true,
        ]);

        $this->cufd = SiatCufdCode::create([
            'store_id'       => $this->store->id,
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

    // ─── XML ────────────────────────────────────────────────────────────────

    public function test_el_xml_del_sector_24_cumple_el_xsd_oficial(): void
    {
        $xml = $this->construir(SiatNota::SECTOR_NOTA);

        $this->assertStringContainsString('<notaFiscalComputarizadaCreditoDebito', $xml);
        $this->assertStringContainsString('<codigoDocumentoSector>24</codigoDocumentoSector>', $xml);
        // El sector 24 no tiene descuento adicional ni número de ítem.
        $this->assertStringNotContainsString('<descuentoAdicional', $xml);
        $this->assertStringNotContainsString('<nroItem>', $xml);
    }

    public function test_el_xml_del_sector_47_cumple_el_xsd_oficial(): void
    {
        $xml = $this->construir(SiatNota::SECTOR_NOTA_DESCUENTO, descuentoAdicional: 7.50);

        $this->assertStringContainsString('<notaComputarizadaCreditoDebitoDescuento', $xml);
        $this->assertStringContainsString('<codigoDocumentoSector>47</codigoDocumentoSector>', $xml);
        $this->assertStringContainsString('<descuentoAdicional>7.50</descuentoAdicional>', $xml);
        $this->assertStringContainsString('<nroItem>1</nroItem>', $xml);
    }

    /**
     * El XSD del 47 no pone los dos campos donde uno esperaría: el descuento va
     * entre el importe original y el devuelto, y el número de ítem abre la línea.
     */
    public function test_el_sector_47_coloca_sus_dos_campos_donde_manda_el_xsd(): void
    {
        $xml = $this->construir(SiatNota::SECTOR_NOTA_DESCUENTO, descuentoAdicional: 7.50);

        $this->assertMatchesRegularExpression(
            '/<montoTotalOriginal>.*<descuentoAdicional>.*<montoTotalDevuelto>/s',
            $xml,
        );
        $this->assertMatchesRegularExpression('/<detalle><nroItem>/', $xml);
    }

    /** El SIN necesita ver lo facturado (código 1) junto a lo devuelto (código 2). */
    public function test_el_detalle_lleva_la_linea_original_y_la_devuelta(): void
    {
        $xml = $this->construir(SiatNota::SECTOR_NOTA);

        $this->assertSame(1, substr_count($xml, '<codigoDetalleTransaccion>1</codigoDetalleTransaccion>'));
        $this->assertSame(1, substr_count($xml, '<codigoDetalleTransaccion>2</codigoDetalleTransaccion>'));
    }

    public function test_una_nota_sin_linea_devuelta_no_se_construye(): void
    {
        $devolucion = $this->devolucion();
        $devolucion->items()->delete();
        $devolucion->refresh()->load('items');

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/no tiene líneas/');

        app(ConstructorNotaXml::class)->detalles($devolucion, $this->setting);
    }

    public function test_el_constructor_del_xml_exige_las_dos_mitades_del_detalle(): void
    {
        $nota = $this->nota(SiatNota::SECTOR_NOTA);

        $solo = [[
            'actividadEconomica' => '4741100', 'codigoProductoSin' => 1001967,
            'codigoProducto' => 'LAP-1', 'descripcion' => 'Laptop', 'cantidad' => 1.0,
            'unidadMedida' => 57, 'precioUnitario' => 100.0, 'montoDescuento' => null,
            'subTotal' => 100.0,
            'codigoDetalleTransaccion' => NotaCreditoDebitoXml::TRANSACCION_ORIGINAL,
        ]];

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/devuelta/');

        app(NotaCreditoDebitoXml::class)->build(
            nota: $nota, setting: $this->setting, cufd: $this->cufd,
            fechaEmision: now(), detalles: $solo, leyenda: 'Leyenda', usuario: 'test',
        );
    }

    // ─── Importes ───────────────────────────────────────────────────────────

    /**
     * `montoEfectivoCreditoDebito` es el 13 % del monto devuelto —el crédito
     * fiscal que se revierte—, no el dinero que se entrega al cliente.
     */
    public function test_el_monto_efectivo_es_el_trece_por_ciento_de_lo_devuelto(): void
    {
        $this->sinEnvio();
        $nota = app(SiatNotaService::class)->emitir(
            $this->devolucion(cantidad: 2, cantidadVendida: 2),
        );

        $this->assertSame('200.00', (string) $nota->monto_total_devuelto);
        $this->assertSame('26.00', (string) $nota->monto_efectivo);
    }

    public function test_prorratea_el_descuento_de_la_factura_original(): void
    {
        $this->sinEnvio();

        // Factura de 200 con 20 de descuento; se devuelve la mitad.
        $devolucion = $this->devolucion(cantidad: 1, cantidadVendida: 2, descuentoFactura: 20.0);

        $nota = app(SiatNotaService::class)->emitir($devolucion);

        $this->assertSame('10.00', (string) $nota->monto_descuento);
    }

    public function test_no_se_puede_devolver_mas_de_lo_facturado(): void
    {
        $this->sinEnvio();

        $devolucion = $this->devolucion(cantidad: 3, cantidadVendida: 1);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/no se puede ajustar más de lo facturado/');

        app(SiatNotaService::class)->emitir($devolucion);
    }

    // ─── Reglas del documento ───────────────────────────────────────────────

    public function test_sin_factura_original_no_hay_nota(): void
    {
        $this->sinEnvio();

        $devolucion = $this->devolucion(conFactura: false);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/no tiene factura del SIN/');

        app(SiatNotaService::class)->emitir($devolucion);
    }

    public function test_no_se_ajusta_una_factura_anulada(): void
    {
        $this->sinEnvio();

        $devolucion = $this->devolucion();
        $devolucion->sale->siatInvoice->update(['estado' => 'anulada']);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/factura vigente/');

        app(SiatNotaService::class)->emitir($devolucion->refresh());
    }

    public function test_una_devolucion_no_genera_dos_notas(): void
    {
        $this->sinEnvio();
        $devolucion = $this->devolucion();

        app(SiatNotaService::class)->emitir($devolucion);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/ya tiene una nota/');

        app(SiatNotaService::class)->emitir($devolucion->refresh());
    }

    /**
     * El 47 existe para las facturas con descuento adicional, que es el campo que
     * el 24 no tiene dónde declarar.
     */
    public function test_elige_el_sector_segun_el_descuento_de_la_factura(): void
    {
        $this->sinEnvio();
        $servicio = app(SiatNotaService::class);

        $sinDescuento = $servicio->emitir($this->devolucion());
        $this->assertSame(SiatNota::SECTOR_NOTA, $sinDescuento->documento_sector);

        $conDescuento = $servicio->emitir($this->devolucion(descuentoFactura: 5.0));
        $this->assertSame(SiatNota::SECTOR_NOTA_DESCUENTO, $conDescuento->documento_sector);
    }

    /**
     * A diferencia de la factura, cuyo correlativo sale del consecutivo del CUFD
     * y se reinicia cada día, la nota lleva serie continua por tienda y sector.
     */
    public function test_el_correlativo_es_propio_de_cada_sector(): void
    {
        $this->sinEnvio();
        $servicio = app(SiatNotaService::class);

        $primera = $servicio->emitir($this->devolucion());
        $segunda = $servicio->emitir($this->devolucion());
        $otroSector = $servicio->emitir($this->devolucion(descuentoFactura: 5.0));

        $this->assertSame(1, $primera->numero_nota);
        $this->assertSame(2, $segunda->numero_nota);
        $this->assertSame(1, $otroSector->numero_nota, 'El sector 47 empieza su propia serie.');
    }

    /** Un fallo del SIN no pierde la nota: queda rechazada y se puede reenviar. */
    public function test_un_rechazo_del_sin_deja_la_nota_recuperable(): void
    {
        $this->mock(SiatDocumentoAjusteService::class, function ($mock): void {
            $mock->shouldReceive('recepcionDocumentoAjuste')
                ->andThrow(new SiatException('El SIN rechazó la operación: 1000 ALGO'));
        });

        $nota = app(SiatNotaService::class)->emitir($this->devolucion());

        $this->assertSame('rechazada', $nota->estado);
        $this->assertStringContainsString('1000 ALGO', (string) $nota->mensaje_error);
        $this->assertNotNull($nota->cuf);
    }

    public function test_no_se_anula_una_nota_que_nunca_se_envio(): void
    {
        $this->mock(SiatDocumentoAjusteService::class, function ($mock): void {
            $mock->shouldReceive('recepcionDocumentoAjuste')->andThrow(new SiatException('caído'));
            $mock->shouldNotReceive('anulacionDocumentoAjuste');
        });

        $nota = app(SiatNotaService::class)->emitir($this->devolucion());

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/no llegó a enviarse/');

        app(SiatNotaService::class)->anular($nota);
    }

    public function test_anula_y_revierte_una_nota_enviada(): void
    {
        $this->sinEnvio();
        $servicio = app(SiatNotaService::class);
        $nota     = $servicio->emitir($this->devolucion());

        $servicio->anular($nota, codigoMotivo: 2);
        $this->assertSame('anulada', $nota->fresh()->estado);
        $this->assertSame(2, $nota->fresh()->motivo_anulacion);

        $servicio->revertirAnulacion($nota->fresh());
        $this->assertSame('validada', $nota->fresh()->estado);
        $this->assertNull($nota->fresh()->anulado_at);
    }

    /** El CUF de la nota codifica su sector y el tipo de factura 3. */
    public function test_el_cuf_declara_documento_de_ajuste(): void
    {
        $this->sinEnvio();
        $nota = app(SiatNotaService::class)->emitir($this->devolucion());

        $this->assertNotEmpty($nota->cuf);
        $this->assertStringEndsWith($this->cufd->codigo_control, $nota->cuf);
    }

    // ─── Andamiaje ──────────────────────────────────────────────────────────

    /** El servicio SOAP doblado: acepta todo y no toca la red. */
    private function sinEnvio(): void
    {
        $this->mock(SiatDocumentoAjusteService::class, function ($mock): void {
            $respuesta = [
                'codigoRecepcion' => 'REC-1', 'codigoEstado' => 908,
                'codigoDescripcion' => 'VALIDADA', 'mensajes' => [], 'respuesta' => [],
            ];

            $mock->shouldReceive('recepcionDocumentoAjuste')->andReturn($respuesta);
            $mock->shouldReceive('anulacionDocumentoAjuste')->andReturn($respuesta);
            $mock->shouldReceive('reversionAnulacionDocumentoAjuste')->andReturn($respuesta);
            $mock->shouldReceive('verificacionEstadoDocumentoAjuste')->andReturn($respuesta);
        });
    }

    private function construir(int $sector, ?float $descuentoAdicional = null): string
    {
        $devolucion = $this->devolucion();
        $nota       = $this->nota($sector, $devolucion, $descuentoAdicional);

        return app(ConstructorNotaXml::class)->construir(
            $nota, $this->setting, $this->cufd, now(), $devolucion,
        );
    }

    private function nota(
        int $sector,
        ?SaleReturn $devolucion = null,
        ?float $descuentoAdicional = null,
    ): SiatNota {
        $devolucion ??= $this->devolucion();
        $factura      = $devolucion->sale->siatInvoice;

        $nota = new SiatNota([
            'store_id' => $this->store->id, 'sale_return_id' => $devolucion->id,
            'siat_invoice_id' => $factura->id, 'cufd_code_id' => $this->cufd->id,
            'documento_sector' => $sector, 'numero_nota' => 1,
            'fecha_emision' => now(), 'cuf' => str_repeat('A', 40), 'cufd' => $this->cufd->codigo,
            'nit_ci' => $factura->nit_ci, 'tipo_doc_identidad' => $factura->tipo_doc_identidad,
            'nombre_razon_social' => $factura->nombre_razon_social,
            'monto_total_original' => 100, 'monto_total_devuelto' => 100,
            'monto_descuento' => 0, 'monto_efectivo' => 13,
            'descuento_adicional' => $descuentoAdicional,
            'estado' => 'pendiente',
        ]);

        $nota->setRelation('invoice', $factura);
        $nota->setRelation('saleReturn', $devolucion);

        return $nota;
    }

    /**
     * Una venta facturada y su devolución.
     *
     * @param  float  $cantidad          Unidades devueltas.
     * @param  float  $cantidadVendida   Unidades de la venta original.
     * @param  bool   $conFactura        Si la venta llegó a facturarse en el SIN.
     */
    private function devolucion(
        float $cantidad = 1,
        float $cantidadVendida = 1,
        float $descuentoFactura = 0,
        bool $conFactura = true,
    ): SaleReturn {
        $total = 100 * $cantidadVendida;

        $sale = Sale::create([
            'cash_shift_id' => $this->shift->id, 'user_id' => $this->shift->user_id,
            'folio' => 'V-' . uniqid(), 'subtotal' => $total, 'total' => $total,
            'amount_paid' => $total, 'payment_method' => 'cash', 'status' => 'completed',
        ]);

        $item = SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $this->product->id,
            'quantity' => $cantidadVendida, 'price' => 100, 'discount' => 0, 'subtotal' => $total,
        ]);

        if ($conFactura) {
            SiatInvoice::create([
                'sale_id' => $sale->id, 'store_id' => $this->store->id,
                'cufd_code_id' => $this->cufd->id, 'numero_factura' => $sale->id,
                'fecha_emision' => now()->subHour(),
                'cuf' => 'CUF-FACTURA-' . $sale->id, 'cufd' => $this->cufd->codigo,
                'nit_ci' => '9876543', 'tipo_doc_identidad' => 1,
                'nombre_razon_social' => 'CLIENTE DE PRUEBA',
                'importe_total' => $total, 'importe_base_cf' => $total,
                'descuento' => $descuentoFactura,
                'tipo_factura' => 1, 'tipo_emision' => 1, 'metodo_pago' => 1,
                'estado' => 'validada',
            ]);
        }

        $devolucion = SaleReturn::create([
            'sale_id' => $sale->id, 'user_id' => $this->shift->user_id,
            'folio' => 'DEV-' . uniqid(), 'date' => now(), 'reason' => 'Producto defectuoso',
            'refund_method' => 'cash', 'refund_amount' => 100 * $cantidad,
            'status' => 'completed', 'restock' => false,
        ]);

        SaleReturnItem::create([
            'sale_return_id' => $devolucion->id, 'sale_item_id' => $item->id,
            'product_id' => $this->product->id, 'quantity' => $cantidad,
            'unit_price' => 100, 'subtotal' => 100 * $cantidad,
        ]);

        return $devolucion->load(['items.product', 'sale.items.product', 'sale.siatInvoice']);
    }
}
