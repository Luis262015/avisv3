<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiatPuntoVenta;
use App\Models\SiatSetting;
use App\Services\Siat\SiatCodigosService;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatOperacionesService;
use Illuminate\Support\Facades\DB;

/**
 * Alta, selección y baja de puntos de venta ante el SIN.
 *
 * La homologación repite cada caso de las nueve etapas con punto de venta 0 y 1,
 * así que hace falta poder dar de alta un segundo punto y emitir por él.
 *
 * El punto activo se refleja en `siat_settings` (`codigo_punto_venta`, `cuis`,
 * `nombre_punto_venta`), que es de donde leen el CUF, el XML y las cabeceras SOAP.
 * Cambiar de punto es reescribir ese reflejo; lo que no se toca es la cadena de
 * CUFD de cada punto, que sigue su propia numeración.
 */
class SiatPuntoVentaService
{
    public function __construct(
        private readonly SiatOperacionesService $operaciones,
        private readonly SiatCodigosService $codigos,
    ) {}

    /**
     * Registra un punto de venta nuevo ante el SIN y lo guarda con el código que
     * el SIN le asigne.
     *
     * No se puede elegir el número: `registroPuntoVenta` no lo admite como
     * entrada. El registro **no se puede deshacer** salvo dando de baja el punto.
     */
    public function registrar(
        SiatSetting $setting,
        string $nombre,
        string $descripcion,
        int $tipo,
    ): SiatPuntoVenta {
        $codigo = $this->operaciones->registrarPuntoVenta($setting, $nombre, $descripcion, $tipo);

        return SiatPuntoVenta::updateOrCreate(
            [
                'store_id'        => $setting->store_id,
                'codigo_sucursal' => (int) $setting->codigo_sucursal,
                'codigo'          => $codigo,
            ],
            [
                'nombre'      => $nombre,
                'descripcion' => $descripcion,
                'tipo'        => $tipo,
                // El SIN asigna el 0 a la casa matriz; cualquier otro número es un
                // punto de venta corriente.
                'es_principal' => $codigo === 0,
                'estado'      => 'activo',
                'cerrado_at'  => null,
            ],
        );
    }

    /**
     * Trae del SIN los puntos de venta de la sucursal y los concilia con los
     * locales. Es de solo lectura contra el SIN: no registra nada.
     *
     * @return array{sincronizados: int, nuevos: int}
     */
    public function sincronizar(SiatSetting $setting): array
    {
        $remotos = $this->operaciones->consultarPuntosVenta($setting);
        $nuevos  = 0;

        foreach ($remotos as $remoto) {
            $punto = SiatPuntoVenta::firstOrNew([
                'store_id'        => $setting->store_id,
                'codigo_sucursal' => (int) $setting->codigo_sucursal,
                'codigo'          => $remoto['codigoPuntoVenta'],
            ]);

            if (! $punto->exists) {
                $nuevos++;
                $punto->es_principal = $remoto['codigoPuntoVenta'] === 0;
                $punto->estado       = 'activo';
            }

            // El nombre manda el SIN; el CUIS y las fechas son nuestros y no
            // vienen en la consulta, así que no se tocan.
            $punto->nombre = $remoto['nombrePuntoVenta'] ?: $punto->nombre ?: 'Punto de venta';
            $punto->save();
        }

        return ['sincronizados' => count($remotos), 'nuevos' => $nuevos];
    }

    /**
     * Pide al SIN el CUIS de un punto de venta.
     *
     * Cada pareja sucursal/punto de venta tiene el suyo, y el SIN no permite
     * volver a consultarlo: si se pierde, hay que dar de baja el punto. Se pide
     * activando el punto temporalmente, porque `solicitarCuis` lee de la
     * configuración.
     */
    public function solicitarCuis(SiatSetting $setting, SiatPuntoVenta $punto): string
    {
        if ($punto->cuisVigente()) {
            throw new SiatException(
                "El punto de venta {$punto->codigo} ya tiene un CUIS vigente. "
                . 'El SIN no entrega otro mientras siga en vigor.'
            );
        }

        return $this->conPuntoActivo($setting, $punto, fn () => $this->codigos->solicitarCuis($setting));
    }

    /**
     * Deja este punto como el que emite. Todo lo demás —CUF, XML, cabeceras— lee
     * de la configuración, así que basta con reflejarlo ahí.
     */
    public function activar(SiatSetting $setting, SiatPuntoVenta $punto): void
    {
        $this->comprobarPertenencia($setting, $punto);

        if ($punto->estado !== 'activo') {
            throw new SiatException("El punto de venta {$punto->codigo} está cerrado ante el SIN.");
        }

        if (blank($punto->cuis)) {
            throw new SiatException(
                "El punto de venta {$punto->codigo} no tiene CUIS. Solicítelo antes de emitir por él: "
                . 'el SIN no entrega el CUFD sin CUIS.'
            );
        }

        DB::transaction(function () use ($setting, $punto) {
            $setting->update([
                'codigo_punto_venta'   => $punto->codigo,
                'nombre_punto_venta'   => $punto->nombre,
                'cuis'                 => $punto->cuis,
                'cuis_fecha_solicitud' => $punto->cuis_fecha_solicitud,
                'cuis_fecha_vigencia'  => $punto->cuis_fecha_vigencia,
            ]);
        });
    }

    /**
     * Da de baja el punto ante el SIN. Es irreversible y libera su CUIS.
     */
    public function cerrar(SiatSetting $setting, SiatPuntoVenta $punto): void
    {
        $this->comprobarPertenencia($setting, $punto);

        if ($punto->es_principal) {
            throw new SiatException('La casa matriz no se puede dar de baja.');
        }

        if (blank($punto->cuis)) {
            throw new SiatException("El punto de venta {$punto->codigo} no tiene CUIS con el que identificarse.");
        }

        if ((int) $setting->codigo_punto_venta === $punto->codigo) {
            throw new SiatException(
                'No se puede cerrar el punto de venta que está emitiendo. Active otro antes.'
            );
        }

        $this->operaciones->cerrarPuntoVenta($setting, $punto->codigo, $punto->cuis);

        $punto->update(['estado' => 'cerrado', 'cerrado_at' => now()]);
    }

    /**
     * Ejecuta algo con otro punto de venta activo y restaura el anterior pase lo
     * que pase. Lo necesita la solicitud de CUIS, que lee de la configuración.
     */
    private function conPuntoActivo(SiatSetting $setting, SiatPuntoVenta $punto, \Closure $accion): mixed
    {
        $this->comprobarPertenencia($setting, $punto);

        $previo = [
            'codigo_punto_venta'   => $setting->codigo_punto_venta,
            'nombre_punto_venta'   => $setting->nombre_punto_venta,
            'cuis'                 => $setting->cuis,
            'cuis_fecha_solicitud' => $setting->cuis_fecha_solicitud,
            'cuis_fecha_vigencia'  => $setting->cuis_fecha_vigencia,
        ];

        $setting->forceFill([
            'codigo_punto_venta' => $punto->codigo,
            'nombre_punto_venta' => $punto->nombre,
            'cuis'               => $punto->cuis,
        ])->save();

        try {
            return $accion();
        } finally {
            $setting->refresh();

            // Si la acción dejó un CUIS nuevo, es del punto que se estaba
            // atendiendo: se conserva ahí y la configuración vuelve a lo suyo.
            $setting->forceFill($previo)->save();
        }
    }

    private function comprobarPertenencia(SiatSetting $setting, SiatPuntoVenta $punto): void
    {
        if ($punto->store_id !== $setting->store_id) {
            throw new SiatException('Ese punto de venta es de otra tienda.');
        }
    }
}
