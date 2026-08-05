<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gastos e ingresos solo alcanzaban su tienda a través del turno de caja, así que
 * los que no tienen turno (tarjeta, transferencia) quedaban fuera al filtrar los
 * reportes por tienda. Con store_id propio cada movimiento es atribuible siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['expenses', 'incomes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('store_id')->nullable()->after('cash_shift_id')
                    ->constrained()->nullOnDelete();
            });

            $this->backfillFromCashShift($tabla);
        }

        $this->backfillFromSingleStore();
    }

    public function down(): void
    {
        foreach (['expenses', 'incomes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('store_id');
            });
        }
    }

    /**
     * Los movimientos con turno ya tenían tienda implícita: la de su caja.
     */
    private function backfillFromCashShift(string $tabla): void
    {
        DB::table($tabla)
            ->whereNull('store_id')
            ->whereNotNull('cash_shift_id')
            ->orderBy('id')
            ->each(function ($fila) use ($tabla) {
                $storeId = DB::table('cash_shifts')
                    ->join('cash_registers', 'cash_registers.id', '=', 'cash_shifts.cash_register_id')
                    ->where('cash_shifts.id', $fila->cash_shift_id)
                    ->value('cash_registers.store_id');

                if ($storeId) {
                    DB::table($tabla)->where('id', $fila->id)->update(['store_id' => $storeId]);
                }
            });
    }

    /**
     * Con una sola tienda registrada no hay ambigüedad posible: los movimientos
     * sueltos le pertenecen. Con varias se dejan sin asignar antes que adivinar.
     */
    private function backfillFromSingleStore(): void
    {
        $stores = DB::table('stores')->pluck('id');

        if ($stores->count() !== 1) {
            return;
        }

        foreach (['expenses', 'incomes'] as $tabla) {
            DB::table($tabla)->whereNull('store_id')->update(['store_id' => $stores->first()]);
        }
    }
};
