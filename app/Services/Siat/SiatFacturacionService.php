<?php

namespace App\Services\Siat;

use App\Models\SiatSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Servicio "ServicioFacturacionCompraVenta": envío, anulación y consulta de
 * facturas.
 *
 * El XML no viaja en claro: se comprime en gzip, se envía como base64Binary y se
 * acompaña del SHA-256 del archivo comprimido para que el SIN detecte cualquier
 * alteración en tránsito.
 */
class SiatFacturacionService
{
    private const SERVICIO = 'compra_venta';

    /** Códigos de estado que devuelve el servicio, verificados contra el piloto. */
    public const ESTADO_VALIDADA = 908;
    public const ESTADO_ANULADA  = 905;

    /**
     * Envía una factura ya construida.
     *
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function recepcionFactura(
        SiatSetting $setting,
        string $xml,
        string $cufd,
        CarbonInterface $fechaEnvio,
        int $tipoFacturaDocumento,
    ): array {
        $comprimido = $this->comprimir($xml);

        $respuesta = $this->call($setting, 'recepcionFactura', [
            // PHP codifica solo el base64Binary a partir de los bytes crudos; pasarlo
            // ya en base64 lo dejaría codificado dos veces.
            'archivo'     => $comprimido,
            // El SIN compara contra su reloj, que va en hora de Bolivia, y solo
            // admite 5 minutos de diferencia: en UTC el desfase son 4 horas.
            'fechaEnvio'  => $fechaEnvio->copy()->setTimezone(config('siat.timezone'))->format('Y-m-d\TH:i:s.v'),
            'hashArchivo' => hash('sha256', $comprimido),
        ], $cufd, 'SolicitudServicioRecepcionFactura', $tipoFacturaDocumento);

        return $this->interpretar($respuesta);
    }

    /**
     * Anula una factura ya recibida por el SIN.
     *
     * @param  int  $codigoMotivo  Del catálogo `sincronizarParametricaMotivoAnulacion`.
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function anulacionFactura(
        SiatSetting $setting,
        string $cuf,
        string $cufd,
        int $codigoMotivo,
        int $tipoFacturaDocumento,
    ): array {
        $respuesta = $this->call($setting, 'anulacionFactura', [
            'codigoMotivo' => $codigoMotivo,
            'cuf'          => $cuf,
        ], $cufd, 'SolicitudServicioAnulacionFactura', $tipoFacturaDocumento);

        return $this->interpretar($respuesta);
    }

    /**
     * Consulta en qué estado quedó una factura enviada.
     *
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function verificacionEstadoFactura(
        SiatSetting $setting,
        string $cuf,
        string $cufd,
        int $tipoFacturaDocumento,
    ): array {
        $respuesta = $this->call($setting, 'verificacionEstadoFactura', [
            'cuf' => $cuf,
        ], $cufd, 'SolicitudServicioVerificacionEstadoFactura', $tipoFacturaDocumento);

        return $this->interpretar($respuesta);
    }

    /**
     * Las tres operaciones extienden el mismo `solicitudRecepcion`, así que
     * comparten la cabecera de identificación del emisor.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function call(
        SiatSetting $setting,
        string $operacion,
        array $extra,
        string $cufd,
        string $envoltura,
        int $tipoFacturaDocumento,
    ): array {
        if (blank($setting->cuis)) {
            throw new SiatException('No hay CUIS para esta tienda; el SIN no acepta facturas sin él.');
        }

        $client = new SiatSoapClient($setting);

        $parametros = array_merge([
            'codigoAmbiente'        => $client->codigoAmbiente(),
            'codigoDocumentoSector' => (int) config('siat.factura.documento_sector'),
            // El tipo de emisión es independiente de la modalidad: aquí siempre es
            // en línea, porque el envío ocurre contra el servicio.
            'codigoEmision'         => (int) config('siat.factura.emision_online'),
            'codigoModalidad'       => (int) $setting->modalidad,
            'codigoPuntoVenta'      => (int) $setting->codigo_punto_venta,
            'codigoSistema'         => (string) $setting->codigo_sistema,
            'codigoSucursal'        => (int) $setting->codigo_sucursal,
            'cufd'                  => $cufd,
            'cuis'                  => $setting->cuis,
            'nit'                   => (int) $setting->nit,
            // Es el tipo de la factura que se envía, no un ajuste global: el SIN
            // lo contrasta con lo autorizado y con el CUF, y rechaza si difieren.
            'tipoFacturaDocumento'  => $tipoFacturaDocumento,
        ], $extra);

        return $client->call(self::SERVICIO, $operacion, $parametros, envoltura: $envoltura);
    }

    /** El XML viaja comprimido; el hash se calcula sobre esos mismos bytes. */
    private function comprimir(string $xml): string
    {
        $comprimido = gzencode($xml, 9);

        if ($comprimido === false) {
            throw new SiatException('No se pudo comprimir el XML de la factura.');
        }

        return $comprimido;
    }

    /**
     * @param  array<string, mixed>  $respuesta
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    private function interpretar(array $respuesta): array
    {
        $mensajes = $respuesta['mensajesList'] ?? [];

        if (isset($mensajes['codigo']) || isset($mensajes['descripcion'])) {
            $mensajes = [$mensajes];
        }

        $textos = array_values(array_filter(array_map(
            fn($m) => trim((string) ($m['codigo'] ?? '') . ' ' . (string) ($m['descripcion'] ?? '')),
            is_array($mensajes) ? $mensajes : [],
        )));

        $resultado = [
            'codigoRecepcion'   => $respuesta['codigoRecepcion'] ?? null,
            'codigoEstado'      => isset($respuesta['codigoEstado']) ? (int) $respuesta['codigoEstado'] : null,
            'codigoDescripcion' => $respuesta['codigoDescripcion'] ?? null,
            'mensajes'          => $textos,
            'respuesta'         => $respuesta,
        ];

        if (($respuesta['transaccion'] ?? false) !== true) {
            Log::error('SIAT: el servicio de facturación rechazó la operación', $resultado);

            throw new SiatException(
                'El SIN rechazó la operación: ' . ($textos ? implode(' | ', $textos) : 'sin detalle en la respuesta.')
            );
        }

        return $resultado;
    }
}
