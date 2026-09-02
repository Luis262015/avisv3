<?php

namespace App\Console\Commands;

use App\Models\SiatSetting;
use App\Services\Siat\SiatSincronizacionService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vuelca las paramétricas del SIN por consola.
 *
 * Es de solo lectura: sincronizar catálogos no registra nada en el SIN, así que
 * se puede correr contra el piloto sin gastar correlativos ni dejar rastro. Sirve
 * para dos cosas: averiguar el alcance real de la homologación —qué documentos
 * sector admite la actividad del contribuyente— y cubrir la etapa II, que consiste
 * precisamente en consumir las 18 operaciones de sincronización.
 */
class SiatParametricas extends Command
{
    protected $signature = 'siat:parametricas
        {catalogo? : Catálogo concreto a volcar; sin él se listan todos}
        {--tienda= : ID de la tienda; por defecto la primera con configuración activa}
        {--refrescar : Ignora la caché y vuelve a preguntar al SIN}';

    protected $description = 'Consulta las paramétricas del SIN (solo lectura)';

    public function handle(SiatSincronizacionService $sincronizacion): int
    {
        $setting = $this->setting();

        if (! $setting) {
            $this->error('No hay ninguna configuración SIAT que usar.');

            return self::FAILURE;
        }

        $this->line("Tienda <info>{$setting->store_id}</info> · NIT <info>{$setting->nit}</info> · "
            . "ambiente <info>{$setting->ambiente}</info> · sucursal {$setting->codigo_sucursal} · "
            . "punto de venta {$setting->codigo_punto_venta}");
        $this->newLine();

        if ($this->option('refrescar')) {
            $sincronizacion->olvidarCache($setting);
        }

        $catalogo = $this->argument('catalogo');

        if ($catalogo !== null && ! isset(SiatSincronizacionService::CATALOGOS[$catalogo])) {
            $this->error("Catálogo desconocido: {$catalogo}");
            $this->line('Disponibles: ' . implode(', ', array_keys(SiatSincronizacionService::CATALOGOS)));

            return self::FAILURE;
        }

        return $catalogo === null
            ? $this->resumen($sincronizacion, $setting)
            : $this->detalle($sincronizacion, $setting, $catalogo);
    }

    /**
     * Recorre los 17 catálogos y cuenta lo que devuelve cada uno. Un catálogo que
     * falla no interrumpe a los demás: interesa saber cuáles responden.
     */
    private function resumen(SiatSincronizacionService $sincronizacion, SiatSetting $setting): int
    {
        $filas  = [];
        $fallos = 0;

        foreach (SiatSincronizacionService::CATALOGOS as $clave => $metodo) {
            try {
                $datos   = $sincronizacion->{$metodo}($setting);
                $filas[] = [$clave, count($datos), 'OK'];
            } catch (Throwable $e) {
                $fallos++;
                $filas[] = [$clave, '—', mb_strimwidth($e->getMessage(), 0, 60, '…')];
            }
        }

        $this->table(['Catálogo', 'Elementos', 'Resultado'], $filas);

        try {
            $this->line('Hora del SIN: <info>' . $sincronizacion->fechaHora($setting) . '</info>');
        } catch (Throwable $e) {
            $fallos++;
            $this->warn('sincronizarFechaHora falló: ' . $e->getMessage());
        }

        return $fallos === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function detalle(
        SiatSincronizacionService $sincronizacion,
        SiatSetting $setting,
        string $catalogo,
    ): int {
        $metodo = SiatSincronizacionService::CATALOGOS[$catalogo];

        try {
            $datos = $sincronizacion->{$metodo}($setting);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($datos === []) {
            $this->warn('El SIN no devolvió nada para este catálogo.');

            return self::SUCCESS;
        }

        $this->table(['Clave', 'Valor'], $this->aplanar($datos));
        $this->newLine();
        $this->line('Total: <info>' . count($datos) . '</info>');

        return self::SUCCESS;
    }

    /**
     * Aplana para la tabla. Casi todos los catálogos son código => descripción,
     * pero leyendas, productos y documentos sector vienen agrupados por actividad.
     *
     * @param  array<mixed>  $datos
     * @return list<array{0: string, 1: string}>
     */
    private function aplanar(array $datos): array
    {
        $filas = [];

        foreach ($datos as $clave => $valor) {
            if (is_scalar($valor)) {
                $filas[] = [(string) $clave, (string) $valor];

                continue;
            }

            foreach ((array) $valor as $subclave => $subvalor) {
                $filas[] = [
                    "{$clave} · {$subclave}",
                    is_scalar($subvalor) ? (string) $subvalor : json_encode($subvalor, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return $filas;
    }

    private function setting(): ?SiatSetting
    {
        $tienda = $this->option('tienda');

        return $tienda !== null
            ? SiatSetting::where('store_id', $tienda)->first()
            : SiatSetting::where('is_active', true)->orderBy('store_id')->first();
    }
}
