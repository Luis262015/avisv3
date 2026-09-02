<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SiatSetting;
use App\Models\Store;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatSincronizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `siat:parametricas`, el volcado de catálogos del SIN por consola.
 *
 * Es la herramienta con la que se resuelve el alcance de la homologación y se
 * cubre la etapa II, así que importa que recorra los 17 catálogos, que un fallo
 * suelto no interrumpa a los demás y que la salida distinga lo que respondió de
 * lo que no. El servicio va doblado: la consola no habla con el SIN aquí.
 */
class SiatParametricasCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        SiatSetting::create([
            'store_id'            => $store->id,
            'nit'                 => '6923448010',
            'razon_social'        => 'EMPRESA DE PRUEBA SRL',
            'municipio'           => 'LA PAZ',
            'actividad_economica' => '4741100',
            'ambiente'            => 'piloto',
            'cuis'                => 'CUIS-DE-PRUEBA',
            'is_active'           => true,
        ]);
    }

    public function test_el_resumen_recorre_los_diecisiete_catalogos(): void
    {
        $llamados = $this->fakeSincronizacion();

        $this->artisan('siat:parametricas')
            ->expectsOutputToContain('documentos_sector')
            ->assertExitCode(0);

        $this->assertSame(
            array_values(SiatSincronizacionService::CATALOGOS),
            $llamados->metodos,
            'El resumen tiene que consumir un catálogo por cada operación del WSDL.',
        );
    }

    /**
     * Un catálogo caído no puede tumbar el recorrido: lo que se quiere saber es
     * cuáles responden y cuáles no.
     */
    public function test_un_catalogo_que_falla_no_interrumpe_a_los_demas(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            foreach (SiatSincronizacionService::CATALOGOS as $metodo) {
                if ($metodo === 'tiposMoneda') {
                    continue;
                }

                $mock->shouldReceive($metodo)->andReturn([1 => 'ALGO']);
            }

            $mock->shouldReceive('tiposMoneda')->andThrow(new SiatException('El SIN no responde.'));
            $mock->shouldReceive('fechaHora')->andReturn('2026-09-02T13:00:00.000');
            $mock->shouldReceive('olvidarCache')->andReturnNull();
        });

        $this->artisan('siat:parametricas')
            ->expectsOutputToContain('El SIN no responde.')
            ->assertExitCode(1);
    }

    public function test_vuelca_un_catalogo_concreto(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('documentosSector')->once()->andReturn([
                '4741100' => [1 => 'FCV', 24 => 'NCD', 47 => 'NCDDE'],
            ]);
        });

        $this->artisan('siat:parametricas documentos_sector')
            ->expectsOutputToContain('4741100 · 24')
            ->expectsOutputToContain('NCDDE')
            ->assertExitCode(0);
    }

    public function test_rechaza_un_catalogo_que_no_existe(): void
    {
        $this->artisan('siat:parametricas inventado')
            ->expectsOutputToContain('Catálogo desconocido: inventado')
            ->assertExitCode(1);
    }

    public function test_sin_configuracion_activa_no_intenta_hablar_con_el_sin(): void
    {
        SiatSetting::query()->update(['is_active' => false]);

        $this->artisan('siat:parametricas')
            ->expectsOutputToContain('No hay ninguna configuración SIAT que usar.')
            ->assertExitCode(1);
    }

    /**
     * Dobla el servicio y anota en qué orden se le pidieron los catálogos.
     */
    private function fakeSincronizacion(): object
    {
        $registro = new class {
            /** @var list<string> */
            public array $metodos = [];
        };

        $this->mock(SiatSincronizacionService::class, function ($mock) use ($registro): void {
            foreach (SiatSincronizacionService::CATALOGOS as $metodo) {
                $mock->shouldReceive($metodo)
                    ->andReturnUsing(function () use ($registro, $metodo) {
                        $registro->metodos[] = $metodo;

                        return [1 => 'ALGO'];
                    });
            }

            $mock->shouldReceive('fechaHora')->andReturn('2026-09-02T13:00:00.000');
            $mock->shouldReceive('olvidarCache')->andReturnNull();
        });

        return $registro;
    }
}
