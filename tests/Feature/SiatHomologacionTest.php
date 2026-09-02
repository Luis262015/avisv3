<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\SiatCufdCode;
use App\Models\SiatEvento;
use App\Models\SiatHomologacionCaso;
use App\Models\SiatInvoice;
use App\Models\SiatPuntoVenta;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Siat\HomologacionMatriz;
use App\Services\Siat\HomologacionRunner;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatOperacionesService;
use App\Services\Siat\SiatSincronizacionService;
use App\Services\SiatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El generador de volumen de la homologación.
 *
 * La matriz no es una lista fija: sale de cruzar los documentos sector de la
 * actividad, los puntos de venta con CUIS y los motivos de evento. Lo que se
 * comprueba aquí es que ese cruce dé los casos correctos, que el volumen se
 * reparta salvo donde el Excel fija el tamaño del lote, y que la ejecución sea
 * reanudable — que es lo que permite parar a mitad de 500 emisiones.
 */
class SiatHomologacionTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private SiatSetting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        $user        = User::factory()->create();
        $this->store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $this->setting = SiatSetting::create([
            'store_id' => $this->store->id, 'nit' => '1234567890',
            'codigo_sistema' => 'SISTEMA-DE-PRUEBA', 'razon_social' => 'EMPRESA DE PRUEBA SRL',
            'municipio' => 'LA PAZ', 'direccion' => 'AV. SIEMPRE VIVA 123',
            'actividad_economica' => '4741100', 'ambiente' => 'piloto', 'modalidad' => 2,
            'codigo_sucursal' => 0, 'codigo_punto_venta' => 0, 'cuis' => 'CUIS-PV0',
            'tipo_factura_default' => 1, 'is_active' => true,
        ]);

        foreach ([0, 1] as $codigo) {
            SiatPuntoVenta::create([
                'store_id' => $this->store->id, 'codigo' => $codigo, 'codigo_sucursal' => 0,
                'nombre' => "Punto {$codigo}", 'cuis' => "CUIS-PV{$codigo}",
                'es_principal' => $codigo === 0, 'estado' => 'activo',
            ]);
        }

        $this->fakeCatalogos();
    }

    // ─── Matriz ─────────────────────────────────────────────────────────────

    /** Tres sectores por dos puntos de venta: seis casos, y el volumen repartido. */
    public function test_la_emision_individual_cruza_sectores_y_puntos_de_venta(): void
    {
        $casos = app(HomologacionMatriz::class)->generar($this->setting, 4);

        $this->assertCount(6, $casos);
        $this->assertSame(
            ['e4-s1-pv0', 'e4-s1-pv1', 'e4-s24-pv0', 'e4-s24-pv1', 'e4-s47-pv0', 'e4-s47-pv1'],
            array_map(fn ($c) => $c->caso, $casos),
        );
        // 500 repartidos entre 6 casos.
        $this->assertSame(84, $casos[0]->cantidad);
    }

    public function test_las_notas_llevan_tipo_de_factura_3(): void
    {
        $casos = collect(app(HomologacionMatriz::class)->generar($this->setting, 4))
            ->keyBy('caso');

        $this->assertSame(1, $casos['e4-s1-pv0']->tipo_factura);
        $this->assertSame(3, $casos['e4-s24-pv0']->tipo_factura);
        $this->assertSame(3, $casos['e4-s47-pv1']->tipo_factura);
    }

    /** El tamaño del lote lo fija el Excel, no el reparto del total de la etapa. */
    public function test_los_lotes_conservan_su_tamano_exacto(): void
    {
        $paquetes = collect(app(HomologacionMatriz::class)->generar($this->setting, 6));
        $masiva   = collect(app(HomologacionMatriz::class)->generar($this->setting, 9));

        $this->assertSame([500, 250], $paquetes->pluck('cantidad')->unique()->sort()->reverse()->values()->all());
        $this->assertSame([1000, 500], $masiva->pluck('cantidad')->unique()->sort()->reverse()->values()->all());
    }

    public function test_los_eventos_cubren_los_siete_motivos_por_punto_de_venta(): void
    {
        $casos = app(HomologacionMatriz::class)->generar($this->setting, 5);

        $this->assertCount(14, $casos);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], collect($casos)->pluck('motivo_evento')->unique()->sort()->values()->all());
    }

    /** Regenerar la matriz no duplica filas ni pierde lo ya hecho. */
    public function test_regenerar_la_matriz_conserva_el_avance(): void
    {
        $matriz = app(HomologacionMatriz::class);

        $matriz->generar($this->setting, 4);
        SiatHomologacionCaso::where('caso', 'e4-s1-pv0')->update(['completados' => 10]);

        $matriz->generar($this->setting, 4);

        $this->assertSame(6, SiatHomologacionCaso::where('etapa', 4)->count());
        $this->assertSame(10, SiatHomologacionCaso::where('caso', 'e4-s1-pv0')->firstOrFail()->completados);
    }

    public function test_sin_puntos_de_venta_con_cuis_no_hay_matriz(): void
    {
        SiatPuntoVenta::query()->update(['cuis' => null]);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/punto de venta/');

        app(HomologacionMatriz::class)->generar($this->setting, 4);
    }

    public function test_una_actividad_sin_sectores_se_detecta(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('documentosSectorDe')->andReturn([]);
        });

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/no asocia ningún documento sector/');

        app(HomologacionMatriz::class)->generar($this->setting, 4);
    }

    // ─── Ejecución ──────────────────────────────────────────────────────────

    public function test_ejecuta_solo_el_limite_pedido_y_anota_el_avance(): void
    {
        $this->prepararEmision();
        $caso = $this->caso('e4-s1-pv0');

        $hechos = app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 3);

        $this->assertSame(3, $hechos);
        $this->assertSame(3, $caso->fresh()->completados);
        $this->assertSame('en_curso', $caso->fresh()->estado);
    }

    /** Reanudar: la segunda pasada continúa donde se quedó la primera. */
    public function test_la_ejecucion_es_reanudable(): void
    {
        $this->prepararEmision();
        $caso = $this->caso('e4-s1-pv0');
        $caso->update(['cantidad' => 5]);

        $runner = app(HomologacionRunner::class);
        $runner->ejecutar($caso, $this->setting, limite: 2);
        $runner->ejecutar($caso->fresh(), $this->setting, limite: 10);

        $this->assertSame(5, $caso->fresh()->completados);
        $this->assertSame('completado', $caso->fresh()->estado);
    }

    public function test_un_rechazo_del_sin_deja_el_caso_fallido_con_el_motivo(): void
    {
        $this->prepararEmision(rechazada: true);
        $caso = $this->caso('e4-s1-pv0');

        try {
            app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 1);
            $this->fail('Tenía que propagar el rechazo.');
        } catch (SiatException $e) {
            $this->assertStringContainsString('rechazó', $e->getMessage());
        }

        $this->assertSame('fallido', $caso->fresh()->estado);
        $this->assertNotNull($caso->fresh()->mensaje);
    }

    /** Un caso del punto de venta 1 tiene que cambiar el punto activo antes. */
    public function test_activa_el_punto_de_venta_del_caso(): void
    {
        $this->prepararEmision();
        $caso = $this->caso('e4-s1-pv1');

        app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 1);

        $this->assertSame(1, (int) $this->setting->fresh()->codigo_punto_venta);
    }

    /**
     * El SIN rechaza dos cortes con rangos solapados (981) y también uno cuya
     * franja caiga fuera de la vigencia del CUFD (984). Cada corte nuevo se
     * coloca justo antes del más temprano ya declarado.
     */
    public function test_cada_corte_se_declara_antes_del_anterior(): void
    {
        $this->prepararEmision();
        $this->fakeContingencia();

        $runner = app(HomologacionRunner::class);
        $matriz = app(HomologacionMatriz::class);
        $matriz->generar($this->setting, 5);

        $primero = SiatHomologacionCaso::where('caso', 'e5-m1-pv0')->firstOrFail();
        $segundo = SiatHomologacionCaso::where('caso', 'e5-m2-pv0')->firstOrFail();

        $runner->ejecutar($primero, $this->setting);
        $runner->ejecutar($segundo, $this->setting);

        $eventos = SiatEvento::orderBy('fecha_inicio')->get();

        $this->assertCount(2, $eventos);
        $this->assertTrue(
            $eventos[0]->fecha_fin->lessThanOrEqualTo($eventos[1]->fecha_inicio),
            'Las franjas de dos cortes no pueden solaparse.',
        );
    }

    /** Un corte que el SIN no acepta no puede dejar rastro: ocuparía un rango. */
    public function test_un_corte_rechazado_no_deja_fila(): void
    {
        $this->prepararEmision();
        $this->fakeContingencia(declararFalla: true);

        $caso = SiatHomologacionCaso::where('caso', 'e5-m1-pv0')->firstOrFail();

        try {
            app(HomologacionRunner::class)->ejecutar($caso, $this->setting);
            $this->fail('Tenía que propagar el rechazo.');
        } catch (SiatException) {
            // esperado
        }

        $this->assertSame(0, SiatEvento::count());
    }

    public function test_la_etapa_de_firma_digital_no_se_ejecuta(): void
    {
        $this->assertNotContains(8, HomologacionMatriz::EJECUTABLES);
        $this->assertSame([], app(HomologacionMatriz::class)->generar($this->setting, 8));
    }

    // ─── Comando ────────────────────────────────────────────────────────────

    public function test_el_ensayo_en_seco_no_toca_el_sin(): void
    {
        $this->mock(SiatService::class, function ($mock): void {
            $mock->shouldNotReceive('createInvoice');
        });

        $this->artisan('siat:homologacion 4 --dry-run')
            ->expectsOutputToContain('e4-s1-pv0')
            ->expectsOutputToContain('No se envió nada')
            ->assertExitCode(0);

        $this->assertSame(6, SiatHomologacionCaso::count());
    }

    public function test_el_comando_se_niega_a_correr_en_produccion(): void
    {
        $this->setting->update(['ambiente' => 'produccion']);

        $this->artisan('siat:homologacion 4 --force')
            ->expectsOutputToContain('nunca contra producción')
            ->assertExitCode(1);

        $this->assertSame(0, SiatHomologacionCaso::count());
    }

    public function test_el_comando_rechaza_una_etapa_que_no_ejecuta(): void
    {
        $this->artisan('siat:homologacion 8')
            ->expectsOutputToContain('no aplica a la modalidad computarizada')
            ->assertExitCode(1);
    }

    public function test_sin_argumento_muestra_el_avance(): void
    {
        app(HomologacionMatriz::class)->generar($this->setting, 4);

        $this->artisan('siat:homologacion')
            ->expectsOutputToContain('Etapa 4')
            ->assertExitCode(0);
    }

    // ─── Andamiaje ──────────────────────────────────────────────────────────

    private function caso(string $nombre): SiatHomologacionCaso
    {
        app(HomologacionMatriz::class)->generar($this->setting, 4);

        return SiatHomologacionCaso::where('caso', $nombre)->firstOrFail();
    }

    /** Dobla la emisión: el runner no habla con el SIN en las pruebas. */
    private function prepararEmision(bool $rechazada = false): void
    {
        $register = CashRegister::create([
            'store_id' => $this->store->id, 'name' => 'Caja 1', 'is_active' => true,
        ]);

        CashShift::create([
            'cash_register_id' => $register->id, 'user_id' => User::query()->value('id'),
            'opening_amount' => 0, 'opened_at' => now(), 'status' => 'open',
        ]);

        Product::create([
            'name' => 'Laptop', 'slug' => 'laptop', 'sku' => 'LAP-1',
            'price' => 100, 'cost' => 70, 'stock' => 999, 'status' => 'active',
            'codigo_producto_sin' => 1001967, 'unidad_medida_sin' => 57,
        ]);

        // Un CUFD dura 24 horas; se fecha unas horas atrás porque los cortes se
        // declaran en pasado y tienen que caber dentro de su vigencia.
        SiatCufdCode::create([
            'store_id' => $this->store->id,
            'punto_venta_id' => SiatPuntoVenta::where('codigo', 0)->value('id'),
            'codigo' => 'CUFD-PV0', 'codigo_control' => 'CTRL0',
            'fecha_vigencia' => now()->addHours(20), 'consecutivo' => 0, 'estado' => 'activo',
        ])->forceFill(['created_at' => now()->subHours(4)])->save();

        $this->mock(SiatService::class, function ($mock) use ($rechazada): void {
            $mock->shouldReceive('createInvoice')->andReturnUsing(
                fn () => new SiatInvoice([
                    'estado'        => $rechazada ? 'rechazada' : 'enviada',
                    'cuf'           => 'CUF-' . uniqid(),
                    'mensaje_error' => $rechazada ? '1000 ALGO' : null,
                ]),
            );
        });
    }

    /**
     * Dobla solo la llamada SOAP del registro del evento: la contingencia real
     * —abrir, cerrar, declarar— se ejecuta de verdad, que es lo que interesa
     * comprobar. `SiatContingenciaService` es final y no se puede doblar.
     */
    private function fakeContingencia(bool $declararFalla = false): void
    {
        app(HomologacionMatriz::class)->generar($this->setting, 5);

        $this->mock(SiatOperacionesService::class, function ($mock) use ($declararFalla): void {
            if ($declararFalla) {
                $mock->shouldReceive('registrarEvento')
                    ->andThrow(new SiatException('981 RANGO DE FECHAS DE EVENTO SIGNIFICATIVO INVALIDO'));
            } else {
                $mock->shouldReceive('registrarEvento')->andReturn('9898021');
            }
        });
    }

    private function fakeCatalogos(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('documentosSectorDe')
                ->andReturn([1 => 'FCV', 24 => 'NCD', 47 => 'NCDDE']);
            $mock->shouldReceive('eventosSignificativos')->andReturn([
                1 => 'CORTE DE INTERNET', 2 => 'INACCESIBILIDAD', 3 => 'ZONAS SIN INTERNET',
                4 => 'VENTA SIN INTERNET', 5 => 'VIRUS', 6 => 'HARDWARE', 7 => 'ENERGIA',
            ]);
            $mock->shouldReceive('olvidarCache')->andReturnNull();
        });
    }
}
