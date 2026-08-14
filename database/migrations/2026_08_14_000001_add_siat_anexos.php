<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexos de factura: números de serie e IMEI de lo vendido.
 *
 * El SIN los pide aparte de la factura, por `recepcionAnexos`, citando el CUF de
 * una factura ya emitida. No llevan XML ni XSD: viajan como una lista plana de
 * {codigo, codigoProducto, codigoProductoSin, tipoCodigo}.
 *
 * Qué productos los exigen es información del catálogo, igual que el código
 * homologado, así que `tipo_codigo_anexo` va en `products`: null significa que ese
 * producto no lleva anexo. El envío se registra en la factura porque es una sola
 * llamada con la lista entera, no una por código.
 *
 * @see https://siatinfo.impuestos.gob.bo/index.php/facturacion-en-linea/implementacion-servicios-facturacion/servicio-factura-compra-venta/recepcion-archivos-anexos
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Paramétrica del SIN: 1 = Nro. de Serie, 2 = IMEI.
            $table->unsignedTinyInteger('tipo_codigo_anexo')->nullable()->after('unidad_medida_sin');
        });

        Schema::table('siat_invoices', function (Blueprint $table): void {
            // null = la factura no lleva anexos. El resto son los mismos estados
            // que ya usa `estado` para la factura: pendiente / enviado / error.
            $table->string('anexos_estado', 20)->nullable()->after('codigo_recepcion');
            $table->string('anexos_codigo_recepcion')->nullable()->after('anexos_estado');
            $table->text('anexos_mensaje_error')->nullable()->after('anexos_codigo_recepcion');
            $table->timestamp('anexos_enviado_at')->nullable()->after('anexos_mensaje_error');
        });

        Schema::create('siat_anexos', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('siat_invoice_id')->constrained('siat_invoices')->cascadeOnDelete();
            // La línea dice qué producto y cuántas unidades; cada unidad con serie
            // aporta una fila aquí.
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();

            $table->string('codigo', 100);
            $table->unsignedTinyInteger('tipo_codigo');

            $table->timestamps();

            // Un mismo número de serie no puede aparecer dos veces en una factura:
            // sería la misma unidad física declarada dos veces.
            $table->unique(['siat_invoice_id', 'codigo']);
            $table->index('sale_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siat_anexos');

        Schema::table('siat_invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'anexos_estado',
                'anexos_codigo_recepcion',
                'anexos_mensaje_error',
                'anexos_enviado_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('tipo_codigo_anexo');
        });
    }
};
