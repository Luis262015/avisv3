<?php

namespace App\Services\Siat;

use Carbon\CarbonInterface;

/**
 * Generación del Código Único de Factura (CUF) según la especificación oficial
 * del SIAT — Impuestos Nacionales, Bolivia.
 *
 * @see https://siatinfo.impuestos.gob.bo/index.php/facturacion-en-linea/algoritmos-utilizados/generacion-cuf
 * @see https://siatinfo.impuestos.gob.bo/index.php/facturacion-en-linea/algoritmos-utilizados/algoritmo-modulo-11
 *
 * El procedimiento es determinista, sin hashes:
 *   1. Concatenar 9 campos numéricos con relleno de ceros a la izquierda → 53 dígitos.
 *   2. Anexar el dígito autoverificador Módulo 11 → 54 dígitos.
 *   3. Convertir esa cadena, leída como un entero decimal, a Base 16.
 *   4. Concatenar el código de control que el SIN entrega junto con el CUFD.
 *
 * Verificado contra el ejemplo oficial en CufGeneratorTest.
 */
class CufGenerator
{
    // ─── Modalidad ───────────────────────────────────────────────────────────
    public const MODALIDAD_ELECTRONICA  = 1;
    public const MODALIDAD_COMPUTARIZADA = 2;
    public const MODALIDAD_PORTAL_WEB   = 3;

    // ─── Tipo de emisión ─────────────────────────────────────────────────────
    public const EMISION_ONLINE  = 1;
    public const EMISION_OFFLINE = 2;
    public const EMISION_MASIVA  = 3;

    // ─── Tipo de factura / documento de ajuste ───────────────────────────────
    public const FACTURA_CON_CREDITO_FISCAL = 1;
    public const FACTURA_SIN_CREDITO_FISCAL = 2;
    public const DOCUMENTO_AJUSTE           = 3;

    // ─── Tipo de documento sector ────────────────────────────────────────────
    public const SECTOR_COMPRA_VENTA   = 1;
    public const SECTOR_NOTA_CRED_DEB  = 24;

    /**
     * Longitud de cada campo, en el orden exacto de la especificación.
     * La suma es 53; el dígito autoverificador lleva el total a 54.
     */
    private const LONGITUDES = [
        'nit'                 => 13,
        'fecha'               => 17, // yyyyMMddHHmmssSSS
        'sucursal'            => 4,
        'modalidad'           => 1,
        'tipoEmision'         => 1,
        'tipoFactura'         => 1,
        'tipoDocumentoSector' => 2,
        'numeroFactura'       => 10,
        'puntoVenta'          => 4,
    ];

    public function generate(
        string $nit,
        CarbonInterface $fechaEmision,
        int $sucursal,
        int $modalidad,
        int $tipoEmision,
        int $tipoFactura,
        int $tipoDocumentoSector,
        int $numeroFactura,
        int $puntoVenta,
        string $codigoControl,
    ): string {
        $cadena = $this->buildBaseString(
            $nit,
            $fechaEmision,
            $sucursal,
            $modalidad,
            $tipoEmision,
            $tipoFactura,
            $tipoDocumentoSector,
            $numeroFactura,
            $puntoVenta,
        );

        $conDigito = $cadena . $this->modulo11($cadena);

        return $this->decimalToBase16($conDigito) . strtoupper($codigoControl);
    }

    /**
     * Los 53 dígitos previos al autoverificador.
     */
    public function buildBaseString(
        string $nit,
        CarbonInterface $fechaEmision,
        int $sucursal,
        int $modalidad,
        int $tipoEmision,
        int $tipoFactura,
        int $tipoDocumentoSector,
        int $numeroFactura,
        int $puntoVenta,
    ): string {
        $campos = [
            'nit'                 => preg_replace('/\D/', '', $nit),
            // Milisegundos incluidos: yyyyMMddHHmmssSSS.
            'fecha'               => $fechaEmision->format('YmdHisv'),
            'sucursal'            => (string) $sucursal,
            'modalidad'           => (string) $modalidad,
            'tipoEmision'         => (string) $tipoEmision,
            'tipoFactura'         => (string) $tipoFactura,
            'tipoDocumentoSector' => (string) $tipoDocumentoSector,
            'numeroFactura'       => (string) $numeroFactura,
            'puntoVenta'          => (string) $puntoVenta,
        ];

        $cadena = '';

        foreach (self::LONGITUDES as $campo => $longitud) {
            $valor = $campos[$campo];

            if (strlen($valor) > $longitud) {
                throw new \InvalidArgumentException(
                    "El campo \"{$campo}\" del CUF excede su longitud de {$longitud} dígitos: \"{$valor}\"."
                );
            }

            $cadena .= str_pad($valor, $longitud, '0', STR_PAD_LEFT);
        }

        return $cadena;
    }

    /**
     * Dígito autoverificador Módulo 11.
     *
     * Port directo del algoritmo publicado por el SIN, con sus parámetros para el
     * CUF: numDig = 1, limMult = 9, x10 = false.
     *
     * Se recorre la cadena de derecha a izquierda multiplicando cada dígito por un
     * peso que arranca en 2 y vuelve a 2 al superar el límite. Un resto de 10 se
     * representa como "1" y uno de 11 como "0" (este último es inalcanzable con
     * módulo 11, pero se conserva por fidelidad al algoritmo original).
     */
    public function modulo11(string $cadena, int $limiteMultiplicador = 9): string
    {
        $suma = 0;
        $multiplicador = 2;

        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += $multiplicador * (int) $cadena[$i];

            if (++$multiplicador > $limiteMultiplicador) {
                $multiplicador = 2;
            }
        }

        $digito = $suma % 11;

        return match (true) {
            $digito === 10 => '1',
            $digito === 11 => '0',
            default        => (string) $digito,
        };
    }

    /**
     * Convierte la cadena de 54 dígitos, leída como entero decimal, a Base 16.
     *
     * Se usa bcmath porque el valor desborda ampliamente PHP_INT_MAX.
     */
    public function decimalToBase16(string $decimal): string
    {
        $decimal = ltrim($decimal, '0');

        if ($decimal === '') {
            return '0';
        }

        $hex = '';

        while (bccomp($decimal, '0') > 0) {
            $resto   = bcmod($decimal, '16');
            $hex     = strtoupper(dechex((int) $resto)) . $hex;
            $decimal = bcdiv($decimal, '16', 0);
        }

        return $hex;
    }
}
