<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siat_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('nit', 13)->comment('NIT del emisor (13 dígitos)');
            $table->string('razon_social', 150);
            $table->string('municipio', 100);
            $table->string('telefono', 30)->nullable();
            $table->string('direccion', 250)->nullable();
            $table->string('actividad_economica', 10)->comment('Código CAEB (ej: 470000)');
            $table->string('actividad_descripcion', 200)->nullable();
            $table->unsignedSmallInteger('codigo_sucursal')->default(0)->comment('0 = casa matriz');
            $table->unsignedSmallInteger('codigo_punto_venta')->default(0);
            $table->string('nombre_punto_venta', 50)->default('Principal');
            $table->tinyInteger('modalidad')->default(2)->comment('1=En línea, 2=Fuera de línea');
            $table->string('ambiente', 20)->default('simulado')->comment('piloto|produccion|simulado');
            $table->tinyInteger('tipo_factura_default')->default(2)->comment('1=con CF, 2=sin CF');
            $table->string('cuis', 512)->nullable()->comment('Código Único de Inicio de Sistema');
            $table->timestamp('cuis_fecha_solicitud')->nullable();
            $table->text('token_api')->nullable()->comment('Token para API SIN');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siat_settings');
    }
};
