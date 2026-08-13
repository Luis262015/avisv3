<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código de excepción del comprador.
 *
 * El SIN comprueba que un NIT exista en su registro y rechaza la factura con
 * `1037 EL NUMERO DOCUMENTO DE TIPO NIT NO ES VALIDO ... para codigo excepcion 0`
 * cuando no lo encuentra. El campo `codigoExcepcion` del XSD sirve exactamente
 * para eso: con valor 1 el emisor declara que factura a ese NIT pese a que la
 * validación no pasa. Estaba fijo a nulo, así que no había forma de emitir a un
 * comprador cuyo NIT el SIN no reconoce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siat_invoices', function (Blueprint $table): void {
            $table->unsignedTinyInteger('codigo_excepcion')->nullable()->after('tipo_doc_identidad')
                ->comment('1 = emitir pese a que el SIN no valida el NIT del comprador');
        });
    }

    public function down(): void
    {
        Schema::table('siat_invoices', function (Blueprint $table): void {
            $table->dropColumn('codigo_excepcion');
        });
    }
};
