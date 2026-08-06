<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homologación de productos con los catálogos del SIN.
 *
 * Cada línea de una factura debe declarar el código de producto del SIN y su
 * unidad de medida, ambos de las paramétricas oficiales
 * (`sincronizarListaProductosServicios` y `sincronizarParametricaUnidadMedida`).
 * Sin esto no se puede emitir: son campos obligatorios del XSD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('codigo_producto_sin')->nullable()->after('barcode');
            $table->unsignedSmallInteger('unidad_medida_sin')->nullable()->after('codigo_producto_sin');
        });

        Schema::table('siat_settings', function (Blueprint $table): void {
            // La leyenda es obligatoria en la factura y depende de la actividad
            // económica; se cachea aquí la elegida para no depender del SIN al emitir.
            $table->string('leyenda', 200)->nullable()->after('actividad_descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['codigo_producto_sin', 'unidad_medida_sin']);
        });

        Schema::table('siat_settings', function (Blueprint $table): void {
            $table->dropColumn('leyenda');
        });
    }
};
