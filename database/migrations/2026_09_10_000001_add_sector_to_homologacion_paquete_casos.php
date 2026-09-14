<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mete el documento sector en el identificador de los casos de las etapas VI y IX.
 *
 * Esas dos etapas se generaban solo para la factura de compra-venta, así que sus
 * casos no necesitaban decir de qué sector eran: `e9-pv0-n1000`. Pero el SIN las
 * puntúa **por cada sector asociado al NIT** —cuatro, no uno—, y sin el sector en
 * el identificador los casos de los otros tres chocarían con estos en el índice
 * único (store_id, etapa, caso).
 *
 * Se renombran en vez de borrarse: llevan dentro las 100 pruebas de la etapa VI y
 * las 80 de la IX que ya aceptó el piloto.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->renombrar(fn (string $caso, int $sector) => preg_match('/^e\d+-s\d+-/', $caso) === 1
            ? $caso
            : preg_replace('/^(e\d+)-/', "$1-s{$sector}-", $caso));
    }

    public function down(): void
    {
        $this->renombrar(fn (string $caso) => preg_replace('/^(e\d+)-s\d+-/', '$1-', $caso));
    }

    private function renombrar(callable $nuevo): void
    {
        $casos = DB::table('siat_homologacion_casos')
            ->whereIn('etapa', [6, 9])
            ->get(['id', 'caso', 'documento_sector']);

        foreach ($casos as $caso) {
            $renombrado = $nuevo((string) $caso->caso, (int) ($caso->documento_sector ?: 1));

            if ($renombrado !== $caso->caso) {
                DB::table('siat_homologacion_casos')->where('id', $caso->id)->update(['caso' => $renombrado]);
            }
        }
    }
};
