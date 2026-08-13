<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eventos significativos: los cortes durante los que se factura sin conexión.
 *
 * Cuando el SIN no está disponible se sigue vendiendo y se emite "fuera de línea"
 * (tipoEmision 2) contra el último CUFD vigente. Al volver la conexión hay que
 * declarar el corte con `registroEventoSignificativo` y enviar las facturas en
 * paquete citando el código de recepción del evento, dentro del plazo que fija la
 * normativa. Sin este registro las facturas del corte no tienen respaldo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siat_eventos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // El CUFD que estaba vigente durante el corte: es el que el SIN pide
            // como `cufdEvento`, distinto del CUFD con el que se registra.
            $table->foreignId('cufd_code_id')->nullable()->constrained('siat_cufd_codes')->nullOnDelete();

            $table->unsignedSmallInteger('codigo_motivo_evento')
                ->comment('Paramétrica sincronizarParametricaEventosSignificativos');
            $table->string('descripcion', 500);

            // El CAFC es del corte, no de la configuración, y NO se aplica a todos
            // los motivos: para el evento 2 el piloto respondió "1045 Cafc esperado
            // null". Se guarda aquí el que corresponda, o nada.
            $table->string('cafc', 50)->nullable();

            // dateTime y no timestamp: en MySQL la primera columna TIMESTAMP NOT NULL
            // se lleva un ON UPDATE CURRENT_TIMESTAMP implícito que reescribiría estas
            // fechas en cada actualización de la fila.
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable()->comment('Nulo mientras el corte sigue abierto');

            $table->string('codigo_recepcion_evento', 100)->nullable();
            $table->string('estado', 20)->default('abierto')->comment('abierto|cerrado|registrado');
            $table->text('mensaje_error')->nullable();

            $table->timestamps();

            $table->index(['store_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siat_eventos');
    }
};
