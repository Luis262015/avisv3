<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `promotions.type` pasa de enum a texto.
 *
 * La migración que añadió el tipo `combo` solo amplió el enum **en MySQL**, con
 * la idea de que SQLite ignora los enum. No los ignora: la gramática de SQLite de
 * Laravel los traduce a un CHECK constraint, que se quedó con los tres tipos
 * originales. Resultado: en la base de pruebas era imposible crear un combo, así
 * que toda la funcionalidad quedó sin poder probarse —y sin probar.
 *
 * Se deja como texto en ambos motores para que los valores admitidos vivan en un
 * único sitio, `PromotionRequest`, en vez de repartidos entre dos gramáticas SQL
 * que no se comportan igual. Lo mismo con `scope`, que arrastra el mismo riesgo
 * en cuanto haga falta un alcance nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->string('type', 20)->default('percentage')->change();
            $table->string('scope', 20)->default('all')->change();
        });
    }

    public function down(): void
    {
        // Se vuelve al enum con los cuatro tipos ya en uso: reponer solo los tres
        // originales rompería las promociones de tipo combo existentes.
        Schema::table('promotions', function (Blueprint $table): void {
            $table->enum('type', ['percentage', 'fixed', 'buy_x_get_y', 'combo'])->change();
            $table->enum('scope', ['all', 'product', 'category'])->default('all')->change();
        });
    }
};
