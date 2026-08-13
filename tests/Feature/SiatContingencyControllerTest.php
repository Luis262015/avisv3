<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SiatCufdCode;
use App\Models\SiatEvento;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatSincronizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La pantalla de contingencia. El ciclo contra el SIN se comprueba en
 * {@see SiatContingenciaTest}; aquí interesan el enrutado, la validación y que la
 * pantalla siga siendo utilizable justo cuando el SIN no responde, que es cuando
 * se abre un corte.
 */
class SiatContingencyControllerTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private SiatSetting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $this->setting = SiatSetting::create([
            'store_id'            => $this->store->id,
            'nit'                 => '1234567890',
            'razon_social'        => 'EMPRESA DE PRUEBA SRL',
            'municipio'           => 'LA PAZ',
            'actividad_economica' => '4741100',
            'ambiente'            => 'piloto',
            'cuis'                => 'CUIS-DE-PRUEBA',
            'cafc'                => 'CAFC12345',
            'is_active'           => true,
        ]);
    }

    private function visitar(): \Illuminate\Testing\TestResponse
    {
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return $this->get('/admin/siat/contingency', [
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => (string) $version,
        ]);
    }

    private function cufdVigente(): SiatCufdCode
    {
        return SiatCufdCode::create([
            'store_id'       => $this->store->id,
            'codigo'         => 'CUFD-DEL-CORTE',
            'codigo_control' => '23E26C80881BF74',
            'fecha_vigencia' => now()->addDay(),
            'consecutivo'    => 0,
            'estado'         => 'activo',
        ]);
    }

    public function test_it_offers_the_significant_event_reasons_from_the_parametric(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('eventosSignificativos')->andReturn([
                1 => 'CORTE DEL SERVICIO DE INTERNET',
                2 => 'INACCESIBILIDAD AL SERVICIO WEB',
            ]);
        });

        $this->visitar()
            ->assertOk()
            ->assertJsonPath('component', 'admin/siat/contingency/index')
            ->assertJsonCount(2, 'props.motivos.opciones')
            ->assertJsonPath('props.motivos.opciones.0.codigo', 1);
    }

    /**
     * Un corte se abre precisamente cuando el SIN no contesta: si la pantalla
     * dependiera de la paramétrica, sería inservible en el único momento que importa.
     */
    public function test_the_screen_still_works_when_the_parametric_is_unreachable(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('eventosSignificativos')
                ->andThrow(new SiatException('No hay conexión con el servidor del SIN.'));
        });

        $this->visitar()
            ->assertOk()
            ->assertJsonCount(0, 'props.motivos.opciones')
            ->assertJsonPath('props.motivos.error', 'No hay conexión con el servidor del SIN.');
    }

    public function test_it_opens_and_closes_a_blackout(): void
    {
        $this->cufdVigente();

        $this->post('/admin/siat/contingency', [
            'setting_id'           => $this->setting->id,
            'codigo_motivo_evento' => 2,
            'descripcion'          => 'Caída del servicio del SIN',
        ])->assertSessionHasNoErrors();

        $evento = SiatEvento::firstOrFail();
        $this->assertSame('abierto', $evento->estado);

        $this->post("/admin/siat/contingency/{$evento->id}/close")
            ->assertSessionHasNoErrors();

        $this->assertSame('cerrado', $evento->fresh()->estado);
        $this->assertNotNull($evento->fresh()->fecha_fin);
    }

    public function test_it_requires_a_reason_and_a_description(): void
    {
        $this->post('/admin/siat/contingency', ['setting_id' => $this->setting->id])
            ->assertSessionHasErrors(['codigo_motivo_evento', 'descripcion']);

        $this->assertSame(0, SiatEvento::count());
    }

    /** El motivo del rechazo del servicio tiene que llegar al formulario. */
    public function test_it_reports_why_a_blackout_cannot_be_opened(): void
    {
        // Sin CUFD vigente: no hay con qué firmar el CUF de las facturas del corte.
        $this->post('/admin/siat/contingency', [
            'setting_id'           => $this->setting->id,
            'codigo_motivo_evento' => 2,
            'descripcion'          => 'Sin internet',
        ])->assertSessionHasErrors('siat');

        $this->assertSame(0, SiatEvento::count());
    }

    public function test_it_refuses_to_send_a_package_for_an_undeclared_blackout(): void
    {
        $this->cufdVigente();

        $evento = SiatEvento::create([
            'store_id'             => $this->store->id,
            'codigo_motivo_evento' => 2,
            'descripcion'          => 'Corte',
            'fecha_inicio'         => now()->subHour(),
            'fecha_fin'            => now(),
            'estado'               => 'cerrado',
        ]);

        // `declarar()` es lo primero que corre y no hay nada que empaquetar, así
        // que el error debe llegar a la pantalla y no como un 500.
        $this->mock(\App\Services\Siat\SiatOperacionesService::class, function ($mock): void {
            $mock->shouldReceive('registrarEvento')->andReturn('EVT-1');
        });

        $this->post("/admin/siat/contingency/{$evento->id}/send")
            ->assertSessionHasErrors('siat');
    }
}
