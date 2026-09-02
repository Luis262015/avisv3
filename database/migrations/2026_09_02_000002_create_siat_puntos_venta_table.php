<?php

use App\Models\SiatSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puntos de venta del SIN.
 *
 * Hasta ahora la configuración tenía un único `codigo_punto_venta` por tienda, lo
 * que bastaba para la casa matriz (punto de venta 0). La homologación repite cada
 * caso de las nueve etapas con punto de venta 0 **y** 1, así que el punto de venta
 * pasa a ser una entidad.
 *
 * Dos consecuencias que no son evidentes:
 *
 * - **Cada punto de venta tiene su propio CUIS.** El SIN responde «980 EXISTE UN
 *   CUIS VIGENTE PARA LA SUCURSAL O PUNTO DE VENTA», o sea que lo emite por
 *   pareja sucursal/punto de venta y no por sistema.
 * - **El correlativo de facturas cuelga del CUFD, y el CUFD del punto de venta.**
 *   Sin esta columna las dos series compartirían numeración y el SIN rechazaría
 *   las facturas por CUF incoherente.
 *
 * `siat_settings.codigo_punto_venta` y `siat_settings.cuis` se conservan: pasan a
 * ser el reflejo del punto de venta activo, para que todo lo que ya emite —XML,
 * cabeceras SOAP, CUF— siga leyendo de donde leía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siat_puntos_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Lo asigna el SIN al registrarlo y lo devuelve en la respuesta: no se
            // puede pedir un número concreto. El 0 es la casa matriz, que existe
            // sin registrar.
            $table->unsignedInteger('codigo');
            $table->unsignedInteger('codigo_sucursal')->default(0);
            $table->string('nombre', 100);
            $table->string('descripcion', 500)->nullable();
            $table->unsignedTinyInteger('tipo')->nullable()
                ->comment('Paramétrica sincronizarParametricaTipoPuntoVenta');

            // Propio de cada punto de venta, no del sistema.
            $table->string('cuis', 100)->nullable();
            $table->timestamp('cuis_fecha_solicitud')->nullable();
            $table->timestamp('cuis_fecha_vigencia')->nullable();

            $table->boolean('es_principal')->default(false)
                ->comment('La casa matriz: el punto de venta 0, que no se registra');
            $table->string('estado', 20)->default('activo')->comment('activo|cerrado');
            $table->timestamp('cerrado_at')->nullable();

            $table->timestamps();

            $table->unique(['store_id', 'codigo_sucursal', 'codigo'], 'siat_punto_venta_unico');
            $table->index(['store_id', 'estado']);
        });

        Schema::table('siat_cufd_codes', function (Blueprint $table) {
            $table->foreignId('punto_venta_id')->nullable()->after('store_id')
                ->constrained('siat_puntos_venta')->nullOnDelete();
        });

        $this->sembrarPuntoPrincipal();
    }

    /**
     * Cada configuración existente ya emitía por un punto de venta; se le da
     * entidad y se le atan sus CUFD para no romper la serie en curso.
     */
    private function sembrarPuntoPrincipal(): void
    {
        foreach (SiatSetting::all() as $setting) {
            $id = DB::table('siat_puntos_venta')->insertGetId([
                'store_id'             => $setting->store_id,
                'codigo'               => (int) $setting->codigo_punto_venta,
                'codigo_sucursal'      => (int) $setting->codigo_sucursal,
                'nombre'               => $setting->nombre_punto_venta ?: 'Casa matriz',
                'cuis'                 => $setting->cuis,
                'cuis_fecha_solicitud' => $setting->cuis_fecha_solicitud,
                'cuis_fecha_vigencia'  => $setting->cuis_fecha_vigencia,
                'es_principal'         => true,
                'estado'               => 'activo',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            DB::table('siat_cufd_codes')
                ->where('store_id', $setting->store_id)
                ->update(['punto_venta_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('siat_cufd_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('punto_venta_id');
        });

        Schema::dropIfExists('siat_puntos_venta');
    }
};
