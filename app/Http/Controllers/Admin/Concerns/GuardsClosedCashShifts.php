<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Impide modificar movimientos de caja que pertenecen a un turno ya cerrado.
 *
 * El cierre calcula y almacena el monto esperado; editar después el importe de un
 * gasto, ingreso o retiro de ese turno deja el arqueo firmado contradiciendo a sus
 * propios movimientos.
 */
trait GuardsClosedCashShifts
{
    /**
     * @return string|null  Mensaje de error, o null si la edición es admisible.
     */
    protected function closedShiftError(Model $entry): ?string
    {
        $entry->loadMissing('cashShift');

        if ($entry->cashShift && ! $entry->cashShift->isOpen()) {
            return 'Este movimiento pertenece a un turno de caja ya cerrado y no puede modificarse.';
        }

        return null;
    }
}
