<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Punto de venta que emite en modalidad masiva (tipoEmision 3).
 *
 * No es lo mismo que la contingencia: aquí sí hay conexión, pero las facturas no
 * se envían de una en una sino agrupadas en lotes. El tipo de emisión va dentro
 * del CUF, así que la decisión tiene que tomarse al emitir y no al enviar: un CUF
 * calculado como "en línea" hace que el SIN rechace el lote masivo entero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siat_settings', function (Blueprint $table): void {
            $table->boolean('emision_masiva')->default(false)->after('tipo_factura_default');
        });
    }

    public function down(): void
    {
        Schema::table('siat_settings', function (Blueprint $table): void {
            $table->dropColumn('emision_masiva');
        });
    }
};
