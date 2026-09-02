<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de los casos de prueba de la homologación Fase I.
 *
 * Las etapas piden volumen —500 emisiones individuales, 250 anulaciones, lotes de
 * 500 y de 1000 facturas—, repetido con punto de venta 0 y 1 y por cada documento
 * sector de la actividad. Eso no se hace desde el POS ni se lleva de memoria: cada
 * intento queda aquí con lo que respondió el SIN, para poder reanudar donde se
 * quedó y para saber qué falta sin tener que preguntárselo al Portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siat_homologacion_casos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('etapa')->comment('1..9, las etapas de la Fase I');
            $table->string('caso', 60)->comment('Identificador estable del caso dentro de la etapa');

            $table->unsignedInteger('punto_venta');
            $table->unsignedSmallInteger('documento_sector')->nullable();
            $table->unsignedTinyInteger('tipo_factura')->nullable();
            $table->unsignedTinyInteger('motivo_evento')->nullable();
            $table->unsignedInteger('cantidad')->default(1)
                ->comment('Documentos que exige el caso: 1 individual, 500 o 1000 por lote');

            $table->unsignedInteger('completados')->default(0);
            $table->string('estado', 20)->default('pendiente')
                ->comment('pendiente|en_curso|completado|fallido');
            $table->string('codigo_resultado', 20)->nullable()->comment('Código de estado del SIN');
            $table->text('mensaje')->nullable();
            $table->string('referencia', 200)->nullable()->comment('CUF, código de recepción o de evento');
            $table->timestamp('ejecutado_at')->nullable();

            $table->timestamps();

            $table->unique(['store_id', 'etapa', 'caso'], 'siat_homologacion_caso_unico');
            $table->index(['store_id', 'etapa', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siat_homologacion_casos');
    }
};
