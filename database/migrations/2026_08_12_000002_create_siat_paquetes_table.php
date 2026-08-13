<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envíos por lote al SIN.
 *
 * `recepcionPaqueteFactura` no valida en el momento: devuelve un código de
 * recepción y la validación real se consulta después con
 * `validacionRecepcionPaquete`. Hace falta guardar ese código o el lote queda
 * enviado y sin forma de saber si el SIN lo aceptó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siat_paquetes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            // El envío masivo (emisión 3) no nace de un corte, así que no lleva evento.
            $table->foreignId('evento_id')->nullable()->constrained('siat_eventos')->nullOnDelete();

            $table->string('tipo', 20)->default('paquete')->comment('paquete|masivo');
            $table->unsignedInteger('cantidad_facturas')->default(0);
            $table->string('hash_archivo', 64)->nullable()->comment('SHA-256 del .tar.gz enviado');

            $table->string('codigo_recepcion', 100)->nullable();
            $table->unsignedSmallInteger('codigo_estado')->nullable()->comment('Estado que informa el SIN');
            $table->string('estado', 20)->default('pendiente')
                ->comment('pendiente|enviado|validado|rechazado');
            $table->text('mensaje_error')->nullable();

            $table->dateTime('enviado_at')->nullable();
            $table->dateTime('validado_at')->nullable();

            $table->timestamps();

            $table->index(['store_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siat_paquetes');
    }
};
