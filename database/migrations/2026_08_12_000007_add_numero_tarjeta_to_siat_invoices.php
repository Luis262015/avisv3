<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número de tarjeta de la factura.
 *
 * El SIN lo exige cuando el método de pago es tarjeta y rechaza la factura con
 * `1012 EL NUMERO DE TARJETA SOLO PUEDE SER ENVIADO CUANDO EL METODO DE PAGO SEA
 * CON TARJETA ... enviado null para metodo pago 2` si va vacío. Estaba fijo a
 * nulo en el XML, así que ninguna venta con tarjeta se podía facturar.
 *
 * Se guarda cifrado: es un dato de tarjeta y el reenvío necesita reconstruir el
 * mismo XML más tarde, así que no basta con usarlo y descartarlo. La columna es
 * `text` porque el cifrado de Laravel expande bastante el valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siat_invoices', function (Blueprint $table): void {
            $table->text('numero_tarjeta')->nullable()->after('metodo_pago')
                ->comment('Cifrado. Obligatorio cuando metodo_pago = 2 (tarjeta)');
        });
    }

    public function down(): void
    {
        Schema::table('siat_invoices', function (Blueprint $table): void {
            $table->dropColumn('numero_tarjeta');
        });
    }
};
