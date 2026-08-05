<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las compras y órdenes de compra antiguas podían quedarse sin tienda, porque
 * store_id era opcional. El inventario se lleva por tienda (store_product_stocks),
 * así que una compra sin tienda no puede recibirse: se le asigna la tienda
 * predeterminada para que el flujo de recepción vuelva a estar disponible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaultStoreId = DB::table('stores')
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id')
            ?? DB::table('stores')->orderBy('id')->value('id');

        // Sin ninguna tienda cargada no hay nada que asignar: las validaciones
        // nuevas obligan a crear una antes de registrar compras.
        if (! $defaultStoreId) {
            return;
        }

        DB::table('purchases')->whereNull('store_id')->update(['store_id' => $defaultStoreId]);
        DB::table('purchase_orders')->whereNull('store_id')->update(['store_id' => $defaultStoreId]);

        // Deliberadamente NO se tocan los inventory_movements históricos sin tienda:
        // esas entradas nunca sumaron a store_product_stocks, así que asignarles una
        // tienda ahora haría que el historial afirme un stock que jamás existió allí.
        // El descuadre de esas recepciones se corrige con un ajuste de inventario.
    }

    public function down(): void
    {
        // Irreversible por diseño: no se puede saber qué filas tenían store_id nulo.
    }
};
