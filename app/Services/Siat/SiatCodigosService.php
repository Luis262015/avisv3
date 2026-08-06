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

        // El 980 no es un fallo: significa que el SIN ya emitió un CUIS para esta
        // sucursal y punto de venta. No hay operación para recuperarlo, así que si
        // no está guardado hay que recuperarlo de los registros propios.
        if ($this->tieneCodigo($respuesta, 980)) {
            throw new SiatException(
                'El SIN ya tiene un CUIS vigente para la sucursal ' . (int) $setting->codigo_sucursal
                . ' y el punto de venta ' . (int) $setting->codigo_punto_venta . ', y no entrega uno nuevo. '
                . 'Los servicios no permiten volver a consultarlo: recupérelo de sus registros y cárguelo '
                . 'en la configuración, o dé de baja el punto de venta en el Portal SIAT para empezar de cero.'
            );
        }

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

    /**
     * Revisa credenciales, coherencia y conectividad sin registrar nada en el SIN.
     *
     * @return list<string> Problemas encontrados; vacío si la configuración sirve
     *                      para operar.
     */
    public function diagnosticar(SiatSetting $setting): array
    {
        if (blank($setting->token_api)) {
            return ['La configuración no tiene Token Delegado. Obténgalo en el Portal SIAT y cárguelo aquí.'];
        }

        $token = SiatToken::parse($setting->token_api);

        if ($token === null) {
            return ['El Token Delegado no tiene el formato JWT que emite el Portal SIAT; puede estar truncado.'];
        }

        // Una incoherencia entre el token y la configuración hace fallar toda
        // operación, así que no vale la pena seguir hasta resolverla.
        if ($problemas = $token->incoherenciasCon($setting)) {
            return $problemas;
        }

        try {
            $this->verificarComunicacion($setting);
        } catch (SiatException $e) {
            return [$e->getMessage()];
        }

        return $this->problemasDeAutenticacion($setting);
    }

    /**
     * `verificarComunicacion` solo valida la firma del token: la aprueban tanto el
     * piloto como producción, aunque el sistema no esté habilitado en ninguno de
     * los dos. Se sondea además con `verificarNit`, que es de solo lectura y sí
     * exige un token del ambiente al que se apunta.
     *
     * @return list<string>
     */
    private function problemasDeAutenticacion(SiatSetting $setting): array
    {
        $client = $this->clientFor($setting);

        try {
            $client->call(self::SERVICIO, 'verificarNit', [
                'codigoAmbiente'      => $client->codigoAmbiente(),
                'codigoModalidad'     => (int) $setting->modalidad,
                'codigoSistema'       => (string) $setting->codigo_sistema,
                'codigoSucursal'      => (int) $setting->codigo_sucursal,
                'cuis'                => $setting->cuis ?: 'SINCUIS',
                'nit'                 => (int) $setting->nit,
                'nitParaVerificacion' => (int) $setting->nit,
            ], envoltura: 'SolicitudVerificarNit');
        } catch (SiatException $e) {
            if (str_contains($e->getMessage(), 'API KEY NO VALIDO')) {
                return [sprintf(
                    'El SIN no acepta el Token Delegado en el ambiente "%s". Lo habitual es que el token '
                    . 'sea del otro ambiente: piloto y producción se autorizan por separado en el Portal SIAT.',
                    $setting->ambiente
                )];
            }

            return [$e->getMessage()];
        }

        // Cualquier respuesta de negocio (p. ej. "CUIS no asociado") significa que
        // la autenticación pasó, que es lo único que se estaba comprobando.
        return [];
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

    /**
     * ¿La respuesta trae este código del SIN entre sus mensajes?
     *
     * @param  array<string, mixed>  $respuesta
     */
    private function tieneCodigo(array $respuesta, int $codigo): bool
    {
        $mensajes = $respuesta['codigosRespuestas'] ?? $respuesta['mensajesList'] ?? [];

        // Un solo mensaje llega como objeto; varios, como lista.
        if (isset($mensajes['codigo'])) {
            $mensajes = [$mensajes];
        }

        foreach ($mensajes as $mensaje) {
            if ((int) ($mensaje['codigo'] ?? 0) === $codigo) {
                return true;
            }
        }

        return false;
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
