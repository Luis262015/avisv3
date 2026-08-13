<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\Purchase;
use App\Models\SiatSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Servicio "ServicioRecepcionCompras": el Registro de Compras.
 *
 * Es el reverso de la facturación: aquí el contribuyente declara las compras que
 * hizo y que sus proveedores no reportaron, agrupadas por gestión y periodo.
 *
 * Esta clase es solo la capa SOAP, como {@see SiatFacturacionService} lo es para
 * las ventas; quien arma los paquetes y lleva el registro local es
 * {@see RegistroComprasService}.
 *
 * @see https://siatinfo.impuestos.gob.bo/index.php/registro-de-compras-y-ventas/registro-de-compras-serv/recepcion-paquete-compras
 */
// Sin `final`: es la frontera con el SIN y los tests la doblan, igual que
// SiatFacturacionService.
class SiatComprasService
{
    private const SERVICIO = 'compras';

    public function __construct(
        private readonly PaqueteFacturas $paquetes,
        private readonly CufdProvider $cufds,
    ) {}

    /**
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function recepcionPaqueteCompras(
        SiatSetting $setting,
        string $archivo,
        int $cantidad,
        int $gestion,
        int $periodo,
    ): array {
        return $this->interpretar($this->call($setting, 'recepcionPaqueteCompras', [
            'archivo'          => $archivo,
            'cantidadFacturas' => $cantidad,
            'fechaEnvio'       => $this->fecha(now()),
            'gestion'          => $gestion,
            'hashArchivo'      => hash('sha256', $archivo),
            'periodo'          => $periodo,
        ], 'SolicitudRecepcionCompras'));
    }

    /**
     * Consulta en qué estado quedó un paquete de compras ya recibido.
     *
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function validacionRecepcionPaqueteCompras(SiatSetting $setting, string $codigoRecepcion): array
    {
        return $this->interpretar($this->call($setting, 'validacionRecepcionPaqueteCompras', [
            'codigoRecepcion' => $codigoRecepcion,
        ], 'SolicitudValidacionRecepcionCompras'));
    }

    /**
     * Cierra el periodo: confirma ante el SIN que lo declarado es todo.
     *
     * Comparte estructura con la recepción, pero sin archivo real: es la firma de
     * que el periodo queda cerrado.
     *
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function confirmar(SiatSetting $setting, int $gestion, int $periodo, int $cantidad = 0): array
    {
        return $this->interpretar($this->call($setting, 'confirmacionCompras', [
            'cantidadFacturas' => $cantidad,
            'fechaEnvio'       => $this->fecha(now()),
            'gestion'          => $gestion,
            'periodo'          => $periodo,
        ], 'SolicitudConfirmacionCompras'));
    }

    /**
     * Anula una compra ya declarada. La identifica su clave natural, no un id
     * nuestro: autorización, NIT del proveedor, DUI/DIM y número de factura.
     *
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function anular(SiatSetting $setting, Purchase $compra): array
    {
        $compra->loadMissing('supplier');

        return $this->interpretar($this->call($setting, 'anulacionCompra', [
            'codAutorizacion' => (string) $compra->codigo_autorizacion,
            'nitProveedor'    => (int) preg_replace('/\D/', '', (string) ($compra->nit_proveedor ?: $compra->supplier?->rfc)),
            'nroDuiDim'       => (string) ($compra->numero_dui_dim ?: '0'),
            'nroFactura'      => (int) preg_replace('/\D/', '', (string) $compra->invoice_number),
        ], 'SolicitudAnulacionCompra'));
    }

    /**
     * Las compras que el SIN tiene registradas para una fecha, tal como las
     * devuelve: un .tar.gz con un XML por compra.
     *
     * No pasa por `SiatSoapClient::call()` porque su `normalize()` serializa la
     * respuesta con json_encode, y el archivo binario no sobrevive a eso.
     *
     * @return array{transaccion: bool, mensajes: list<string>, xml: list<string>}
     */
    public function consultar(SiatSetting $setting, CarbonInterface $fecha): array
    {
        $client = new SiatSoapClient($setting);
        $soap   = $client->rawClient(self::SERVICIO);

        try {
            $respuesta = $soap->__soapCall('consultaCompras', [[
                'SolicitudConsultaCompras' => array_merge($this->cabecera($setting, $client), [
                    'fecha' => $this->fecha($fecha),
                ]),
            ]]);
        } catch (\SoapFault $e) {
            throw new SiatException("Error al consultar las compras en el SIN: {$e->getMessage()}", previous: $e);
        }

        $cuerpo   = $respuesta->RespuestaServicioFacturacion ?? $respuesta;
        $mensajes = $cuerpo->mensajesList ?? [];

        return [
            'transaccion' => (bool) ($cuerpo->transaccion ?? false),
            'mensajes'    => array_map(
                fn ($m) => trim(((string) ($m->codigo ?? '')) . ' ' . ((string) ($m->descripcion ?? ''))),
                is_array($mensajes) ? $mensajes : [$mensajes],
            ),
            'xml' => $this->desempaquetar($cuerpo->archivo ?? null),
        ];
    }

    /**
     * El SIN devuelve las compras en el mismo .tar.gz que espera al recibirlas.
     *
     * @return list<string>
     */
    private function desempaquetar(?string $archivo): array
    {
        if (blank($archivo)) {
            return [];
        }

        try {
            return array_values($this->paquetes->leer($archivo));
        } catch (\Throwable $e) {
            Log::warning('SIAT: no se pudo abrir el paquete de compras devuelto', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Todas las operaciones extienden `solicitudCompras`, que lleva la
     * identificación del contribuyente.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function call(SiatSetting $setting, string $operacion, array $extra, string $envoltura): array
    {
        $client = new SiatSoapClient($setting);

        return $client->call(
            self::SERVICIO,
            $operacion,
            array_merge($this->cabecera($setting, $client), $extra),
            envoltura: $envoltura,
        );
    }

    /** @return array<string, mixed> */
    private function cabecera(SiatSetting $setting, SiatSoapClient $client): array
    {
        if (blank($setting->cuis)) {
            throw new SiatException('No hay CUIS para esta tienda; el SIN no admite el Registro de Compras sin él.');
        }

        return [
            'codigoAmbiente'   => $client->codigoAmbiente(),
            'codigoPuntoVenta' => (int) $setting->codigo_punto_venta,
            'codigoSistema'    => (string) $setting->codigo_sistema,
            'codigoSucursal'   => (int) $setting->codigo_sucursal,
            'cufd'             => $this->cufds->vigente($setting)->codigo,
            'cuis'             => $setting->cuis,
            'nit'              => (int) $setting->nit,
        ];
    }

    private function fecha(CarbonInterface $fecha): string
    {
        return $fecha->copy()->setTimezone(config('siat.timezone'))->format('Y-m-d\TH:i:s.v');
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
            Log::error('SIAT: el Registro de Compras rechazó la operación', $resultado);

            throw new SiatException(
                'El SIN rechazó la operación: ' . ($textos ? implode(' | ', $textos) : 'sin detalle en la respuesta.')
            );
        }

        return $resultado;
    }
}
