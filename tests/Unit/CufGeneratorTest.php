<?php

namespace Tests\Unit;

use App\Services\Siat\CufGenerator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Verifica el generador de CUF contra el ejemplo publicado por Impuestos Nacionales.
 *
 * @see https://siatinfo.impuestos.gob.bo/index.php/facturacion-en-linea/algoritmos-utilizados/generacion-cuf
 */
class CufGeneratorTest extends TestCase
{
    private CufGenerator $cuf;

    /** Datos del ejemplo oficial. */
    private const NIT             = '123456789';
    private const FECHA           = '20190113163721231'; // yyyyMMddHHmmssSSS
    private const CADENA_53       = '00001234567892019011316372123100001110100000000010000';
    private const CADENA_54       = '000012345678920190113163721231000011101000000000100001';
    private const BASE_16         = '8727F63A15F8976591FDDE5B387C5D015A29E06A1';
    private const CODIGO_CONTROL  = 'A19E23EF34124CD';
    private const CUF_ESPERADO    = '8727F63A15F8976591FDDE5B387C5D015A29E06A1A19E23EF34124CD';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cuf = new CufGenerator();
    }

    private function fechaEjemplo(): Carbon
    {
        return Carbon::createFromFormat('YmdHis.v', '20190113163721.231');
    }

    public function test_it_builds_the_53_digit_base_string_from_the_official_example(): void
    {
        $cadena = $this->cuf->buildBaseString(
            nit: self::NIT,
            fechaEmision: $this->fechaEjemplo(),
            sucursal: 0,
            modalidad: CufGenerator::MODALIDAD_ELECTRONICA,
            tipoEmision: CufGenerator::EMISION_ONLINE,
            tipoFactura: CufGenerator::FACTURA_CON_CREDITO_FISCAL,
            tipoDocumentoSector: CufGenerator::SECTOR_COMPRA_VENTA,
            numeroFactura: 1,
            puntoVenta: 0,
        );

        $this->assertSame(53, strlen($cadena));
        $this->assertSame(self::CADENA_53, $cadena);
    }

    public function test_the_modulo_11_check_digit_matches_the_official_example(): void
    {
        $digito = $this->cuf->modulo11(self::CADENA_53);

        $this->assertSame('1', $digito);
        $this->assertSame(self::CADENA_54, self::CADENA_53 . $digito);
    }

    public function test_it_converts_the_54_digit_string_to_base_16(): void
    {
        $this->assertSame(self::BASE_16, $this->cuf->decimalToBase16(self::CADENA_54));
    }

    public function test_it_reproduces_the_official_cuf_end_to_end(): void
    {
        $cuf = $this->cuf->generate(
            nit: self::NIT,
            fechaEmision: $this->fechaEjemplo(),
            sucursal: 0,
            modalidad: CufGenerator::MODALIDAD_ELECTRONICA,
            tipoEmision: CufGenerator::EMISION_ONLINE,
            tipoFactura: CufGenerator::FACTURA_CON_CREDITO_FISCAL,
            tipoDocumentoSector: CufGenerator::SECTOR_COMPRA_VENTA,
            numeroFactura: 1,
            puntoVenta: 0,
            codigoControl: self::CODIGO_CONTROL,
        );

        $this->assertSame(self::CUF_ESPERADO, $cuf);
    }

    public function test_the_date_is_formatted_with_milliseconds(): void
    {
        $cadena = $this->cuf->buildBaseString(
            nit: self::NIT,
            fechaEmision: $this->fechaEjemplo(),
            sucursal: 0,
            modalidad: 1,
            tipoEmision: 1,
            tipoFactura: 1,
            tipoDocumentoSector: 1,
            numeroFactura: 1,
            puntoVenta: 0,
        );

        // Los 17 dígitos que siguen al NIT (13) son la fecha.
        $this->assertSame(self::FECHA, substr($cadena, 13, 17));
    }

    public function test_it_rejects_a_field_longer_than_its_specification(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->cuf->buildBaseString(
            nit: self::NIT,
            fechaEmision: $this->fechaEjemplo(),
            sucursal: 0,
            modalidad: 1,
            tipoEmision: 1,
            tipoFactura: 1,
            tipoDocumentoSector: 1,
            numeroFactura: 12345678901, // 11 dígitos, el máximo son 10
            puntoVenta: 0,
        );
    }
}
