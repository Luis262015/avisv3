<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha de emisión exacta de la factura, con milisegundos.
 *
 * El CUF codifica la fecha como `yyyyMMddHHmmssSSS`, y el SIN comprueba que
 * coincida con el `fechaEmision` del XML. `created_at` solo guarda segundos, así
 * que reconstruir el XML desde ahí (al reenviar una factura pendiente) producía
 * una fecha distinta de la que va dentro del CUF y el envío se rechazaba.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siat_invoices', function (Blueprint $table): void {
            $table->dateTime('fecha_emision', precision: 3)->nullable()->after('numero_factura');
        });

        // Las facturas ya emitidas no tienen milisegundos que recuperar; se usa
        // created_at para que al menos tengan la fecha correcta al segundo.
        DB::table('siat_invoices')->whereNull('fecha_emision')->update([
            'fecha_emision' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('siat_invoices', function (Blueprint $table): void {
            $table->dropColumn('fecha_emision');
        });
    }
};
