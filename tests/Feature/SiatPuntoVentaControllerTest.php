<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SiatPuntoVenta;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatOperacionesService;
use App\Services\Siat\SiatSincronizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La pantalla de puntos de venta.
 *
 * Registrar uno ante el SIN no se deshace, así que importa que la validación
 * pare antes de llamar, que un fallo del SIN se vea en pantalla y que la página
 * siga en pie cuando la paramétrica de tipos no responde.
 */
class SiatPuntoVentaControllerTest extends TestCase
{
    use RefreshDatabase;

    private SiatSetting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $this->setting = SiatSetting::create([
            'store_id' => $store->id, 'nit' => '1234567890',
            'codigo_sistema' => 'SISTEMA-DE-PRUEBA', 'razon_social' => 'EMPRESA DE PRUEBA SRL',
            'municipio' => 'LA PAZ', 'actividad_economica' => '4741100',
            'ambiente' => 'piloto', 'modalidad' => 2, 'codigo_sucursal' => 0,
            'codigo_punto_venta' => 0, 'cuis' => 'CUIS-PV0', 'is_active' => true,
        ]);

        SiatPuntoVenta::create([
            'store_id' => $store->id, 'codigo' => 0, 'codigo_sucursal' => 0,
            'nombre' => 'Casa matriz', 'cuis' => 'CUIS-PV0',
            'es_principal' => true, 'estado' => 'activo',
        ]);
    }

    public function test_la_pantalla_lista_los_puntos_y_los_tipos(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('tiposPuntoVenta')->andReturn([2 => 'PUNTO VENTA VENTANILLA DE COBRANZA']);
        });

        $this->get('/admin/siat/puntos-venta')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/siat/puntos-venta/index')
                ->where('setting.codigo_punto_venta', 0)
                ->has('puntos', 1)
                ->where('tipos.2', 'PUNTO VENTA VENTANILLA DE COBRANZA'));
    }

    /** Sin la paramétrica no se puede registrar, pero la página no se cae. */
    public function test_la_pantalla_aguanta_que_el_sin_no_responda(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('tiposPuntoVenta')->andThrow(new SiatException('SIN caído.'));
        });

        $this->get('/admin/siat/puntos-venta')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tipos', []));
    }

    public function test_registra_un_punto_con_el_codigo_del_sin(): void
    {
        $this->mock(SiatOperacionesService::class, function ($mock): void {
            $mock->shouldReceive('registrarPuntoVenta')->once()->andReturn(1);
        });

        $this->post("/admin/siat/settings/{$this->setting->id}/puntos-venta", [
            'nombre' => 'Caja 2', 'descripcion' => 'Segundo punto', 'tipo' => 2,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('siat_puntos_venta', ['codigo' => 1, 'nombre' => 'Caja 2']);
    }

    /** El alta no se deshace: la validación tiene que parar antes de llamar. */
    public function test_no_llama_al_sin_con_datos_invalidos(): void
    {
        $this->mock(SiatOperacionesService::class, function ($mock): void {
            $mock->shouldNotReceive('registrarPuntoVenta');
        });

        $this->post("/admin/siat/settings/{$this->setting->id}/puntos-venta", [
            'nombre' => '', 'descripcion' => 'x', 'tipo' => 99,
        ])->assertSessionHasErrors(['nombre', 'tipo']);

        $this->assertSame(1, SiatPuntoVenta::count());
    }

    public function test_un_fallo_del_sin_se_muestra_en_pantalla(): void
    {
        $this->mock(SiatOperacionesService::class, function ($mock): void {
            $mock->shouldReceive('registrarPuntoVenta')
                ->andThrow(new SiatException('El SIN rechazó el registroPuntoVenta.'));
        });

        $this->post("/admin/siat/settings/{$this->setting->id}/puntos-venta", [
            'nombre' => 'Caja 2', 'descripcion' => 'Segundo punto', 'tipo' => 2,
        ])->assertSessionHasErrors('siat');

        $this->assertSame(1, SiatPuntoVenta::count());
    }

    public function test_activar_cambia_el_punto_que_emite(): void
    {
        $punto = SiatPuntoVenta::create([
            'store_id' => $this->setting->store_id, 'codigo' => 1, 'codigo_sucursal' => 0,
            'nombre' => 'Caja 2', 'cuis' => 'CUIS-PV1', 'estado' => 'activo',
        ]);

        $this->post("/admin/siat/settings/{$this->setting->id}/puntos-venta/{$punto->id}/activate")
            ->assertSessionHas('success');

        $this->assertSame(1, (int) $this->setting->fresh()->codigo_punto_venta);
    }

    public function test_activar_sin_cuis_avisa_en_vez_de_romper(): void
    {
        $punto = SiatPuntoVenta::create([
            'store_id' => $this->setting->store_id, 'codigo' => 1, 'codigo_sucursal' => 0,
            'nombre' => 'Caja 2', 'estado' => 'activo',
        ]);

        $this->post("/admin/siat/settings/{$this->setting->id}/puntos-venta/{$punto->id}/activate")
            ->assertSessionHasErrors('siat');

        $this->assertSame(0, (int) $this->setting->fresh()->codigo_punto_venta);
    }

    public function test_sincronizar_no_registra_nada(): void
    {
        $this->mock(SiatOperacionesService::class, function ($mock): void {
            $mock->shouldReceive('consultarPuntosVenta')->once()->andReturn([
                ['codigoPuntoVenta' => 1, 'nombrePuntoVenta' => 'Caja 2', 'tipoPuntoVenta' => 'VENTANILLA'],
            ]);
            $mock->shouldNotReceive('registrarPuntoVenta');
        });

        $this->post("/admin/siat/settings/{$this->setting->id}/puntos-venta/sync")
            ->assertSessionHas('success');

        $this->assertDatabaseHas('siat_puntos_venta', ['codigo' => 1, 'nombre' => 'Caja 2']);
    }
}
