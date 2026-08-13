<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\Purchase;
use DOMDocument;

/**
 * Construye el XML `registroCompra` del Registro de Compras y lo valida contra el
 * XSD oficial antes de que salga del sistema.
 *
 * Es el reverso de la factura: aquí el contribuyente declara lo que compró, así
 * que el emisor es el proveedor y no la tienda. No lleva CUF propio ni firma; lo
 * que identifica el documento es el código de autorización de la factura del
 * proveedor.
 *
 * @see resources/siat/registroCompra.xsd (paquete registroCompra.zip del SIN)
 */
final class RegistroCompraXml
{
    /**
     * @param  int  $nro  Correlativo dentro del paquete, empezando en 1.
     */
    public function build(Purchase $compra, int $nro): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $raiz = $dom->createElement('registroCompra');
        $dom->appendChild($raiz);

        // El orden importa: el XSD declara una <xs:sequence>.
        foreach ($this->campos($compra, $nro) as $nombre => $valor) {
            $raiz->appendChild($dom->createElement(
                $nombre,
                htmlspecialchars((string) $valor, ENT_XML1, 'UTF-8'),
            ));
        }

        $xml = $dom->saveXML();

        $this->validar($dom, $compra);

        return $xml;
    }

    /**
     * Qué le falta a una compra para poder declararse.
     *
     * Se comprueba antes de construir nada para poder listar en pantalla todo lo
     * que hay que completar, en vez de ir descubriéndolo de una excepción en una.
     *
     * @return list<string>
     */
    public function problemas(Purchase $compra): array
    {
        $problemas = [];

        if (blank($compra->codigo_autorizacion)) {
            $problemas[] = 'falta el código de autorización de la factura del proveedor';
        }

        if (blank($this->numeroFactura($compra))) {
            $problemas[] = 'el número de factura no es numérico o está vacío';
        }

        if (blank($this->nitEmisor($compra))) {
            $problemas[] = 'el proveedor no tiene NIT';
        }

        if (blank($this->razonSocial($compra))) {
            $problemas[] = 'el proveedor no tiene razón social';
        }

        if ((float) $compra->total <= 0) {
            $problemas[] = 'el importe total debe ser mayor que cero';
        }

        if (blank($compra->tipo_compra)) {
            $problemas[] = 'falta el tipo de compra';
        }

        return $problemas;
    }

    /** @return array<string, string|int> */
    private function campos(Purchase $compra, int $nro): array
    {
        $compra->loadMissing('supplier');

        return [
            'nro'                  => $nro,
            'nitEmisor'            => (int) $this->nitEmisor($compra),
            'razonSocialEmisor'    => $this->razonSocial($compra),
            'codigoAutorizacion'   => (string) $compra->codigo_autorizacion,
            'numeroFactura'        => (int) $this->numeroFactura($compra),
            // El XSD lo declara string con minLength 1: en compras internas es "0",
            // no vacío.
            'numeroDuiDim'         => (string) ($compra->numero_dui_dim ?: '0'),
            'fechaEmision'         => $this->fechaEmision($compra),
            'montoTotalCompra'     => $this->monto($compra->total),
            'importeIce'           => $this->monto($compra->importe_ice),
            'importeIehd'          => $this->monto($compra->importe_iehd),
            'importeIpj'           => $this->monto($compra->importe_ipj),
            'tasas'                => $this->monto($compra->tasas),
            'otroNoSujetoCredito'  => $this->monto($compra->otro_no_sujeto_credito),
            'importesExentos'      => $this->monto($compra->importes_exentos),
            'importeTasaCero'      => $this->monto($compra->importe_tasa_cero),
            'subTotal'             => $this->monto($compra->subtotal ?: $compra->total),
            'descuento'            => $this->monto($compra->descuento_siat),
            'montoGiftCard'        => $this->monto($compra->monto_gift_card),
            'montoTotalSujetoIva'  => $this->monto($this->sujetoIva($compra)),
            'creditoFiscal'        => $this->monto($compra->credito_fiscal),
            'tipoCompra'           => (int) $compra->tipo_compra,
            // El XSD lo exige siempre; las facturas modernas no lo tienen y va "0".
            'codigoControl'        => (string) ($compra->codigo_control ?: '0'),
        ];
    }

    /**
     * Base sujeta a IVA: el total menos todo lo que no da derecho a crédito.
     */
    private function sujetoIva(Purchase $compra): float
    {
        return round(
            (float) $compra->total
            - (float) $compra->importe_ice
            - (float) $compra->importe_iehd
            - (float) $compra->importe_ipj
            - (float) $compra->tasas
            - (float) $compra->otro_no_sujeto_credito
            - (float) $compra->importes_exentos
            - (float) $compra->importe_tasa_cero
            - (float) $compra->monto_gift_card,
            2,
        );
    }

    /** El NIT propio de la compra manda sobre el del proveedor, que puede faltar. */
    private function nitEmisor(Purchase $compra): ?string
    {
        $nit = $compra->nit_proveedor ?: $compra->supplier?->rfc;

        return blank($nit) ? null : preg_replace('/\D/', '', (string) $nit);
    }

    private function razonSocial(Purchase $compra): ?string
    {
        return $compra->razon_social_proveedor ?: $compra->supplier?->name;
    }

    /**
     * El XSD lo quiere numérico.
     *
     * No se le quitan los caracteres no numéricos: de "FAC-A/2026" saldría "2026",
     * que es declarar una factura distinta de la que se tiene. Si no es un número,
     * es un problema que hay que corregir en la compra.
     */
    private function numeroFactura(Purchase $compra): ?string
    {
        $numero = trim((string) $compra->invoice_number);

        if ($numero === '' || ! ctype_digit($numero)) {
            return null;
        }

        // Un número todo ceros sigue siendo el cero, no una cadena vacía.
        return ltrim($numero, '0') ?: '0';
    }

    /** La fecha de la factura del proveedor, no la del registro en el sistema. */
    private function fechaEmision(Purchase $compra): string
    {
        $fecha = $compra->invoice_date ?? $compra->date;

        return $fecha->copy()->startOfDay()->format('Y-m-d\TH:i:s');
    }

    private function monto(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    /**
     * Valida contra el XSD del SIN. Sale más barato descubrir aquí un campo mal
     * formado que en el rechazo del paquete, que solo señala el número de archivo.
     */
    private function validar(DOMDocument $dom, Purchase $compra): void
    {
        $xsd = resource_path('siat/registroCompra.xsd');

        if (! is_file($xsd)) {
            throw new SiatException("No se encuentra el XSD del Registro de Compras en {$xsd}.");
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

        throw new SiatException(
            "La compra {$compra->folio} no cumple el XSD del Registro de Compras: {$detalle}"
        );
    }
}
