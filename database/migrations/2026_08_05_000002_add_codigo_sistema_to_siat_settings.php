<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El SIN exige `codigoSistema` en solicitudCuis, solicitudCufd, recepcionFactura
 * y el resto de operaciones. Es el código que asigna al autorizar el Sistema
 * Informático de Facturación, y faltaba en la configuración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siat_settings', function (Blueprint $table) {
            $table->string('codigo_sistema', 100)->nullable()->after('nit')
                ->comment('Código de Sistema asignado por el SIN al autorizar');

            // Vigencia del CUIS, para avisar antes de que caduque.
            $table->timestamp('cuis_fecha_vigencia')->nullable()->after('cuis_fecha_solicitud');
        });

        // El SIN devuelve la dirección de la sucursal junto con el CUFD; se guarda
        // porque debe imprimirse en la representación gráfica de la factura.
        Schema::table('siat_cufd_codes', function (Blueprint $table) {
            $table->string('direccion', 250)->nullable()->after('codigo_control');
        });
    }

    public function down(): void
    {
        Schema::table('siat_settings', function (Blueprint $table) {
            $table->dropColumn(['codigo_sistema', 'cuis_fecha_vigencia']);
        });

        Schema::table('siat_cufd_codes', function (Blueprint $table) {
            $table->dropColumn('direccion');
        });
    }
};
