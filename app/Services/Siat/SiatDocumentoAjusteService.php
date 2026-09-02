<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\SiatSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Servicio "ServicioFacturacionDocumentoAjuste": las notas de crédito-débito.
 *
 * Vive aparte de {@see SiatFacturacionService} porque el SIN lo publica como un
 * servicio distinto, con sus propias operaciones y sus propias envolturas. El
 * transporte sí es el mismo: gzip del XML, bytes crudos en el `base64Binary` y
 * SHA-256 del comprimido.
 *
 * Tiene cuatro operaciones y ninguna más: **no hay envío por paquete ni masivo**
 * para las notas, así que quedan fuera de las etapas VI y IX de la homologación.
 *
 * Operaciones del WSDL del piloto: `recepcionDocumentoAjuste`,
 * `anulacionDocumentoAjuste`, `reversionAnulacionDocumentoAjuste`,
 * `verificacionEstadoDocumentoAjuste`.
 */
class SiatDocumentoAjusteService
{
    private const SERVICIO = 'ajuste';

    /** Mismos códigos que en compra-venta: el SIN comparte la tabla de estados. */
    public const ESTADO_VALIDADA = 908;
    public const ESTADO_ANULADA  = 905;
    public const ESTADO_REVERTIDA = 907;

    /**
     * Envía una nota ya construida y validada contra el XSD.
     *
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function recepcionDocumentoAjuste(
        SiatSetting $setting,
        string $xml,
        string $cufd,
        CarbonInterface $fechaEnvio,
        int $documentoSector,
    ): array {
        $comprimido = $this->comprimir($xml);

        $respuesta = $this->call($setting, 'recepcionDocumentoAjuste', [
            // Bytes crudos: PHP codifica el base64Binary por su cuenta.
            'archivo'     => $comprimido,
            'fechaEnvio'  => $this->fechaEnvio($fechaEnvio),
            'hashArchivo' => hash('sha256', $comprimido),
        ], $cufd, 'SolicitudServicioRecepcionDocumentoAjuste', $documentoSector);

        return $this->interpretar($respuesta);
    }

    /**
     * @param  int  $codigoMotivo  Del catálogo `sincronizarParametricaMotivoAnulacion`.
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function anulacionDocumentoAjuste(
        SiatSetting $setting,
        string $cuf,
        string $cufd,
        int $codigoMotivo,
        int $documentoSector,
    ): array {
        $respuesta = $this->call($setting, 'anulacionDocumentoAjuste', [
            'codigoMotivo' => $codigoMotivo,
            'cuf'          => $cuf,
        ], $cufd, 'SolicitudServicioAnulacionDocumentoAjuste', $documentoSector);

        return $this->interpretar($respuesta);
    }

    /**
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function reversionAnulacionDocumentoAjuste(
        SiatSetting $setting,
        string $cuf,
        string $cufd,
        int $documentoSector,
    ): array {
        $respuesta = $this->call($setting, 'reversionAnulacionDocumentoAjuste', [
            'cuf' => $cuf,
        ], $cufd, 'SolicitudServicioReversionAnulacionDocumentoAjuste', $documentoSector);

        return $this->interpretar($respuesta);
    }

    /**
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function verificacionEstadoDocumentoAjuste(
        SiatSetting $setting,
        string $cuf,
        string $cufd,
        int $documentoSector,
    ): array {
        $respuesta = $this->call($setting, 'verificacionEstadoDocumentoAjuste', [
            'cuf' => $cuf,
        ], $cufd, 'SolicitudServicioVerificacionEstadoDocumentoAjuste', $documentoSector);

        return $this->interpretar($respuesta);
    }

    /**
     * Cabecera común: las cuatro operaciones extienden `solicitudRecepcion`.
     *
     * `__getTypes()` aplana la herencia y solo enseña los campos propios de cada
     * solicitud, pero el servicio exige igualmente ambiente, sistema, CUIS, CUFD y
     * NIT. La diferencia con compra-venta está en dos campos: el documento sector
     * es 24 o 47, y el tipo de factura es siempre 3 (documento de ajuste).
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
        int $documentoSector,
    ): array {
        if (blank($setting->cuis)) {
            throw new SiatException('No hay CUIS para esta tienda; el SIN no acepta notas sin él.');
        }

        if (! array_key_exists($documentoSector, (array) config('siat.nota.documentos_sector'))) {
            throw new SiatException("El documento sector {$documentoSector} no es una nota de crédito-débito.");
        }

        $client = new SiatSoapClient($setting);

        $parametros = array_merge([
            'codigoAmbiente'        => $client->codigoAmbiente(),
            'codigoDocumentoSector' => $documentoSector,
            // La nota siempre se emite en línea: el SIN no publica ni paquete ni
            // masiva para el documento de ajuste.
            'codigoEmision'         => (int) config('siat.factura.emision_online'),
            'codigoModalidad'       => (int) $setting->modalidad,
            'codigoPuntoVenta'      => (int) $setting->codigo_punto_venta,
            'codigoSistema'         => (string) $setting->codigo_sistema,
            'codigoSucursal'        => (int) $setting->codigo_sucursal,
            'cufd'                  => $cufd,
            'cuis'                  => $setting->cuis,
            'nit'                   => (int) $setting->nit,
            'tipoFacturaDocumento'  => (int) config('siat.nota.tipo_factura'),
        ], $extra);

        return $client->call(self::SERVICIO, $operacion, $parametros, envoltura: $envoltura);
    }

    /** El SIN admite 5 minutos de desfase y trabaja en hora de Bolivia. */
    private function fechaEnvio(CarbonInterface $fecha): string
    {
        return $fecha->copy()->setTimezone(config('siat.timezone'))->format('Y-m-d\TH:i:s.v');
    }

    private function comprimir(string $xml): string
    {
        $comprimido = gzencode($xml, 9);

        if ($comprimido === false) {
            throw new SiatException('No se pudo comprimir el XML de la nota.');
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
            fn ($m) => trim((string) ($m['codigo'] ?? '') . ' ' . (string) ($m['descripcion'] ?? '')),
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
            Log::error('SIAT: el servicio de documento de ajuste rechazó la operación', $resultado);

            throw new SiatException(
                'El SIN rechazó la operación: ' . ($textos ? implode(' | ', $textos) : 'sin detalle en la respuesta.')
            );
        }

        return $resultado;
    }
}
