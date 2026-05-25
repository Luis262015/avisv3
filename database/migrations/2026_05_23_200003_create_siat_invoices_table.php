<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siat_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cufd_code_id')->nullable()->constrained('siat_cufd_codes')->nullOnDelete();
            $table->unsignedInteger('numero_factura')->comment('Número correlativo');
            $table->string('cuf', 128)->unique()->comment('Código Único de Factura');
            $table->string('cufd', 512)->comment('Código CUFD usado');

            // Datos del comprador
            $table->string('nit_ci', 20)->default('0')->comment('NIT o CI del comprador; 0=sin nombre');
            $table->tinyInteger('tipo_doc_identidad')->default(5)->comment('1=CI, 2=Pasaporte, 3=Carnet Ext., 4=Otro, 5=NIT');
            $table->string('nombre_razon_social', 200)->default('Sin Nombre');
            $table->string('complemento', 10)->nullable();

            // Montos en Bolivianos
            $table->decimal('importe_total', 12, 2);
            $table->decimal('importe_base_cf', 12, 2)->comment('Base imponible crédito fiscal (total - exentos)');
            $table->decimal('descuento', 12, 2)->default(0);

            // Configuración de factura
            $table->tinyInteger('tipo_factura')->default(2)->comment('1=con CF, 2=sin CF');
            $table->tinyInteger('tipo_emision')->default(2)->comment('1=Online, 2=Offline');
            $table->tinyInteger('metodo_pago')->default(1)->comment('1=Efectivo, 2=Tarjeta, 3=Transferencia, 7=QR');

            // Estado y control SIN
            $table->string('estado', 30)->default('pendiente')->comment('pendiente|enviada|anulada|contingencia');
            $table->string('codigo_recepcion', 100)->nullable()->comment('Código de recepción SIN');
            $table->text('codigo_qr')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->string('motivo_anulacion', 200)->nullable();

            $table->timestamps();

            $table->index(['store_id', 'estado']);
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siat_invoices');
    }
};
