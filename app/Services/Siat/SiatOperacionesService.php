<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\SiatSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Servicio "FacturacionOperaciones": eventos significativos.
 *
 * Un evento significativo es la declaración formal de un corte —el SIN caído, sin
 * internet, sin luz— durante el que se facturó fuera de línea. Se declara cuando
 * vuelve la conexión y el SIN responde con un código de recepción que después hay
 * que citar al enviar el paquete de esas facturas.
 *
 * @see https://siatinfo.impuestos.gob.bo/index.php/facturacion-en-linea/implementacion-servicios-facturacion/operaciones/registro-evento-significativo
 */
class SiatOperacionesService
{
    private const SERVICIO = 'operaciones';

    /**
     * Declara el corte y devuelve el código de recepción del evento.
     *
     * @param  string  $cufdEvento  El CUFD que estaba vigente durante el corte.
     * @param  string  $cufd        El CUFD vigente ahora, al declararlo.
     */
    public function registrarEvento(
        SiatSetting $setting,
        int $codigoMotivoEvento,
        string $descripcion,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        string $cufdEvento,
        string $cufd,
    ): string {
        $client = $this->clientFor($setting);

        $respuesta = $client->call(self::SERVICIO, 'registroEventoSignificativo', [
            'codigoAmbiente'       => $client->codigoAmbiente(),
            'codigoMotivoEvento'   => $codigoMotivoEvento,
            'codigoPuntoVenta'     => (int) $setting->codigo_punto_venta,
            'codigoSistema'        => (string) $setting->codigo_sistema,
            'codigoSucursal'       => (int) $setting->codigo_sucursal,
            'cufd'                 => $cufd,
            'cufdEvento'           => $cufdEvento,
            'cuis'                 => $this->requireCuis($setting),
            'descripcion'          => $descripcion,
            // En hora de Bolivia, como el resto de fechas que valida el SIN.
            'fechaHoraFinEvento'   => $this->fecha($fin),
            'fechaHoraInicioEvento' => $this->fecha($inicio),
            'nit'                  => (int) $setting->nit,
        ], envoltura: 'SolicitudEventoSignificativo');

        $this->assertTransaccion($respuesta, 'registro del evento significativo');

        $codigo = $respuesta['codigoRecepcionEventoSignificativo'] ?? null;

        if (blank($codigo)) {
            throw new SiatException('El SIN no devolvió el código de recepción del evento significativo.');
        }

        return (string) $codigo;
    }

    /**
     * Eventos significativos que el SIN tiene registrados para una fecha.
     *
     * `fechaEvento` no es opcional: el WSDL la declara y la consulta se acota a
     * ese día, no devuelve el histórico entero.
     *
     * @return list<array<string, mixed>>
     */
    public function consultarEventos(SiatSetting $setting, string $cufd, ?CarbonInterface $fechaEvento = null): array
    {
        $client = $this->clientFor($setting);

        $respuesta = $client->call(self::SERVICIO, 'consultaEventoSignificativo', [
            'codigoAmbiente'   => $client->codigoAmbiente(),
            'codigoPuntoVenta' => (int) $setting->codigo_punto_venta,
            'codigoSistema'    => (string) $setting->codigo_sistema,
            'codigoSucursal'   => (int) $setting->codigo_sucursal,
            'cufd'             => $cufd,
            'cuis'             => $this->requireCuis($setting),
            'fechaEvento'      => $this->fecha($fechaEvento ?? Carbon::now()),
            'nit'              => (int) $setting->nit,
        ], envoltura: 'SolicitudConsultaEvento');

        $lista = $respuesta['listaCodigos'] ?? [];

        if (blank($lista)) {
            return [];
        }

        // Un único elemento llega como objeto y no como lista.
        return array_is_list($lista) ? $lista : [$lista];
    }

    private function clientFor(SiatSetting $setting): SiatSoapClient
    {
        return new SiatSoapClient($setting);
    }

    private function requireCuis(SiatSetting $setting): string
    {
        if (blank($setting->cuis)) {
            throw new SiatException('No hay CUIS para esta tienda; el SIN no admite eventos sin él.');
        }

        return $setting->cuis;
    }

    private function fecha(CarbonInterface $fecha): string
    {
        return $fecha->copy()->setTimezone(config('siat.timezone'))->format('Y-m-d\TH:i:s.v');
    }

    /** @param array<string, mixed> $respuesta */
    private function assertTransaccion(array $respuesta, string $operacion): void
    {
        if (($respuesta['transaccion'] ?? false) === true) {
            return;
        }

        Log::error("SIAT: {$operacion} rechazado", ['respuesta' => $respuesta]);

        $mensajes = $respuesta['mensajesList'] ?? [];

        if (isset($mensajes['codigo']) || isset($mensajes['descripcion'])) {
            $mensajes = [$mensajes];
        }

        $textos = collect(is_array($mensajes) ? $mensajes : [])
            ->map(fn ($m) => trim((string) ($m['codigo'] ?? '') . ' ' . (string) ($m['descripcion'] ?? '')))
            ->filter()
            ->implode(' | ');

        throw new SiatException(
            "El SIN rechazó el {$operacion}: " . ($textos !== '' ? $textos : 'sin detalle en la respuesta.')
        );
    }
}
