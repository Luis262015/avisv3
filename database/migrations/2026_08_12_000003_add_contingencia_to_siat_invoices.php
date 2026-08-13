<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaza cada factura con el corte en que se emitió y con el lote en que viajó.
 *
 * El CAFC (Código de Autorización de Factura Computarizada) se saca del Portal
 * SIAT por rangos y es obligatorio en la cabecera de toda factura computarizada
 * emitida fuera de línea; sin él el XML no pasa la validación del SIN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siat_invoices', function (Blueprint $table): void {
            $table->string('cafc', 50)->nullable()->after('cufd')
                ->comment('Obligatorio en emisión fuera de línea (computarizada)');
            $table->foreignId('evento_id')->nullable()->after('cufd_code_id')
                ->constrained('siat_eventos')->nullOnDelete();
            $table->foreignId('paquete_id')->nullable()->after('evento_id')
                ->constrained('siat_paquetes')->nullOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('siat_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('evento_id');
            $table->dropConstrainedForeignId('paquete_id');
            $table->dropColumn('cafc');
        });
    }
};
