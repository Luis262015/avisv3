<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue el envío de un paquete de su validación.
 *
 * Las etapas VI y IX del Excel puntúan dos cosas distintas por cada lote: el
 * envío, que responde 901 PENDIENTE, y la consulta posterior con
 * `validacionRecepcionPaquete`, que responde 908 RECEPCION VALIDADA. Son casos
 * separados en el apartado de seguimiento, así que la matriz necesita saber
 * cuál es cuál.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siat_homologacion_casos', function (Blueprint $table): void {
            $table->string('operacion')->default('envio')->after('catalogo');
        });
    }

    public function down(): void
    {
        Schema::table('siat_homologacion_casos', function (Blueprint $table): void {
            $table->dropColumn('operacion');
        });
    }
};
