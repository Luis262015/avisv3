<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos que el Registro de Compras del SIN exige de cada compra.
 *
 * El módulo de Compras se hizo para controlar inventario y pagos, no para
 * declarar ante Impuestos, así que le faltaba justo lo que identifica la factura
 * del proveedor: su código de autorización, su número y el desglose de importes
 * que pide `registroCompra.xsd`.
 *
 * El NIT y la razón social se toman del proveedor, pero se pueden sobrescribir
 * por compra: el registro del proveedor puede estar incompleto y eso no debería
 * impedir declarar el periodo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->string('codigo_autorizacion', 100)->nullable()->after('folio')
                ->comment('CUF/CAF de la factura del proveedor');
            // El número de la factura del proveedor ya vive en `invoice_number`;
            // aquí solo se añade lo que no existía.
            $table->string('numero_dui_dim', 15)->nullable()->after('codigo_autorizacion')
                ->comment('Solo importaciones; 0 en compras internas');
            $table->string('nit_proveedor', 15)->nullable()->after('numero_dui_dim');
            $table->string('razon_social_proveedor', 240)->nullable()->after('nit_proveedor');
            $table->unsignedTinyInteger('tipo_compra')->nullable()->after('razon_social_proveedor')
                ->comment('Normativa RCV; no hay paramétrica en los servicios');
            $table->string('codigo_control', 20)->nullable()->after('tipo_compra')
                ->comment('Solo facturas antiguas con código de control');

            // Desglose que exige el XSD. Todo a 0 salvo que la factura lo traiga.
            foreach ([
                'importe_ice', 'importe_iehd', 'importe_ipj', 'tasas',
                'otro_no_sujeto_credito', 'importes_exentos', 'importe_tasa_cero',
                'monto_gift_card', 'descuento_siat', 'credito_fiscal',
            ] as $columna) {
                $table->decimal($columna, 14, 2)->default(0)->after('codigo_control');
            }

            $table->foreignId('paquete_id')->nullable()->after('credito_fiscal')
                ->constrained('siat_paquetes')->nullOnDelete();
        });

        Schema::table('siat_paquetes', function (Blueprint $table): void {
            // El Registro de Compras se declara por periodo, no por evento.
            $table->unsignedSmallInteger('gestion')->nullable()->after('tipo');
            $table->unsignedTinyInteger('periodo')->nullable()->after('gestion');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('paquete_id');
            $table->dropColumn([
                'codigo_autorizacion', 'numero_dui_dim',
                'nit_proveedor', 'razon_social_proveedor', 'tipo_compra', 'codigo_control',
                'importe_ice', 'importe_iehd', 'importe_ipj', 'tasas',
                'otro_no_sujeto_credito', 'importes_exentos', 'importe_tasa_cero',
                'monto_gift_card', 'descuento_siat', 'credito_fiscal',
            ]);
        });

        Schema::table('siat_paquetes', function (Blueprint $table): void {
            $table->dropColumn(['gestion', 'periodo']);
        });
    }
};
