<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SiatCufdCode;
use App\Models\SiatPuntoVenta;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Services\Siat\CufdProvider;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatOperacionesService;
use App\Services\SiatPuntoVentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Puntos de venta del SIN.
 *
 * La homologación repite cada caso con punto de venta 0 y 1, así que hace falta
 * dar de alta un segundo punto y emitir por él. Lo que se comprueba aquí es lo que
 * de verdad puede morder: que cada punto conserve su propia cadena de CUFD —y con
 * ella su correlativo—, que el CUIS sea por punto y no por sistema, y que no se
 * pueda emitir por un punto sin CUIS.
 */
class SiatPuntoVentaTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private SiatSetting $setting;
    private SiatPuntoVenta $principal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $this->setting = SiatSetting::create([
            'store_id' => $this->store->id, 'nit' => '1234567890',
            'codigo_sistema' => 'SISTEMA-DE-PRUEBA', 'razon_social' => 'EMPRESA DE PRUEBA SRL',
            'municipio' => 'LA PAZ', 'direccion' => 'AV. SIEMPRE VIVA 123',
            'actividad_economica' => '4741100', 'ambiente' => 'piloto', 'modalidad' => 2,
            'codigo_sucursal' => 0, 'codigo_punto_venta' => 0, 'nombre_punto_venta' => 'Casa matriz',
            'cuis' => 'CUIS-PV0', 'is_active' => true,
        ]);

        $this->principal = SiatPuntoVenta::create([
            'store_id' => $this->store->id, 'codigo' => 0, 'codigo_sucursal' => 0,
            'nombre' => 'Casa matriz', 'cuis' => 'CUIS-PV0',
            'es_principal' => true, 'estado' => 'activo',
        ]);
    }

    /** El SIN asigna el número: no se puede pedir uno concreto. */
    public function test_registra_el_punto_de_venta_con_el_codigo_que_asigna_el_sin(): void
    {
        $this->mock(SiatOperacionesService::class, function ($mock): void {
            $mock->shouldReceive('registrarPuntoVenta')
                ->once()
                ->withArgs(fn ($setting, $nombre, $desc, $tipo) => $nombre === 'Caja 2' && $tipo === 2)
                ->andReturn(1);
        });

        $punto = app(SiatPuntoVentaService::class)
            ->registrar($this->setting, 'Caja 2', 'Segunda caja', 2);

        $this->assertSame(1, $punto->codigo);
        $this->assertSame('activo', $punto->estado);
        $this->assertFalse($punto->es_principal);
        $this->assertNull($punto->cuis, 'El CUIS se pide aparte, no llega con el registro.');
    }

    public function test_no_se_activa_un_punto_sin_cuis(): void
    {
        $punto = $this->segundoPunto(conCuis: false);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/no tiene CUIS/');

        app(SiatPuntoVentaService::class)->activar($this->setting, $punto);
    }

    public function test_activar_refleja_el_punto_en_la_configuracion(): void
    {
        $punto = $this->segundoPunto();

        app(SiatPuntoVentaService::class)->activar($this->setting, $punto);

        $this->setting->refresh();

        $this->assertSame(1, (int) $this->setting->codigo_punto_venta);
        $this->assertSame('CUIS-PV1', $this->setting->cuis);
        $this->assertSame('Caja 2', $this->setting->nombre_punto_venta);
    }

    /**
     * Lo importante del cambio: cada punto lleva su cadena de CUFD. Si se
     * compartiera, las dos series usarían el mismo correlativo y el SIN
     * rechazaría las facturas por CUF incoherente.
     */
    public function test_cada_punto_de_venta_tiene_su_propia_cadena_de_cufd(): void
    {
        $punto = $this->segundoPunto();

        $cufd0 = $this->cufd($this->principal, 'CUFD-PV0', consecutivo: 7);
        $cufd1 = $this->cufd($punto, 'CUFD-PV1', consecutivo: 0);

        $proveedor = app(CufdProvider::class);

        $this->assertTrue($proveedor->activo($this->setting)->is($cufd0));

        app(SiatPuntoVentaService::class)->activar($this->setting, $punto);

        $this->assertTrue($proveedor->activo($this->setting->refresh())->is($cufd1));
    }

    public function test_pedir_un_cufd_nuevo_no_vence_el_del_otro_punto(): void
    {
        $punto = $this->segundoPunto();

        $cufd0 = $this->cufd($this->principal, 'CUFD-PV0');
        $cufd1 = $this->cufd($punto, 'CUFD-PV1');

        // Lo que hace el proveedor al renovar: vencer los del punto activo.
        SiatCufdCode::where('store_id', $this->store->id)
            ->where('punto_venta_id', $this->principal->id)
            ->where('estado', 'activo')
            ->update(['estado' => 'vencido']);

        $this->assertSame('vencido', $cufd0->fresh()->estado);
        $this->assertSame('activo', $cufd1->fresh()->estado, 'El CUFD del otro punto no se toca.');
    }

    public function test_no_se_pide_un_cuis_que_ya_esta_vigente(): void
    {
        $punto = $this->segundoPunto();
        $punto->update(['cuis_fecha_vigencia' => now()->addYear()]);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/ya tiene un CUIS vigente/');

        app(SiatPuntoVentaService::class)->solicitarCuis($this->setting, $punto);
    }

    public function test_no_se_cierra_la_casa_matriz(): void
    {
        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/casa matriz/');

        app(SiatPuntoVentaService::class)->cerrar($this->setting, $this->principal);
    }

    public function test_no_se_cierra_el_punto_que_esta_emitiendo(): void
    {
        $punto = $this->segundoPunto();
        app(SiatPuntoVentaService::class)->activar($this->setting, $punto);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/está emitiendo/');

        app(SiatPuntoVentaService::class)->cerrar($this->setting->refresh(), $punto);
    }

    public function test_cierra_un_punto_inactivo_y_lo_marca(): void
    {
        $punto = $this->segundoPunto();

        $this->mock(SiatOperacionesService::class, function ($mock) use ($punto): void {
            $mock->shouldReceive('cerrarPuntoVenta')
                ->once()
                ->withArgs(fn ($s, $codigo, $cuis) => $codigo === 1 && $cuis === 'CUIS-PV1')
                ->andReturn(1);
        });

        app(SiatPuntoVentaService::class)->cerrar($this->setting, $punto);

        $this->assertSame('cerrado', $punto->fresh()->estado);
        $this->assertNotNull($punto->fresh()->cerrado_at);
    }

    /** Reconciliar con el SIN es de solo lectura: no registra nada. */
    public function test_sincroniza_los_puntos_que_el_sin_ya_conoce(): void
    {
        $this->mock(SiatOperacionesService::class, function ($mock): void {
            $mock->shouldReceive('consultarPuntosVenta')->once()->andReturn([
                ['codigoPuntoVenta' => 0, 'nombrePuntoVenta' => 'Casa matriz', 'tipoPuntoVenta' => 'COMISIONISTA'],
                ['codigoPuntoVenta' => 1, 'nombrePuntoVenta' => 'Caja 2', 'tipoPuntoVenta' => 'VENTANILLA'],
            ]);
            $mock->shouldNotReceive('registrarPuntoVenta');
        });

        $resultado = app(SiatPuntoVentaService::class)->sincronizar($this->setting);

        $this->assertSame(['sincronizados' => 2, 'nuevos' => 1], $resultado);
        $this->assertSame(2, SiatPuntoVenta::count());
        $this->assertSame('Caja 2', SiatPuntoVenta::where('codigo', 1)->firstOrFail()->nombre);
    }

    public function test_no_se_toca_un_punto_de_otra_tienda(): void
    {
        $otra  = Store::create(['name' => 'Otra tienda', 'is_active' => true]);
        $ajeno = SiatPuntoVenta::create([
            'store_id' => $otra->id, 'codigo' => 1, 'codigo_sucursal' => 0,
            'nombre' => 'Ajeno', 'cuis' => 'X', 'estado' => 'activo',
        ]);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/otra tienda/');

        app(SiatPuntoVentaService::class)->activar($this->setting, $ajeno);
    }

    /** El CUIS no tiene por qué llegar al navegador. */
    public function test_el_cuis_no_se_serializa(): void
    {
        $this->assertArrayNotHasKey('cuis', $this->principal->toArray());
        $this->assertTrue($this->principal->toArray()['tiene_cuis']);
    }

    private function segundoPunto(bool $conCuis = true): SiatPuntoVenta
    {
        return SiatPuntoVenta::create([
            'store_id' => $this->store->id, 'codigo' => 1, 'codigo_sucursal' => 0,
            'nombre' => 'Caja 2', 'tipo' => 2,
            'cuis' => $conCuis ? 'CUIS-PV1' : null,
            'es_principal' => false, 'estado' => 'activo',
        ]);
    }

    private function cufd(SiatPuntoVenta $punto, string $codigo, int $consecutivo = 0): SiatCufdCode
    {
        return SiatCufdCode::create([
            'store_id' => $this->store->id, 'punto_venta_id' => $punto->id,
            'codigo' => $codigo, 'codigo_control' => 'CTRL-' . $punto->codigo,
            'fecha_vigencia' => now()->addDay(), 'consecutivo' => $consecutivo, 'estado' => 'activo',
        ]);
    }
}
