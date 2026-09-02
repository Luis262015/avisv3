<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\SiatCufdCode;
use App\Models\SiatNota;
use App\Models\SiatSetting;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMElement;

/**
 * Construye el XML de la Nota de Crédito-Débito y lo valida contra el XSD oficial.
 *
 * Cubre los dos documentos sector de la actividad: el 24 y el 47 (con descuento).
 * Son el mismo esquema salvo por dos campos —`descuentoAdicional` en la cabecera
 * y `nroItem` en cada línea—, así que comparten constructor y se separan solo
 * donde el XSD lo obliga.
 *
 * Igual que en la factura computarizada, aquí no hay firma XAdES: el esquema
 * electrónico exige `<ds:Signature>` y el computarizado no.
 *
 * @see resources/siat/notaComputarizadaCreditoDebito.xsd (paquete CreditoDebitoXML.zip)
 * @see resources/siat/notaComputarizadaCreditoDebitoDescuento.xsd (CreditoDebitoDescuentoXML.zip)
 */
class NotaCreditoDebitoXml
{
    private const XMLNS = 'http://www.w3.org/2000/xmlns/';
    private const XSI   = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * `codigoDetalleTransaccion`: 1 es la línea tal y como se facturó, 2 la parte
     * que se devuelve. El XSD exige al menos una de cada.
     */
    public const TRANSACCION_ORIGINAL = 1;
    public const TRANSACCION_DEVUELTA = 2;

    /**
     * Elemento raíz y XSD de cada documento sector. El nombre de la raíz no sigue
     * el mismo patrón en los dos: el del 47 no lleva el prefijo "notaFiscal".
     *
     * @var array<int, array{raiz: string, xsd: string}>
     */
    private const ESQUEMAS = [
        SiatNota::SECTOR_NOTA => [
            'raiz' => 'notaFiscalComputarizadaCreditoDebito',
            'xsd'  => 'notaComputarizadaCreditoDebito.xsd',
        ],
        SiatNota::SECTOR_NOTA_DESCUENTO => [
            'raiz' => 'notaComputarizadaCreditoDebitoDescuento',
            'xsd'  => 'notaComputarizadaCreditoDebitoDescuento.xsd',
        ],
    ];

    /**
     * @param  list<array{
     *     actividadEconomica: string, codigoProductoSin: int, codigoProducto: string,
     *     descripcion: string, cantidad: float, unidadMedida: int, precioUnitario: float,
     *     montoDescuento: float|null, subTotal: float, codigoDetalleTransaccion: int
     * }>  $detalles
     */
    public function build(
        SiatNota $nota,
        SiatSetting $setting,
        SiatCufdCode $cufd,
        CarbonInterface $fechaEmision,
        array $detalles,
        string $leyenda,
        string $usuario,
    ): string {
        $esquema = self::ESQUEMAS[$nota->documento_sector]
            ?? throw new SiatException(
                "El documento sector {$nota->documento_sector} no es una nota de crédito-débito."
            );

        // El XSD declara `minOccurs="2"`: la nota necesita al menos una línea de la
        // transacción original y otra de lo devuelto. Detenerlo aquí evita gastar
        // el correlativo en un envío que el SIN va a rechazar.
        $this->comprobarDetalles($detalles);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $raiz = $dom->createElement($esquema['raiz']);
        $raiz->setAttributeNS(self::XMLNS, 'xmlns:xsi', self::XSI);
        $raiz->setAttributeNS(self::XSI, 'xsi:noNamespaceSchemaLocation', $esquema['xsd']);
        $dom->appendChild($raiz);

        $raiz->appendChild($this->cabecera($dom, $nota, $setting, $cufd, $fechaEmision, $leyenda, $usuario));

        foreach ($detalles as $indice => $detalle) {
            $raiz->appendChild($this->detalle($dom, $detalle, $nota->documento_sector, $indice + 1));
        }

        $xml = $dom->saveXML();

        $this->validar($dom, $esquema['xsd']);

        return $xml;
    }

    /**
     * El SIN pide la foto completa: qué se facturó (código 1) y qué se devuelve
     * (código 2). Sin las dos mitades el XSD no valida.
     *
     * @param  list<array<string, mixed>>  $detalles
     */
    private function comprobarDetalles(array $detalles): void
    {
        $codigos = array_column($detalles, 'codigoDetalleTransaccion');

        if (! in_array(self::TRANSACCION_ORIGINAL, $codigos, true)) {
            throw new SiatException(
                'La nota no incluye ninguna línea de la factura original. '
                . 'El SIN exige el detalle original (código 1) junto al devuelto.'
            );
        }

        if (! in_array(self::TRANSACCION_DEVUELTA, $codigos, true)) {
            throw new SiatException('La nota no incluye ninguna línea devuelta (código 2).');
        }

        if (count($detalles) > 500) {
            throw new SiatException('El XSD admite como mucho 500 líneas de detalle en una nota.');
        }
    }


    public function validar(DOMDocument $dom, string $xsdNombre): void
    {
        $xsd = resource_path("siat/{$xsdNombre}");

        if (! is_file($xsd)) {
            throw new SiatException("No se encuentra el XSD del SIN en {$xsd}.");
        }

        $previo = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valido  = $dom->schemaValidate($xsd);
        $errores = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        if ($valido) {
            return;
        }

        $detalle = collect($errores)
            ->map(fn (\LibXMLError $e) => trim($e->message))
            ->unique()
            ->take(5)
            ->implode(' | ');

        throw new SiatException("El XML de la nota no cumple el XSD del SIN: {$detalle}");
    }

    private function cabecera(
        DOMDocument $dom,
        SiatNota $nota,
        SiatSetting $setting,
        SiatCufdCode $cufd,
        CarbonInterface $fechaEmision,
        string $leyenda,
        string $usuario,
    ): DOMElement {
        $cabecera = $dom->createElement('cabecera');
        $factura  = $nota->invoice;

        // El orden importa: el XSD declara una <xs:sequence>.
        $campos = [
            'nitEmisor'               => preg_replace('/\D/', '', (string) $setting->nit),
            'razonSocialEmisor'       => $setting->razon_social,
            'municipio'               => $setting->municipio,
            'telefono'                => $setting->telefono,
            'numeroNotaCreditoDebito' => $nota->numero_nota,
            'cuf'                     => $nota->cuf,
            'cufd'                    => $cufd->codigo,
            'codigoSucursal'          => (int) $setting->codigo_sucursal,
            // El domicilio del CUFD es el registrado en el Padrón; el local puede
            // estar desactualizado.
            'direccion'               => $cufd->direccion ?: $setting->direccion,
            'codigoPuntoVenta'        => (int) $setting->codigo_punto_venta,
            'fechaEmision'            => $this->fecha($fechaEmision),
            'nombreRazonSocial'       => $nota->nombre_razon_social,
            'codigoTipoDocumentoIdentidad' => (int) $nota->tipo_doc_identidad,
            'numeroDocumento'         => $nota->nit_ci,
            'complemento'             => $nota->complemento,
            'codigoCliente'           => $nota->nit_ci,
            // Los tres datos de la factura que se ajusta. `numeroAutorizacionCuf`
            // es su CUF: es lo que ata la nota al documento original.
            'numeroFactura'           => $factura?->numero_factura,
            'numeroAutorizacionCuf'   => $factura?->cuf,
            'fechaEmisionFactura'     => $factura?->fecha_emision
                ? $this->fecha($factura->fecha_emision)
                : null,
            'montoTotalOriginal'      => $this->monto($nota->monto_total_original),
        ];

        // El sector 47 intercala el descuento adicional de la factura original
        // entre el importe original y el devuelto, no al final de la cabecera.
        if ($nota->documento_sector === SiatNota::SECTOR_NOTA_DESCUENTO) {
            $campos['descuentoAdicional'] = $this->montoOpcional($nota->descuento_adicional);
        }

        $campos += [
            'montoTotalDevuelto'      => $this->monto($nota->monto_total_devuelto),
            'montoDescuentoCreditoDebito' => $this->montoOpcional($nota->monto_descuento),
            // No es el efectivo entregado al cliente: es el 13 % del monto
            // devuelto, o sea el crédito fiscal que se revierte.
            'montoEfectivoCreditoDebito' => $this->monto($nota->monto_efectivo),
            'codigoExcepcion'         => $nota->codigo_excepcion,
            'leyenda'                 => $leyenda,
            'usuario'                 => $usuario,
            'codigoDocumentoSector'   => $nota->documento_sector,
        ];

        foreach ($campos as $nombre => $valor) {
            $cabecera->appendChild($this->campo($dom, $nombre, $valor));
        }

        return $cabecera;
    }

    /** @param array<string, mixed> $detalle */
    private function detalle(DOMDocument $dom, array $detalle, int $documentoSector, int $nroItem): DOMElement
    {
        $nodo = $dom->createElement('detalle');

        $campos = [];

        // En el sector 47 el número de ítem abre la línea; es el único campo del
        // detalle que se adelanta a la actividad económica.
        if ($documentoSector === SiatNota::SECTOR_NOTA_DESCUENTO) {
            $campos['nroItem'] = $nroItem;
        }

        $campos += [
            'actividadEconomica' => $detalle['actividadEconomica'],
            'codigoProductoSin'  => (int) $detalle['codigoProductoSin'],
            'codigoProducto'     => $detalle['codigoProducto'],
            'descripcion'        => $detalle['descripcion'],
            'cantidad'           => $this->monto($detalle['cantidad']),
            'unidadMedida'       => (int) $detalle['unidadMedida'],
            'precioUnitario'     => $this->monto($detalle['precioUnitario']),
            'montoDescuento'     => $this->montoOpcional($detalle['montoDescuento'] ?? null),
            'subTotal'           => $this->monto($detalle['subTotal']),
            'codigoDetalleTransaccion' => (int) $detalle['codigoDetalleTransaccion'],
        ];

        foreach ($campos as $nombre => $valor) {
            $nodo->appendChild($this->campo($dom, $nombre, $valor));
        }

        return $nodo;
    }

    /**
     * Los campos vacíos van como `xsi:nil="true"` y no como elemento vacío: el
     * XSD los declara `nillable` pero con `minLength 1`.
     */
    private function campo(DOMDocument $dom, string $nombre, mixed $valor): DOMElement
    {
        if ($valor === null || $valor === '') {
            $nodo = $dom->createElement($nombre);
            $nodo->setAttributeNS(self::XSI, 'xsi:nil', 'true');

            return $nodo;
        }

        return $dom->createElement($nombre, htmlspecialchars((string) $valor, ENT_XML1, 'UTF-8'));
    }

    /** En hora de Bolivia: es la que entra en el CUF y el SIN contrasta ambas. */
    private function fecha(CarbonInterface $fecha): string
    {
        return $fecha->copy()->setTimezone(config('siat.timezone'))->format('Y-m-d\TH:i:s.v');
    }

    private function monto(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    /**
     * Los importes de descuento son `minInclusive 0` y nillable: un cero se puede
     * enviar, pero es más limpio omitirlo cuando no hay descuento.
     */
    private function montoOpcional(mixed $valor): ?string
    {
        return $valor === null || (float) $valor <= 0.0 ? null : $this->monto($valor);
    }
}
