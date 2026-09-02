<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas de Crédito-Débito: los documentos sector 24 y 47.
 *
 * Van en tabla propia y no en `siat_invoices` porque son otro documento fiscal:
 * otro servicio del SIN (`ServicioFacturacionDocumentoAjuste`), otro XSD, otro
 * correlativo y campos que la factura no tiene —el importe original, el devuelto
 * y el crédito fiscal que se revierte—. Lo que sí comparten es el ciclo: CUF,
 * envío, anulación y reversión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siat_notas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete()
                ->comment('Devolución que origina la nota');
            $table->foreignId('siat_invoice_id')->constrained('siat_invoices')->cascadeOnDelete()
                ->comment('Factura original que se ajusta');
            $table->foreignId('cufd_code_id')->constrained('siat_cufd_codes');

            $table->unsignedSmallInteger('documento_sector')
                ->comment('24 nota crédito-débito · 47 con descuento');
            $table->unsignedInteger('numero_nota')->comment('Correlativo propio, independiente del de facturas');

            // Con milisegundos: la fecha entra en el CUF y el SIN contrasta ambas.
            // Igual que en siat_invoices, el cast `datetime` los perdería al
            // escribir, así que la columna es DATETIME(3) y hay accessor propio.
            $table->dateTime('fecha_emision', 3);
            $table->string('cuf', 100);
            $table->string('cufd', 512);

            // Datos del comprador: se copian de la factura original porque el SIN
            // exige que coincidan y la factura podría editarse después.
            $table->string('nit_ci', 20);
            $table->unsignedTinyInteger('tipo_doc_identidad')->default(1);
            $table->unsignedTinyInteger('codigo_excepcion')->nullable();
            $table->string('nombre_razon_social', 500);
            $table->string('complemento', 5)->nullable();

            $table->decimal('monto_total_original', 14, 2)->comment('Total sujeto a crédito fiscal de la factura');
            $table->decimal('monto_total_devuelto', 14, 2);
            $table->decimal('monto_descuento', 14, 2)->default(0)
                ->comment('Descuento prorrateado de la factura original');
            $table->decimal('monto_efectivo', 14, 2)->comment('13 % del monto devuelto: el crédito fiscal revertido');
            $table->decimal('descuento_adicional', 14, 2)->nullable()->comment('Solo en el sector 47');

            $table->string('estado', 20)->default('pendiente')
                ->comment('pendiente|enviada|validada|rechazada|anulada');
            $table->string('codigo_recepcion', 100)->nullable();
            $table->text('codigo_qr')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->unsignedTinyInteger('motivo_anulacion')->nullable();

            $table->timestamps();

            $table->unique('cuf');
            // El correlativo es por punto de venta, y hoy hay uno por tienda.
            $table->unique(['store_id', 'documento_sector', 'numero_nota'], 'siat_notas_correlativo_unique');
            $table->index(['store_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siat_notas');
    }
};
