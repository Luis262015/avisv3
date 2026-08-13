<?php

namespace Tests\Unit;

use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\SiatSetting;
use App\Services\Siat\FacturaComputarizadaXml;
use App\Services\Siat\SiatException;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * El XML se valida contra el XSD oficial del SIN
 * (resources/siat/facturaComputarizadaCompraVenta.xsd), así que estos tests
 * comprueban de verdad el contrato y no una idea nuestra de él.
 */
class FacturaComputarizadaXmlTest extends TestCase
{
    private FacturaComputarizadaXml $xml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->xml = new FacturaComputarizadaXml();
    }

    private function setting(array $atributos = []): SiatSetting
    {
        return new SiatSetting(array_merge([
            'nit'                 => '1234567890',
            'razon_social'        => 'EMPRESA DE PRUEBA SRL',
            'municipio'           => 'LA PAZ',
            'telefono'            => null,
            'codigo_sucursal'     => 0,
            'codigo_punto_venta'  => 0,
            'direccion'           => 'AV. SIEMPRE VIVA 123',
            'actividad_economica' => '4741100',
        ], $atributos));
    }

    private function cufd(array $atributos = []): SiatCufdCode
    {
        return new SiatCufdCode(array_merge([
            'codigo'         => 'ABC123',
            'codigo_control' => '23E26C80881BF74',
            'direccion'      => 'ALTURA RELOJ DE LA PEREZ Nro. 819',
        ], $atributos));
    }

    private function invoice(array $atributos = []): SiatInvoice
    {
        return new SiatInvoice(array_merge([
            'numero_factura'      => 7,
            'cuf'                 => '1D9B8B69B433E44A906A66F0556EC41622D2A8A9A8123E26C80881BF74',
            'cufd'                => 'ABC123',
            'nit_ci'              => '1234567890',
            'tipo_doc_identidad'  => 5,
            'nombre_razon_social' => 'CLIENTE DE PRUEBA',
            'importe_total'       => 20.00,
            'importe_base_cf'     => 20.00,
            'descuento'           => 0,
            'metodo_pago'         => 1,
        ], $atributos));
    }

    /** @return list<array<string, mixed>> */
    private function detalles(array $sobrescribir = []): array
    {
        return [array_merge([
            'actividadEconomica' => '4741100',
            'codigoProductoSin'  => 1001966,
            'codigoProducto'     => 'SKU-1',
            'descripcion'        => 'Producto de prueba',
            'cantidad'           => 2.0,
            'unidadMedida'       => 57,
            'precioUnitario'     => 10.0,
            'montoDescuento'     => 0.0,
            'subTotal'           => 20.0,
        ], $sobrescribir)];
    }

    private function build(array $args = []): string
    {
        return $this->xml->build(
            invoice: $args['invoice'] ?? $this->invoice(),
            setting: $args['setting'] ?? $this->setting(),
            cufd: $args['cufd'] ?? $this->cufd(),
            fechaEmision: $args['fecha'] ?? Carbon::parse('2026-08-06 10:30:00', 'America/La_Paz'),
            detalles: $args['detalles'] ?? $this->detalles(),
            leyenda: $args['leyenda'] ?? 'Ley N° 453: Leyenda de prueba.',
            usuario: $args['usuario'] ?? 'cajero',
        );
    }

    public function test_it_produces_xml_that_validates_against_the_official_xsd(): void
    {
        // build() valida internamente: si no cumpliera, lanzaría SiatException.
        $xml = $this->build();

        $this->assertStringContainsString('<facturaComputarizadaCompraVenta', $xml);
        $this->assertStringContainsString('<nitEmisor>1234567890</nitEmisor>', $xml);
        $this->assertStringContainsString('<codigoDocumentoSector>1</codigoDocumentoSector>', $xml);
    }

    /** La Computarizada usa el XSD sin firma; la Electrónica es la que la exige. */
    public function test_it_does_not_include_a_digital_signature(): void
    {
        $this->assertStringNotContainsString('Signature', $this->build());
    }

    public function test_it_marks_empty_fields_as_nil_instead_of_leaving_them_blank(): void
    {
        $xml = $this->build();

        // telefono viene null en la configuración; un <telefono></telefono> vacío
        // incumpliría el minLength del XSD.
        $this->assertMatchesRegularExpression('/<telefono[^>]*xsi:nil="true"/', $xml);
        $this->assertMatchesRegularExpression('/<cafc[^>]*xsi:nil="true"/', $xml);
    }

    /**
     * El SIN rechaza con `1037 EL NUMERO DOCUMENTO DE TIPO NIT NO ES VALIDO ...
     * para codigo excepcion 0` cuando el NIT del comprador no está en su registro.
     * El código de excepción 1 es la forma prevista de emitir igualmente.
     */
    public function test_it_declares_the_exception_code_when_the_buyer_nit_does_not_validate(): void
    {
        $xml = $this->build(['invoice' => $this->invoice(['codigo_excepcion' => 1])]);

        $this->assertStringContainsString('<codigoExcepcion>1</codigoExcepcion>', $xml);
    }

    /** Sin excepción el campo va nulo: rellenarlo de más también se rechaza. */
    public function test_the_exception_code_is_nil_by_default(): void
    {
        $this->assertMatchesRegularExpression('/<codigoExcepcion[^>]*xsi:nil="true"/', $this->build());
    }

    public function test_it_prefers_the_address_the_sin_returned_with_the_cufd(): void
    {
        $xml = $this->build();

        $this->assertStringContainsString('ALTURA RELOJ DE LA PEREZ Nro. 819', $xml);
        $this->assertStringNotContainsString('AV. SIEMPRE VIVA 123', $xml);
    }

    public function test_it_falls_back_to_the_configured_address_when_the_cufd_has_none(): void
    {
        $xml = $this->build(['cufd' => $this->cufd(['direccion' => null])]);

        $this->assertStringContainsString('AV. SIEMPRE VIVA 123', $xml);
    }

    public function test_it_writes_the_emission_date_in_bolivian_time(): void
    {
        $xml = $this->build([
            // 14:30 UTC son las 10:30 en Bolivia; el SIN espera la hora local.
            'fecha' => Carbon::parse('2026-08-06 14:30:00', 'UTC'),
        ]);

        $this->assertStringContainsString('<fechaEmision>2026-08-06T10:30:00', $xml);
    }

    public function test_it_writes_amounts_with_two_decimals(): void
    {
        $xml = $this->build();

        $this->assertStringContainsString('<montoTotal>20.00</montoTotal>', $xml);
        $this->assertStringContainsString('<precioUnitario>10.00</precioUnitario>', $xml);
    }

    public function test_it_escapes_characters_that_would_break_the_xml(): void
    {
        $xml = $this->build([
            'detalles' => $this->detalles(['descripcion' => 'Tornillos < 5" & tuercas']),
        ]);

        $this->assertStringContainsString('Tornillos &lt; 5" &amp; tuercas', $xml);
    }

    public function test_it_emits_one_detalle_per_line(): void
    {
        $detalles = array_merge(
            $this->detalles(['codigoProducto' => 'SKU-1']),
            $this->detalles(['codigoProducto' => 'SKU-2']),
        );

        $this->assertSame(2, substr_count($this->build(['detalles' => $detalles]), '<detalle>'));
    }

    public function test_it_rejects_an_invoice_without_detail(): void
    {
        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/al menos una línea/');

        $this->build(['detalles' => []]);
    }

    public function test_it_reports_which_field_breaks_the_schema(): void
    {
        $this->expectException(SiatException::class);
        // montoTotal tiene minExclusive 0: un total de 0 no es una factura válida.
        $this->expectExceptionMessageMatches('/no cumple el XSD/');

        $this->build(['invoice' => $this->invoice(['importe_total' => 0])]);
    }
}
