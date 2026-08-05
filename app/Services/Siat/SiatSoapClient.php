<?php

namespace App\Services\Siat;

use App\Models\SiatSetting;
use Illuminate\Support\Facades\Log;
use SoapClient;

/**
 * Cliente SOAP de los servicios web del SIAT.
 *
 * La autenticación va como cabecera HTTP `Authorization: Token <valor>`, con el
 * Token Delegado que se obtiene del Portal SIAT. La documentación es explícita en
 * que el token NO va en la cabecera XML del request, sino en la HTTP.
 *
 * @see https://siatanexo.impuestos.gob.bo/index.php/implementacion-servicios-facturacion/autenticacion/token-de-autenticacion
 */
class SiatSoapClient
{
    /** @var array<string, SoapClient> */
    private array $clients = [];

    public function __construct(private readonly SiatSetting $setting) {}

    /**
     * Código de ambiente que el SIN espera en el cuerpo de cada petición.
     * Producción = 1, Pruebas y Piloto = 2.
     */
    public function codigoAmbiente(): int
    {
        $codigos = config('siat.codigos_ambiente');

        return $codigos[$this->setting->ambiente]
            ?? throw new SiatException("El ambiente \"{$this->setting->ambiente}\" no tiene código SIAT asignado.");
    }

    public function wsdlUrl(string $servicio): string
    {
        $host = config("siat.hosts.{$this->setting->ambiente}")
            ?? throw new SiatException("No hay host SIAT configurado para el ambiente \"{$this->setting->ambiente}\".");

        $nombre = config("siat.servicios.{$servicio}")
            ?? throw new SiatException("Servicio SIAT desconocido: \"{$servicio}\".");

        return sprintf('%s/%s/%s?wsdl', rtrim($host, '/'), config('siat.version'), $nombre);
    }

    /**
     * Llama a una operación y devuelve la respuesta como array.
     *
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>
     */
    public function call(string $servicio, string $operacion, array $parametros = []): array
    {
        $client = $this->clientFor($servicio);

        try {
            // El SIN envuelve los parámetros en un objeto de solicitud.
            $respuesta = $client->__soapCall($operacion, [
                ['SolicitudTecnica' => $parametros],
            ]);
        } catch (\SoapFault $e) {
            Log::error('SIAT: fallo SOAP', [
                'servicio'  => $servicio,
                'operacion' => $operacion,
                'ambiente'  => $this->setting->ambiente,
                'mensaje'   => $e->getMessage(),
            ]);

            throw new SiatException(
                "Error al comunicarse con el SIN ({$operacion}): {$e->getMessage()}",
                previous: $e,
            );
        }

        return $this->normalize($respuesta);
    }

    /**
     * Prueba de conectividad y validez del token. No requiere CUIS ni CUFD.
     */
    public function verificarComunicacion(string $servicio = 'codigos'): array
    {
        return $this->call($servicio, 'verificarComunicacion');
    }

    private function clientFor(string $servicio): SoapClient
    {
        if (isset($this->clients[$servicio])) {
            return $this->clients[$servicio];
        }

        $token = $this->setting->token_api;

        if (blank($token)) {
            throw new SiatException(
                'La configuración SIAT no tiene Token Delegado. Obténgalo en el Portal SIAT '
                . '(Gestión de Autorización de Sistemas Informáticos de Facturación) y cárguelo en la configuración.'
            );
        }

        $context = stream_context_create([
            'http' => [
                'header'  => "Authorization: Token {$token}",
                'timeout' => (int) config('siat.timeout'),
            ],
        ]);

        try {
            return $this->clients[$servicio] = new SoapClient($this->wsdlUrl($servicio), [
                'stream_context'     => $context,
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'compression'        => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                'connection_timeout' => (int) config('siat.timeout'),
                'exceptions'         => true,
                'trace'              => true,
            ]);
        } catch (\SoapFault $e) {
            throw new SiatException(
                "No se pudo cargar el WSDL de \"{$servicio}\": {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * Las respuestas llegan como objetos anidados; se normalizan a arrays para
     * poder tratarlas de forma uniforme.
     *
     * @return array<string, mixed>
     */
    private function normalize(mixed $respuesta): array
    {
        $array = json_decode(json_encode($respuesta), true) ?? [];

        // El SIN devuelve la carga bajo distintos nombres según la operación.
        foreach (['RespuestaCuis', 'RespuestaCufd', 'RespuestaServicioFacturacion', 'return'] as $envoltura) {
            if (isset($array[$envoltura]) && is_array($array[$envoltura])) {
                return $array[$envoltura];
            }
        }

        return $array;
    }
}
