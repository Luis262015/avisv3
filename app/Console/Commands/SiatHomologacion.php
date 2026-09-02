<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SiatHomologacionCaso;
use App\Models\SiatSetting;
use App\Services\Siat\HomologacionMatriz;
use App\Services\Siat\HomologacionRunner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ejecuta los casos de la homologación Fase I contra el ambiente de pruebas.
 *
 * Las etapas piden volumen —500 emisiones, 250 anulaciones, lotes de 500 y 1000
 * facturas— repetido con punto de venta 0 y 1 y por cada documento sector: no se
 * hace desde el POS. Cada caso queda registrado, así que se puede parar y seguir.
 *
 * Emite documentos fiscales de verdad en el ambiente configurado, por eso pide
 * confirmación y se niega a correr en producción.
 */
class SiatHomologacion extends Command
{
    protected $signature = 'siat:homologacion
        {etapa? : Etapa a ejecutar (2, 4, 5, 6, 7, 9); sin ella solo muestra el estado}
        {--tienda= : ID de la tienda; por defecto la primera con configuración activa}
        {--caso= : Ejecuta solo este caso (p. ej. e4-s24-pv1)}
        {--limit= : Máximo de documentos a emitir en esta pasada}
        {--total= : Sobrescribe el volumen oficial de la etapa al generar la matriz}
        {--dry-run : Genera la matriz y enseña qué haría, sin tocar el SIN}
        {--force : No pide confirmación}';

    protected $description = 'Ejecuta los casos de prueba de la homologación SIAT Fase I';

    public function handle(HomologacionMatriz $matriz, HomologacionRunner $runner): int
    {
        $setting = $this->setting();

        if (! $setting) {
            $this->error('No hay ninguna configuración SIAT que usar.');

            return self::FAILURE;
        }

        if ($setting->ambiente === 'produccion') {
            $this->error('La homologación se corre contra el piloto, nunca contra producción.');

            return self::FAILURE;
        }

        $this->line("Tienda <info>{$setting->store_id}</info> · NIT <info>{$setting->nit}</info> · "
            . "ambiente <info>{$setting->ambiente}</info>");

        $etapa = $this->argument('etapa');

        if ($etapa === null) {
            return $this->estado($setting);
        }

        $etapa = (int) $etapa;

        if (! in_array($etapa, HomologacionMatriz::EJECUTABLES, true)) {
            $this->error('Etapa no ejecutable desde aquí. Disponibles: '
                . implode(', ', HomologacionMatriz::EJECUTABLES) . '.');
            $this->line('  I y III (CUIS y CUFD) se cubren al dar de alta cada punto de venta.');
            $this->line('  VIII (firma digital) no aplica a la modalidad computarizada.');

            return self::FAILURE;
        }

        try {
            $casos = $matriz->generar($setting, $etapa, $this->option('total') ? (int) $this->option('total') : null);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('caso')) {
            $casos = array_values(array_filter($casos, fn ($c) => $c->caso === $this->option('caso')));

            if ($casos === []) {
                $this->error("El caso {$this->option('caso')} no está en la matriz de la etapa {$etapa}.");

                return self::FAILURE;
            }
        }

        $pendientes = array_values(array_filter($casos, fn ($c) => ! $c->estaCompleto()));

        $this->tabla($casos);

        if ($pendientes === []) {
            $this->info('La etapa ya está completa.');

            return self::SUCCESS;
        }

        $porHacer = array_sum(array_map(fn ($c) => $c->restantes(), $pendientes));
        $limite   = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($this->option('dry-run')) {
            $this->warn("Ensayo: quedan {$porHacer} documento(s) en " . count($pendientes) . ' caso(s). No se envió nada.');

            return self::SUCCESS;
        }

        $aviso = "Se van a emitir documentos fiscales reales en el ambiente «{$setting->ambiente}»"
            . ($limite ? " (máximo {$limite} por caso)" : " ({$porHacer} en total)") . '. ¿Continuar?';

        if (! $this->option('force') && ! $this->confirm($aviso, false)) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        return $this->correr($runner, $setting, $pendientes, $limite);
    }

    /**
     * @param  list<SiatHomologacionCaso>  $casos
     */
    private function correr(
        HomologacionRunner $runner,
        SiatSetting $setting,
        array $casos,
        ?int $limite,
    ): int {
        $fallidos = 0;

        foreach ($casos as $caso) {
            $this->line("→ <info>{$caso->caso}</info> (faltan {$caso->restantes()})");

            try {
                $hechos = $runner->ejecutar($caso, $setting->refresh(), $limite);
                $this->line("   {$hechos} documento(s); resultado " . ($caso->fresh()->codigo_resultado ?? '—'));
            } catch (Throwable $e) {
                $fallidos++;
                $this->error('   ' . $e->getMessage());
            }
        }

        $this->newLine();
        $this->estado($setting);

        return $fallidos === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function estado(SiatSetting $setting): int
    {
        $casos = SiatHomologacionCaso::where('store_id', $setting->store_id)
            ->orderBy('etapa')->orderBy('caso')->get();

        if ($casos->isEmpty()) {
            $this->warn('Todavía no se ha generado ninguna matriz. Pase una etapa como argumento.');

            return self::SUCCESS;
        }

        $filas = $casos->groupBy('etapa')->map(function ($grupo, $etapa) {
            $total       = $grupo->sum('cantidad');
            $completados = $grupo->sum('completados');
            $fallidos    = $grupo->where('estado', 'fallido')->count();

            return [
                "Etapa {$etapa}",
                $grupo->count(),
                "{$completados} / {$total}",
                $total > 0 ? round($completados / $total * 100) . ' %' : '—',
                $fallidos ?: '',
            ];
        })->values()->all();

        $this->table(['Etapa', 'Casos', 'Documentos', 'Avance', 'Fallidos'], $filas);

        return self::SUCCESS;
    }

    /** @param list<SiatHomologacionCaso> $casos */
    private function tabla(array $casos): void
    {
        $this->table(
            ['Caso', 'PV', 'Sector', 'Motivo', 'Hechos', 'Estado', 'Resultado'],
            array_map(fn (SiatHomologacionCaso $c) => [
                $c->caso,
                $c->punto_venta,
                $c->documento_sector ?? '—',
                $c->motivo_evento ?? '—',
                "{$c->completados} / {$c->cantidad}",
                $c->estado,
                mb_strimwidth((string) ($c->codigo_resultado ?? $c->mensaje ?? ''), 0, 40, '…'),
            ], $casos),
        );
    }

    private function setting(): ?SiatSetting
    {
        $tienda = $this->option('tienda');

        return $tienda !== null
            ? SiatSetting::where('store_id', $tienda)->first()
            : SiatSetting::where('is_active', true)->orderBy('store_id')->first();
    }
}
