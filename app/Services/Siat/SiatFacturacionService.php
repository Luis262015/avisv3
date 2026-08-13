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
     * Tipo de emisión declarado en la cabecera. No es la modalidad: una factura
     * computarizada se emite en línea salvo durante un corte.
     */
    public const EMISION_ONLINE  = 1;
    public const EMISION_OFFLINE = 2;
    public const EMISION_MASIVA  = 3;

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
            'fechaEnvio'  => $this->fechaEnvio($fechaEnvio),
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
     * Envía en un solo lote las facturas emitidas durante un corte.
     *
     * El SIN no valida el contenido en el momento: acusa recibo con un
     * `codigoRecepcion` y la validación real se consulta después con
     * {@see validacionRecepcionPaquete}. El paquete es el .tar.gz que arma
     * {@see PaqueteFacturas}, y `codigoEvento` es el código de recepción que
     * devolvió el registro del evento significativo: sin él el SIN no tiene con
     * qué justificar que esas facturas se emitieran fuera de línea.
     *
     * @param  string|null  $cafc  El mismo que llevan las facturas del paquete.
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function recepcionPaqueteFactura(
        SiatSetting $setting,
        string $paquete,
        string $cufd,
        CarbonInterface $fechaEnvio,
        int $tipoFacturaDocumento,
        int $cantidadFacturas,
        string $codigoEvento,
        ?string $cafc = null,
    ): array {
        $respuesta = $this->call($setting, 'recepcionPaqueteFactura', [
            'archivo'          => $paquete,
            // `solicitudRecepcionPaquete` declara el CAFC a nivel de paquete además
            // de en la cabecera de cada factura (verificado en el WSDL del piloto).
            'cafc'             => $cafc,
            'cantidadFacturas' => $cantidadFacturas,
            // El WSDL lo declara `long`, no cadena: el código de recepción del
            // evento significativo es numérico.
            'codigoEvento'     => (int) $codigoEvento,
            'fechaEnvio'       => $this->fechaEnvio($fechaEnvio),
            'hashArchivo'      => hash('sha256', $paquete),
        ], $cufd, 'SolicitudServicioRecepcionPaquete', $tipoFacturaDocumento, self::EMISION_OFFLINE);

        return $this->interpretar($respuesta);
    }

    /**
     * Consulta si el SIN validó un paquete ya recibido.
     *
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function validacionRecepcionPaquete(
        SiatSetting $setting,
        string $codigoRecepcion,
        string $cufd,
        int $tipoFacturaDocumento,
    ): array {
        // La operación del WSDL termina en "Factura", aunque la documentación la
        // titule sin ese sufijo.
        $respuesta = $this->call($setting, 'validacionRecepcionPaqueteFactura', [
            'codigoRecepcion' => $codigoRecepcion,
        ], $cufd, 'SolicitudServicioValidacionRecepcionPaquete', $tipoFacturaDocumento, self::EMISION_OFFLINE);

        return $this->interpretar($respuesta);
    }

    /**
     * Envío masivo (emisión 3): lotes de facturas emitidas con conexión.
     *
     * A diferencia del paquete de contingencia no responde a ningún corte, así que
     * no lleva `codigoEvento`.
     *
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function recepcionMasivaFactura(
        SiatSetting $setting,
        string $paquete,
        string $cufd,
        CarbonInterface $fechaEnvio,
        int $tipoFacturaDocumento,
        int $cantidadFacturas,
    ): array {
        $respuesta = $this->call($setting, 'recepcionMasivaFactura', [
            'archivo'          => $paquete,
            'cantidadFacturas' => $cantidadFacturas,
            'fechaEnvio'       => $this->fechaEnvio($fechaEnvio),
            'hashArchivo'      => hash('sha256', $paquete),
        ], $cufd, 'SolicitudServicioRecepcionMasiva', $tipoFacturaDocumento, self::EMISION_MASIVA);

        return $this->interpretar($respuesta);
    }

    /**
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function validacionRecepcionMasiva(
        SiatSetting $setting,
        string $codigoRecepcion,
        string $cufd,
        int $tipoFacturaDocumento,
    ): array {
        $respuesta = $this->call($setting, 'validacionRecepcionMasivaFactura', [
            'codigoRecepcion' => $codigoRecepcion,
        ], $cufd, 'SolicitudServicioValidacionRecepcionMasiva', $tipoFacturaDocumento, self::EMISION_MASIVA);

        return $this->interpretar($respuesta);
    }

    /**
     * Deshace la anulación de una factura, que vuelve a quedar vigente.
     *
     * El SIN solo lo admite dentro del plazo que fija la normativa; pasado ese
     * plazo responde con el motivo del rechazo, que sube como excepción.
     *
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function reversionAnulacionFactura(
        SiatSetting $setting,
        string $cuf,
        string $cufd,
        int $tipoFacturaDocumento,
    ): array {
        $respuesta = $this->call($setting, 'reversionAnulacionFactura', [
            'cuf' => $cuf,
        ], $cufd, 'SolicitudServicioReversionAnulacionFactura', $tipoFacturaDocumento);

        return $this->interpretar($respuesta);
    }

    /**
     * El SIN compara contra su reloj, que va en hora de Bolivia, y solo admite 5
     * minutos de diferencia: en UTC el desfase son 4 horas.
     */
    private function fechaEnvio(CarbonInterface $fecha): string
    {
        return $fecha->copy()->setTimezone(config('siat.timezone'))->format('Y-m-d\TH:i:s.v');
    }

    /**
     * Todas las operaciones extienden el mismo `solicitudRecepcion`, así que
     * comparten la cabecera de identificación del emisor.
     *
     * @param  array<string, mixed>  $extra
     * @param  int|null  $codigoEmision  Solo los envíos por lote se apartan de la
     *                                   emisión en línea.
     * @return array<string, mixed>
     */
    private function call(
        SiatSetting $setting,
        string $operacion,
        array $extra,
        string $cufd,
        string $envoltura,
        int $tipoFacturaDocumento,
        ?int $codigoEmision = null,
    ): array {
        if (blank($setting->cuis)) {
            throw new SiatException('No hay CUIS para esta tienda; el SIN no acepta facturas sin él.');
        }

        $client = new SiatSoapClient($setting);

        $parametros = array_merge([
            'codigoAmbiente'        => $client->codigoAmbiente(),
            'codigoDocumentoSector' => (int) config('siat.factura.documento_sector'),
            // El tipo de emisión es independiente de la modalidad. Por defecto es
            // en línea, porque el envío ocurre contra el servicio; los lotes lo
            // sobrescriben con "fuera de línea" o "masiva".
            'codigoEmision'         => $codigoEmision ?? (int) config('siat.factura.emision_online'),
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
