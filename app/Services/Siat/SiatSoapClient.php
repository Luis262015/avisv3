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
     * @param  string|null  $envoltura  Nombre del objeto de solicitud que espera el
     *                                  WSDL (p. ej. "SolicitudCuis"). Varía por
     *                                  operación; sin él, los parámetros van sueltos.
     * @return array<string, mixed>
     */
    public function call(string $servicio, string $operacion, array $parametros = [], ?string $envoltura = null): array
    {
        $client = $this->clientFor($servicio);

        try {
            $respuesta = $client->__soapCall($operacion, [
                $envoltura ? [$envoltura => $parametros] : $parametros,
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
     * El SoapClient crudo, sin pasar por `call()`.
     *
     * Lo necesita quien recibe binarios: `normalize()` serializa la respuesta con
     * json_encode, y un base64Binary con bytes que no son UTF-8 se pierde ahí.
     */
    public function rawClient(string $servicio): SoapClient
    {
        return $this->clientFor($servicio);
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

        // Sin ext-soap la clase no existe y PHP lanzaría un Error fatal, que el
        // controlador no atrapa y acaba en un 500 sin explicación.
        if (! class_exists(\SoapClient::class)) {
            throw new SiatException(
                'La extensión SOAP de PHP no está habilitada, y los servicios del SIN la requieren. '
                . 'Habilite "extension=soap" en php.ini y reinicie el servidor web.'
            );
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
                'header'  => implode("\r\n", $this->authHeaders($token)),
                'timeout' => (int) config('siat.timeout'),
            ],
        ]);

        $wsdl = $this->wsdlUrl($servicio);

        try {
            // Las advertencias de PHP al no alcanzar el WSDL solo añaden ruido:
            // el SoapFault que viene detrás ya describe el fallo.
            return $this->clients[$servicio] = @new SoapClient($wsdl, [
                'stream_context'     => $context,
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'compression'        => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                'connection_timeout' => (int) config('siat.timeout'),
                'exceptions'         => true,
                'trace'              => true,
            ]);
        } catch (\SoapFault $e) {
            throw new SiatException($this->describeWsdlFailure($servicio, $wsdl, $e), previous: $e);
        }
    }

    /**
     * Cabeceras de autenticación, en la cabecera HTTP y no en la XML del SOAP.
     *
     * La v2 de los servicios responde "EL SERVICIO REQUIERE API KEY" si solo se
     * envía `Authorization`: espera `apikey: TokenApi <token>`. Se mandan ambas
     * para seguir siendo compatibles con la v1.
     *
     * @return list<string>
     */
    private function authHeaders(string $token): array
    {
        return [
            "apikey: TokenApi {$token}",
            "Authorization: Token {$token}",
        ];
    }

    /**
     * "Couldn't load from ... failed to load external entity" no le dice nada a
     * quien lo lee. Se sondea el endpoint para distinguir un servidor caído de un
     * problema de credenciales o de red.
     */
    private function describeWsdlFailure(string $servicio, string $wsdl, \SoapFault $fault): string
    {
        $status = $this->probeStatus($wsdl);

        Log::error('SIAT: no se pudo cargar el WSDL', [
            'servicio'    => $servicio,
            'wsdl'        => $wsdl,
            'http_status' => $status,
            'mensaje'     => $fault->getMessage(),
        ]);

        return match (true) {
            $status === null => "No hay conexión con el servidor del SIN ({$wsdl}). "
                . 'Verifique la salida a internet del servidor.',

            $status >= 500 => "El servicio del SIN no está disponible en este momento (HTTP {$status}). "
                . 'Es una caída del lado de Impuestos Nacionales; reintente más tarde.',

            $status === 401 || $status === 403 => "El SIN rechazó la petición (HTTP {$status}). "
                . 'Revise que el Token Delegado sea el del ambiente correcto y siga vigente.',

            $status === 404 => "El servicio \"{$servicio}\" no existe en esa ruta (HTTP 404). "
                . 'Revise el nombre del servicio y la versión en config/siat.php.',

            default => "No se pudo cargar el WSDL de \"{$servicio}\" (HTTP {$status}): {$fault->getMessage()}",
        };
    }

    /** Código HTTP del endpoint, o null si ni siquiera hubo respuesta. */
    private function probeStatus(string $url): ?int
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]);

        $headers = @get_headers($url, false, $context);

        if (! $headers || ! preg_match('#HTTP/\S+\s+(\d{3})#', $headers[0], $m)) {
            return null;
        }

        return (int) $m[1];
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

        // El SIN devuelve la carga bajo un nombre distinto por operación
        // (RespuestaCuis, RespuestaCufd, RespuestaComunicacion…); si solo hay una
        // clave envolvente, se devuelve su contenido.
        if (count($array) === 1) {
            $unico = reset($array);

            if (is_array($unico)) {
                return $unico;
            }
        }

        return $array;
    }
}
