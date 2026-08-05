<?php

namespace App\Services\Siat;

use App\Models\SiatCufdCode;
use App\Models\SiatSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Servicio "FacturacionCodigos" del SIAT: CUIS y CUFD.
 *
 * @see https://siatinfo.impuestos.gob.bo/index.php/facturacion-en-linea/implementacion-servicios-facturacion/codigos/solicitud-cuis
 * @see https://siatinfo.impuestos.gob.bo/index.php/facturacion-en-linea/implementacion-servicios-facturacion/codigos/solicitud-cufd
 */
class SiatCodigosService
{
    private const SERVICIO = 'codigos';

    /**
     * Solicita el CUIS (Código Único de Inicio de Sistemas) y lo persiste.
     *
     * Se obtiene una vez por sucursal/punto de venta y habilita el resto de
     * operaciones; sin él no se puede pedir un CUFD.
     */
    public function solicitarCuis(SiatSetting $setting): string
    {
        $client = $this->clientFor($setting);

        $respuesta = $client->call(self::SERVICIO, 'cuis', [
            'codigoAmbiente'   => $client->codigoAmbiente(),
            'codigoModalidad'  => (int) $setting->modalidad,
            'codigoSistema'    => $this->requireCodigoSistema($setting),
            'codigoSucursal'   => (int) $setting->codigo_sucursal,
            'codigoPuntoVenta' => (int) $setting->codigo_punto_venta,
            'nit'              => (int) $setting->nit,
        ], envoltura: 'SolicitudCuis');

        $this->assertTransaccion($respuesta, 'solicitud de CUIS');

        $codigo = $respuesta['codigoCuis'] ?? $respuesta['codigoCUIS'] ?? null;

        if (blank($codigo)) {
            throw new SiatException('El SIN no devolvió un CUIS en la respuesta.');
        }

        $setting->update([
            'cuis'                 => $codigo,
            'cuis_fecha_solicitud' => now(),
            'cuis_fecha_vigencia'  => $this->parseFecha($respuesta['fechaVigencia'] ?? null),
        ]);

        return $codigo;
    }

    /**
     * Solicita el CUFD del día y lo persiste.
     *
     * Vence a las 24 horas; el `codigoControl` que acompaña al CUFD es el sufijo
     * obligatorio del CUF de cada factura emitida bajo ese CUFD.
     */
    public function solicitarCufd(SiatSetting $setting): SiatCufdCode
    {
        if (blank($setting->cuis)) {
            throw new SiatException(
                'No hay CUIS registrado para esta tienda. Solicite primero el CUIS: '
                . 'sin él el SIN no entrega el CUFD.'
            );
        }

        $client = $this->clientFor($setting);

        // La operación se llama "cufd" en el WSDL, no "solicitudCufd" como la
        // titula la documentación.
        $respuesta = $client->call(self::SERVICIO, 'cufd', [
            'codigoAmbiente'   => $client->codigoAmbiente(),
            'codigoModalidad'  => (int) $setting->modalidad,
            'codigoSistema'    => $this->requireCodigoSistema($setting),
            'codigoSucursal'   => (int) $setting->codigo_sucursal,
            'codigoPuntoVenta' => (int) $setting->codigo_punto_venta,
            'cuis'             => $setting->cuis,
            'nit'              => (int) $setting->nit,
        ], envoltura: 'SolicitudCufd');

        $this->assertTransaccion($respuesta, 'solicitud de CUFD');

        $codigo        = $respuesta['codigo'] ?? $respuesta['codigoCUFD'] ?? null;
        $codigoControl = $respuesta['codigoControl'] ?? null;

        if (blank($codigo) || blank($codigoControl)) {
            throw new SiatException('El SIN no devolvió el CUFD o su código de control.');
        }

        // Vencer los CUFD anteriores de la tienda antes de registrar el nuevo.
        SiatCufdCode::where('store_id', $setting->store_id)
            ->where('estado', 'activo')
            ->update(['estado' => 'vencido']);

        return SiatCufdCode::create([
            'store_id'       => $setting->store_id,
            'codigo'         => $codigo,
            'codigo_control' => $codigoControl,
            'direccion'      => $respuesta['direccion'] ?? null,
            'fecha_vigencia' => $this->parseFecha($respuesta['fechaVigencia'] ?? null) ?? now()->addDay(),
            'consecutivo'    => 0,
            'estado'         => 'activo',
        ]);
    }

    /** Comprueba conectividad y validez del Token Delegado. */
    public function verificarComunicacion(SiatSetting $setting): array
    {
        return $this->clientFor($setting)->verificarComunicacion(self::SERVICIO);
    }

    private function clientFor(SiatSetting $setting): SiatSoapClient
    {
        return new SiatSoapClient($setting);
    }

    private function requireCodigoSistema(SiatSetting $setting): string
    {
        if (blank($setting->codigo_sistema)) {
            throw new SiatException(
                'Falta el Código de Sistema en la configuración SIAT. Es el que asigna el SIN '
                . 'al autorizar el Sistema Informático de Facturación.'
            );
        }

        return $setting->codigo_sistema;
    }

    /**
     * El SIN informa el resultado en `transaccion` y detalla el motivo en
     * `codigosRespuestas` (o `mensajesList`) cuando algo falla.
     *
     * @param  array<string, mixed>  $respuesta
     */
    private function assertTransaccion(array $respuesta, string $operacion): void
    {
        if (($respuesta['transaccion'] ?? false) === true) {
            return;
        }

        Log::error("SIAT: {$operacion} rechazada", ['respuesta' => $respuesta]);

        throw new SiatException(
            "El SIN rechazó la {$operacion}: " . $this->describirRespuestas($respuesta)
        );
    }

    /** @param  array<string, mixed>  $respuesta */
    private function describirRespuestas(array $respuesta): string
    {
        $mensajes = $respuesta['codigosRespuestas'] ?? $respuesta['mensajesList'] ?? null;

        if (blank($mensajes)) {
            return 'sin detalle en la respuesta.';
        }

        // Una sola respuesta llega como objeto, varias como lista.
        if (isset($mensajes['codigo']) || isset($mensajes['descripcion'])) {
            $mensajes = [$mensajes];
        }

        return collect($mensajes)
            ->map(fn($m) => trim(($m['codigo'] ?? '') . ' ' . ($m['descripcion'] ?? '')))
            ->filter()
            ->implode(' | ');
    }

    private function parseFecha(?string $fecha): ?Carbon
    {
        if (blank($fecha)) {
            return null;
        }

        try {
            return Carbon::parse($fecha);
        } catch (\Throwable) {
            return null;
        }
    }
}
