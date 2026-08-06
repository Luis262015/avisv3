<?php

namespace App\Services\Siat;

use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\SiatSetting;
use Carbon\CarbonInterface;
use DOMDocument;

/**
 * Construye el XML de "facturaComputarizadaCompraVenta" y lo valida contra el
 * XSD oficial antes de que salga del sistema.
 *
 * La modalidad Computarizada usa el mismo esquema que la Electrónica salvo por
 * un detalle decisivo: el XSD electrónico exige `<ds:Signature>` y el
 * computarizado no. Por eso aquí no hay firma XAdES ni certificado digital.
 *
 * @see resources/siat/facturaComputarizadaCompraVenta.xsd (paquete CompraVentaXML.zip del SIN)
 */
class FacturaComputarizadaXml
{
    /** El XSD fija este valor para Factura Compra Venta. */
    private const DOCUMENTO_SECTOR = 1;

    private const XMLNS = 'http://www.w3.org/2000/xmlns/';
    private const XSI   = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * @param  list<array{
     *     actividadEconomica: string, codigoProductoSin: int, codigoProducto: string,
     *     descripcion: string, cantidad: float, unidadMedida: int, precioUnitario: float,
     *     montoDescuento: float, subTotal: float
     * }>  $detalles
     */
    public function build(
        SiatInvoice $invoice,
        SiatSetting $setting,
        SiatCufdCode $cufd,
        CarbonInterface $fechaEmision,
        array $detalles,
        string $leyenda,
        string $usuario,
    ): string {
        if ($detalles === []) {
            throw new SiatException('La factura no tiene detalle: el SIN exige al menos una línea.');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $raiz = $dom->createElement('facturaComputarizadaCompraVenta');
        // Declarar el namespace con setAttribute lo dejaría como un atributo más y
        // el validador lo rechaza; tiene que ir por setAttributeNS.
        $raiz->setAttributeNS(self::XMLNS, 'xmlns:xsi', self::XSI);
        $raiz->setAttributeNS(self::XSI, 'xsi:noNamespaceSchemaLocation', 'facturaComputarizadaCompraVenta.xsd');
        $dom->appendChild($raiz);

        $raiz->appendChild($this->cabecera($dom, $invoice, $setting, $cufd, $fechaEmision, $leyenda, $usuario));

        foreach ($detalles as $detalle) {
            $raiz->appendChild($this->detalle($dom, $detalle));
        }

        $xml = $dom->saveXML();

        $this->validar($dom);

        return $xml;
    }

    /**
     * Valida contra el XSD del SIN. Sale más barato descubrir aquí un campo mal
     * formado que en el rechazo del servicio, que no dice qué elemento falla.
     */
    public function validar(DOMDocument $dom): void
    {
        $xsd = resource_path('siat/facturaComputarizadaCompraVenta.xsd');

        if (! is_file($xsd)) {
            throw new SiatException("No se encuentra el XSD del SIN en {$xsd}.");
        }

        $previo = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valido = $dom->schemaValidate($xsd);
        $errores = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        if ($valido) {
            return;
        }

        $detalle = collect($errores)
            ->map(fn(\LibXMLError $e) => trim($e->message))
            ->unique()
            ->take(5)
            ->implode(' | ');

        throw new SiatException("El XML de la factura no cumple el XSD del SIN: {$detalle}");
    }

    private function cabecera(
        DOMDocument $dom,
        SiatInvoice $invoice,
        SiatSetting $setting,
        SiatCufdCode $cufd,
        CarbonInterface $fechaEmision,
        string $leyenda,
        string $usuario,
    ): \DOMElement {
        $cabecera = $dom->createElement('cabecera');

        // El orden importa: el XSD declara una <xs:sequence>.
        $campos = [
            'nitEmisor'                   => preg_replace('/\D/', '', (string) $setting->nit),
            'razonSocialEmisor'           => $setting->razon_social,
            'municipio'                   => $setting->municipio,
            'telefono'                    => $setting->telefono,
            'numeroFactura'               => $invoice->numero_factura,
            'cuf'                         => $invoice->cuf,
            'cufd'                        => $cufd->codigo,
            'codigoSucursal'              => (int) $setting->codigo_sucursal,
            // El domicilio que el SIN devuelve con el CUFD es el registrado; el de
            // la configuración local puede estar sin llenar o desactualizado.
            'direccion'                   => $cufd->direccion ?: $setting->direccion,
            'codigoPuntoVenta'            => (int) $setting->codigo_punto_venta,
            // En hora de Bolivia: es la misma fecha que entra en el CUF y el SIN
            // contrasta ambas.
            'fechaEmision'                => $fechaEmision->copy()
                ->setTimezone(config('siat.timezone'))->format('Y-m-d\TH:i:s.v'),
            'nombreRazonSocial'           => $invoice->nombre_razon_social,
            'codigoTipoDocumentoIdentidad' => (int) $invoice->tipo_doc_identidad,
            'numeroDocumento'             => $invoice->nit_ci,
            'complemento'                 => $invoice->complemento,
            'codigoCliente'               => $invoice->nit_ci,
            'codigoMetodoPago'            => (int) $invoice->metodo_pago,
            'numeroTarjeta'               => null,
            'montoTotal'                  => $this->monto($invoice->importe_total),
            'montoTotalSujetoIva'         => $this->monto($invoice->importe_base_cf),
            'codigoMoneda'                => (int) config('siat.factura.codigo_moneda'),
            'tipoCambio'                  => $this->monto(config('siat.factura.tipo_cambio')),
            'montoTotalMoneda'            => $this->monto($invoice->importe_total),
            'montoGiftCard'               => null,
            'descuentoAdicional'          => $this->monto($invoice->descuento),
            'codigoExcepcion'             => null,
            'cafc'                        => null,
            'leyenda'                     => $leyenda,
            'usuario'                     => $usuario,
            'codigoDocumentoSector'       => self::DOCUMENTO_SECTOR,
        ];

        foreach ($campos as $nombre => $valor) {
            $cabecera->appendChild($this->campo($dom, $nombre, $valor));
        }

        return $cabecera;
    }

    /** @param array<string, mixed> $detalle */
    private function detalle(DOMDocument $dom, array $detalle): \DOMElement
    {
        $nodo = $dom->createElement('detalle');

        $campos = [
            'actividadEconomica' => $detalle['actividadEconomica'],
            'codigoProductoSin'  => (int) $detalle['codigoProductoSin'],
            'codigoProducto'     => $detalle['codigoProducto'],
            'descripcion'        => $detalle['descripcion'],
            'cantidad'           => $this->monto($detalle['cantidad']),
            'unidadMedida'       => (int) $detalle['unidadMedida'],
            'precioUnitario'     => $this->monto($detalle['precioUnitario']),
            'montoDescuento'     => $this->monto($detalle['montoDescuento']),
            'subTotal'           => $this->monto($detalle['subTotal']),
            'numeroSerie'        => null,
            'numeroImei'         => null,
        ];

        foreach ($campos as $nombre => $valor) {
            $nodo->appendChild($this->campo($dom, $nombre, $valor));
        }

        return $nodo;
    }

    /**
     * Los campos vacíos van como `xsi:nil="true"`, no como elemento vacío: el XSD
     * los declara `nillable` pero con `minLength 1`, así que un elemento en blanco
     * lo incumple.
     */
    private function campo(DOMDocument $dom, string $nombre, mixed $valor): \DOMElement
    {
        if ($valor === null || $valor === '') {
            $nodo = $dom->createElement($nombre);
            $nodo->setAttributeNS(self::XSI, 'xsi:nil', 'true');

            return $nodo;
        }

        return $dom->createElement($nombre, htmlspecialchars((string) $valor, ENT_XML1, 'UTF-8'));
    }

    /** El XSD acepta como mucho 2 decimales en todos los importes. */
    private function monto(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
