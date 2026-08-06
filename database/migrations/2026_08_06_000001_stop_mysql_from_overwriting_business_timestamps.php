<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quita el automatismo que MySQL aplicó a dos fechas de negocio.
 *
 * Cuando `explicit_defaults_for_timestamp` está apagado, MySQL le pone
 * `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` a la primera columna
 * TIMESTAMP NOT NULL de la tabla si la migración no declara un default. Eso
 * convierte cualquier UPDATE de la fila en una reescritura silenciosa de la
 * fecha:
 *
 *  - `siat_cufd_codes.fecha_vigencia`: `nextConsecutivo()` incrementa un
 *    contador en cada factura, y ese UPDATE dejaba el CUFD vencido al instante.
 *  - `cash_shifts.opened_at`: cerrar el turno reescribía su hora de apertura.
 *
 * DATETIME no tiene ese comportamiento en ningún caso, y el valor visible para
 * la aplicación no cambia: la conversión usa la zona de sesión, la misma que
 * MySQL venía aplicando al leer.
 */
return new class extends Migration
{
    /** @var array<string, string> tabla => columna */
    private const COLUMNAS = [
        'siat_cufd_codes' => 'fecha_vigencia',
        'cash_shifts'     => 'opened_at',
    ];

    public function up(): void
    {
        // Solo MySQL tiene este automatismo; en SQLite (los tests) no hay nada que
        // quitar y `MODIFY` ni siquiera existe.
        if (! $this->esMysql()) {
            return;
        }

        foreach (self::COLUMNAS as $tabla => $columna) {
            if (Schema::hasColumn($tabla, $columna)) {
                DB::statement("ALTER TABLE `{$tabla}` MODIFY `{$columna}` DATETIME NOT NULL");
            }
        }
    }

    public function down(): void
    {
        if (! $this->esMysql()) {
            return;
        }

        foreach (self::COLUMNAS as $tabla => $columna) {
            if (Schema::hasColumn($tabla, $columna)) {
                DB::statement("ALTER TABLE `{$tabla}` MODIFY `{$columna}` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }
        }
    }

    private function esMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], strict: true);
    }
};
