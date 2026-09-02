<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separa «cuántas pruebas pide el caso» de «cuántas facturas lleva cada lote».
 *
 * `cantidad` valía para las dos cosas y no da: en las etapas VI y IX un caso son
 * 10 pruebas, y cada prueba manda un paquete de 500 o 1000 facturas. Confundirlas
 * hacía que un solo envío diera el caso por terminado.
 *
 * De paso se añade `catalogo`, porque la etapa II no es un caso por punto de
 * venta sino uno por cada catálogo del servicio de sincronización: 18 catálogos
 * por 2 puntos de venta son los 36 casos que el Portal cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siat_homologacion_casos', function (Blueprint $table) {
            $table->unsignedInteger('tamano_lote')->nullable()->after('cantidad')
                ->comment('Facturas por paquete en las etapas VI y IX');
            $table->string('catalogo', 40)->nullable()->after('motivo_evento')
                ->comment('Catálogo de sincronización que consume el caso (etapa II)');
        });
    }

    public function down(): void
    {
        Schema::table('siat_homologacion_casos', function (Blueprint $table) {
            $table->dropColumn(['tamano_lote', 'catalogo']);
        });
    }
};
