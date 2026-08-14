<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mínimo de existencias por tienda.
 *
 * `products.min_stock` es uno solo para toda la empresa, así que una sucursal
 * pequeña y el almacén central se juzgaban contra la misma cifra: falsos avisos
 * en una y silencio en la otra.
 *
 * Se deja **nullable a propósito**: null significa "usa el mínimo del producto".
 * Así las tiendas que no necesitan un criterio propio no obligan a mantener una
 * cifra por cada par tienda-producto, y `products.min_stock` sigue sirviendo de
 * respaldo en vez de quedar muerto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_product_stocks', function (Blueprint $table): void {
            $table->unsignedInteger('min_stock')->nullable()->after('stock')
                ->comment('Mínimo propio de la tienda; null = usar products.min_stock');
        });
    }

    public function down(): void
    {
        Schema::table('store_product_stocks', function (Blueprint $table): void {
            $table->dropColumn('min_stock');
        });
    }
};
