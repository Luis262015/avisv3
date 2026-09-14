<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
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
use App\Services\Siat\SiatCodigosService;
use App\Services\Siat\SiatDocumentoAjusteService;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatFacturacionService;
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

    /**
     * El tamaño del lote lo fija el Excel (500 o 1000 facturas) y no tiene que
     * ver con cuántas pruebas pide el caso, que son 10 en las dos etapas.
     */
    public function test_el_tamano_del_lote_va_aparte_del_numero_de_pruebas(): void
    {
        $paquetes = collect(app(HomologacionMatriz::class)->generar($this->setting, 6));
        $masiva   = collect(app(HomologacionMatriz::class)->generar($this->setting, 9));

        $envios = fn ($casos) => $casos->reject->esValidacion();

        $this->assertSame([500, 250], $envios($paquetes)->pluck('tamano_lote')->unique()->sort()->reverse()->values()->all());
        $this->assertSame([1000, 500], $envios($masiva)->pluck('tamano_lote')->unique()->sort()->reverse()->values()->all());

        $this->assertSame([10], $paquetes->pluck('cantidad')->unique()->values()->all());
        $this->assertSame([10], $masiva->pluck('cantidad')->unique()->values()->all());
    }

    /**
     * El Excel de paquetes son 16 casos por sector: catorce envíos —cada motivo
     * con lote completo y parcial— y dos validaciones, una por punto de venta.
     * El eje es el tamaño del lote, no el punto de venta.
     */
    public function test_los_paquetes_siguen_los_dieciseis_casos_del_excel(): void
    {
        $casos = collect(app(HomologacionMatriz::class)->generar($this->setting, 6));

        $this->assertCount(16, $casos);
        $this->assertCount(14, $casos->reject->esValidacion());
        $this->assertSame(['e6-s1-val-pv0', 'e6-s1-val-pv1'], $casos->filter->esValidacion()->pluck('caso')->sort()->values()->all());
        $this->assertContains('e6-s1-m1-n500', $casos->pluck('caso')->all());
        $this->assertContains('e6-s1-m1-n250', $casos->pluck('caso')->all());
    }

    /**
     * En la etapa VI el tamaño del lote y el punto de venta son **una sola
     * columna**: el Excel manda «igual a 500» por el punto 1 y «menor a 500»
     * por el 0, fila a fila.
     *
     * Estaban los catorce envíos saliendo por el punto 1, que es lo que deja
     * sin puntuar las siete filas del punto 0. No lo cazaba el test de los
     * dieciséis casos porque el número de casos es el mismo: lo que cambia es
     * con qué punto sale cada uno.
     */
    public function test_el_tamano_del_lote_decide_el_punto_de_venta_del_envio(): void
    {
        $envios = collect(app(HomologacionMatriz::class)->generar($this->setting, 6))
            ->reject->esValidacion();

        $this->assertSame(
            [250 => 0, 500 => 1],
            $envios->groupBy('tamano_lote')
                ->map(fn ($grupo) => $grupo->pluck('punto_venta')->unique()->sole())
                ->sortKeys()
                ->all(),
        );

        $this->assertCount(7, $envios->where('punto_venta', 0));
        $this->assertCount(7, $envios->where('punto_venta', 1));
    }

    /**
     * El alcance sale de **todas** las actividades del NIT, no de la de la
     * tienda. Fijarlo en `siat_settings.actividad_economica` dejaba la etapa IX
     * en 80 pruebas cuando el panel pide 320: ese NIT tiene 21 actividades y
     * entre todas suman seis sectores, cuatro de ellos facturables.
     */
    /**
     * La etapa VII **no** reparte un total entre sus casos: el panel de
     * Seguimiento pide **125 pruebas en cada uno de los 12**, o sea 1500.
     *
     * La página dice «debe realizar 250 anulaciones», y esa redacción se había
     * tomado por «total de la etapa». Con 250 repartidos entre 12 casos salían
     * 21 por caso, así que los seis que llevaban 42 anulaciones figuraban
     * «completados» al doble de lo pedido mientras el panel los daba en 33 %.
     */
    public function test_la_anulacion_son_ciento_veinticinco_pruebas_por_caso(): void
    {
        $casos = collect(app(HomologacionMatriz::class)->generar($this->setting, 7));

        // Cada caso pide las 125 enteras: no se reparte nada entre ellos, que
        // es justo lo que hacía `TOTAL_ETAPA`. Con los seis sectores del NIT
        // real son 12 casos y 1500 pruebas.
        $this->assertSame([125], $casos->pluck('cantidad')->unique()->values()->all());
        $this->assertSame(125 * $casos->count(), $casos->sum('cantidad'));
        $this->assertArrayNotHasKey(7, HomologacionMatriz::TOTAL_ETAPA);
        $this->assertSame(125, HomologacionMatriz::POR_CASO[7]);
    }

    /**
     * Los timeouts internos del piloto llegan con `mensajesList` vacío y el
     * motivo suelto en `codigoDescripcion`. Mirando solo la lista salían como
     * «sin detalle en la respuesta», que parece un rechazo nuestro cuando en
     * realidad es el SIN cayéndose y basta reintentar.
     */
    public function test_un_fallo_sin_mensajes_explica_lo_que_diga_el_sin(): void
    {
        $porQue = function (string $servicio, array $resultado): string {
            $metodo = new \ReflectionMethod($servicio, 'porQue');
            $metodo->setAccessible(true);

            return $metodo->invoke(app($servicio), $resultado);
        };

        foreach ([SiatFacturacionService::class, SiatDocumentoAjusteService::class] as $servicio) {
            // El caso real: el piloto se cae y solo manda codigoDescripcion.
            $this->assertSame(
                'Error inesperado: java.util.concurrent.TimeoutException: Request timeout (codigoEstado -1)',
                $porQue($servicio, [
                    'mensajes'          => [],
                    'codigoEstado'      => -1,
                    'codigoDescripcion' => 'Error inesperado: java.util.concurrent.TimeoutException: Request timeout',
                ]),
            );

            // Con mensajes de verdad manda la lista, como siempre.
            $this->assertSame(
                '1045 CAFC ESPERADO NULL',
                $porQue($servicio, [
                    'mensajes'          => ['1045 CAFC ESPERADO NULL'],
                    'codigoEstado'      => 904,
                    'codigoDescripcion' => 'OBSERVADA',
                ]),
            );

            // Y sin nada aprovechable se dice tal cual.
            $this->assertSame(
                'sin detalle en la respuesta.',
                $porQue($servicio, ['mensajes' => [], 'codigoEstado' => null, 'codigoDescripcion' => null]),
            );
        }
    }

    public function test_el_alcance_es_el_de_todas_las_actividades_del_nit(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('actividades')->andReturn([
                '4741100' => 'VENTA AL POR MENOR DE COMPUTADORAS',
                '474000'  => 'VENTA AL POR MENOR EN COMERCIOS',
                '620900'  => 'OTRAS ACTIVIDADES DE TECNOLOGIA',
            ]);
            $mock->shouldReceive('documentosSectorDe')->with(\Mockery::any(), '4741100')
                ->andReturn([1 => 'FCV', 24 => 'NCD', 47 => 'NCDDE']);
            $mock->shouldReceive('documentosSectorDe')->with(\Mockery::any(), '474000')
                ->andReturn([1 => 'FCV', 23 => 'FAC_PRE', 34 => 'FAC_SEG', 35 => 'FAC_CVB', 24 => 'NCD', 47 => 'NCDDE']);
            $mock->shouldReceive('documentosSectorDe')->with(\Mockery::any(), '620900')
                ->andReturn([34 => 'FAC_SEG', 35 => 'FAC_CVB']);
            $mock->shouldReceive('eventosSignificativos')->andReturn([
                1 => 'CORTE DE INTERNET', 2 => 'INACCESIBILIDAD', 3 => 'ZONAS SIN INTERNET',
                4 => 'VENTA SIN INTERNET', 5 => 'VIRUS', 6 => 'HARDWARE', 7 => 'ENERGIA',
            ]);
        });

        $matriz = app(HomologacionMatriz::class);

        $this->assertSame([1, 23, 24, 34, 35, 47], $matriz->sectores($this->setting));
        // Las notas no tienen ni paquete ni masiva: quedan fuera de VI y de IX.
        $this->assertSame([1, 23, 34, 35], $matriz->sectoresConPaquete($this->setting));
    }

    /**
     * Y de ahí salen los 320: cuatro sectores facturables × ocho casos × diez
     * pruebas. Con un solo sector eran 80, y la etapa parecía vencida.
     */
    public function test_la_masiva_cruza_todos_los_sectores_facturables(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('actividades')->andReturn(['474000' => 'VENTA AL POR MENOR']);
            $mock->shouldReceive('documentosSectorDe')->andReturn([
                1 => 'FCV', 23 => 'FAC_PRE', 34 => 'FAC_SEG', 35 => 'FAC_CVB', 24 => 'NCD', 47 => 'NCDDE',
            ]);
            $mock->shouldReceive('eventosSignificativos')->andReturn([1 => 'CORTE DE INTERNET']);
        });

        $casos = collect(app(HomologacionMatriz::class)->generar($this->setting, 9));

        $this->assertCount(32, $casos);
        $this->assertSame(320, $casos->sum('cantidad'));
        $this->assertSame([1, 23, 34, 35], $casos->pluck('documento_sector')->unique()->sort()->values()->all());
        // Ocho por sector: dos puntos de venta × dos tamaños × envío y validación.
        $this->assertCount(8, $casos->where('documento_sector', 34));
        $this->assertContains('e9-s34-pv1-n1000', $casos->pluck('caso')->all());
    }

    /** La masiva son 8: cuatro envíos y la validación de cada uno. */
    public function test_la_masiva_valida_cada_lote_que_envia(): void
    {
        $casos = collect(app(HomologacionMatriz::class)->generar($this->setting, 9));

        $this->assertCount(8, $casos);
        $this->assertSame(
            ['e9-s1-val-pv0-n1000', 'e9-s1-val-pv0-n500', 'e9-s1-val-pv1-n1000', 'e9-s1-val-pv1-n500'],
            $casos->filter->esValidacion()->pluck('caso')->sort()->values()->all(),
        );
    }

    /** Sin paquetes enviados no hay nada que consultar, y se dice. */
    public function test_una_validacion_sin_paquetes_lo_explica(): void
    {
        app(HomologacionMatriz::class)->generar($this->setting, 9);
        $caso = SiatHomologacionCaso::where('caso', 'e9-s1-val-pv0-n1000')->firstOrFail();

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/Ejecute antes los casos de envío/');

        app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 1);
    }

    /**
     * La etapa II se cuenta por operación y punto de venta, no por barrido: 18
     * operaciones × 2 puntos × 50 pruebas = 1800, que es lo que muestra el Portal.
     */
    public function test_la_sincronizacion_son_treinta_y_seis_casos_de_cincuenta(): void
    {
        $casos = app(HomologacionMatriz::class)->generar($this->setting, 2);

        $this->assertCount(36, $casos);
        $this->assertSame(50, $casos[0]->cantidad);
        $this->assertSame(1800, array_sum(array_map(fn ($c) => $c->cantidad, $casos)));
        $this->assertContains('fecha_hora', array_map(fn ($c) => $c->catalogo, $casos));
    }

    /** Cada caso consume solo su catálogo, no los diecisiete. */
    public function test_cada_caso_de_sincronizacion_consume_un_unico_catalogo(): void
    {
        $llamadas = [];

        $this->mock(SiatSincronizacionService::class, function ($mock) use (&$llamadas): void {
            $mock->shouldReceive('actividades')->andReturn(['4741100' => 'VENTA']);
            $mock->shouldReceive('documentosSectorDe')->andReturn([1 => 'FCV']);
            $mock->shouldReceive('olvidar')->andReturnNull();
            $mock->shouldReceive('tiposMoneda')->andReturnUsing(function () use (&$llamadas) {
                $llamadas[] = 'tiposMoneda';

                return [];
            });
            $mock->shouldNotReceive('paisesOrigen');
        });

        app(HomologacionMatriz::class)->generar($this->setting, 2);
        $caso = SiatHomologacionCaso::where('caso', 'e2-tipos_moneda-pv0')->firstOrFail();

        app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 3);

        $this->assertSame(['tiposMoneda', 'tiposMoneda', 'tiposMoneda'], $llamadas);
    }

    public function test_los_eventos_cubren_los_siete_motivos_por_punto_de_venta(): void
    {
        $casos = app(HomologacionMatriz::class)->generar($this->setting, 5);

        $this->assertCount(14, $casos);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], collect($casos)->pluck('motivo_evento')->unique()->sort()->values()->all());
        // «Son 5 pruebas por cada caso»: 14 × 5 = 70, no 14.
        $this->assertSame([5], collect($casos)->pluck('cantidad')->unique()->values()->all());
        $this->assertSame(70, collect($casos)->sum('cantidad'));
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
            $mock->shouldReceive('actividades')->andReturn(['4741100' => 'VENTA']);
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

    /**
     * Lo emitido antes del corte ya está en el SIN y hay que contarlo. Llevando
     * el contador al final del caso, matar el proceso a la mitad de las 84
     * emisiones dejaba los documentos gastados en el piloto y el caso a cero, y
     * al reanudar se emitían otras 84.
     */
    public function test_una_interrupcion_a_media_tanda_conserva_lo_ya_emitido(): void
    {
        $this->prepararEmision();
        $caso = $this->caso('e4-s1-pv0');

        $emitidas = 0;

        $this->mock(SiatService::class, function ($mock) use (&$emitidas): void {
            $mock->shouldReceive('createInvoice')->andReturnUsing(function () use (&$emitidas) {
                if (++$emitidas > 3) {
                    throw new SiatException('se cayó la red');
                }

                return new SiatInvoice(['estado' => 'enviada', 'cuf' => 'CUF-' . $emitidas]);
            });
        });

        try {
            app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 10);
            $this->fail('Tenía que propagar el corte.');
        } catch (SiatException) {
            // esperado
        }

        $this->assertSame(3, $caso->fresh()->completados);
        $this->assertSame('fallido', $caso->fresh()->estado);
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
     * coloca justo después del último ya declarado.
     */
    public function test_los_cortes_se_declaran_sin_solaparse(): void
    {
        $this->prepararEmision();
        $this->fakeContingencia();

        $runner = app(HomologacionRunner::class);
        $matriz = app(HomologacionMatriz::class);
        $matriz->generar($this->setting, 5);

        $primero = SiatHomologacionCaso::where('caso', 'e5-m1-pv0')->firstOrFail();
        $segundo = SiatHomologacionCaso::where('caso', 'e5-m2-pv0')->firstOrFail();

        $runner->ejecutar($primero, $this->setting, limite: 1);
        $runner->ejecutar($segundo, $this->setting, limite: 1);

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
            app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 1);
            $this->fail('Tenía que propagar el rechazo.');
        } catch (SiatException) {
            // esperado
        }

        $this->assertSame(0, SiatEvento::count());
    }

    /**
     * Un caso de paquete pide diez pruebas, y una prueba es un paquete entero.
     * Sin el bucle cada pasada mandaba uno solo y cerrar el caso exigía invocar
     * el comando diez veces.
     */
    public function test_una_pasada_manda_tantos_paquetes_como_pruebas_pida_el_caso(): void
    {
        $this->prepararEmision(doblarEmision: false);
        $this->setting->update(['leyenda' => 'Ley N 453: El proveedor debe habilitar medios e instancias de atencion.']);
        $this->fakeContingencia();

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('recepcionFactura');
            $mock->shouldReceive('recepcionPaqueteFactura')->times(3)->andReturn([
                'codigoRecepcion' => 'PAQ-1', 'codigoEstado' => 901,
                'codigoDescripcion' => 'PENDIENTE', 'mensajes' => [], 'respuesta' => [],
            ]);
        });

        app(HomologacionMatriz::class)->generar($this->setting, 6);

        $caso = SiatHomologacionCaso::where('caso', 'e6-s1-m1-n500')->firstOrFail();
        $caso->update(['tamano_lote' => 2]);

        $hechos = app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 3);

        $this->assertSame(3, $hechos);
        $this->assertSame(3, $caso->fresh()->completados);
        $this->assertSame(6, SiatInvoice::count(), 'Tres paquetes de dos facturas.');
    }

    /**
     * Diez paquetes seguidos abriendo todos el corte en `now()-2h` se solaparían,
     * y el SIN los rechaza con el 981.
     */
    public function test_los_cortes_de_dos_paquetes_seguidos_no_se_solapan(): void
    {
        $this->prepararEmision(doblarEmision: false);
        $this->setting->update(['leyenda' => 'Ley N 453: El proveedor debe habilitar medios e instancias de atencion.']);
        $this->fakeContingencia();

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionPaqueteFactura')->andReturn([
                'codigoRecepcion' => 'PAQ-1', 'codigoEstado' => 901,
                'codigoDescripcion' => 'PENDIENTE', 'mensajes' => [], 'respuesta' => [],
            ]);
        });

        app(HomologacionMatriz::class)->generar($this->setting, 6);

        $caso = SiatHomologacionCaso::where('caso', 'e6-s1-m1-n500')->firstOrFail();
        $caso->update(['tamano_lote' => 1]);

        app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 2);

        $cortes = SiatEvento::orderBy('fecha_inicio')->get();

        $this->assertCount(2, $cortes);
        $this->assertTrue(
            $cortes[0]->fecha_fin->lessThanOrEqualTo($cortes[1]->fecha_inicio),
            'El segundo corte tiene que empezar después de que cierre el primero.',
        );
    }

    /**
     * El SIN rechaza el paquete al enviarlo, o sea despues de haber emitido las
     * quinientas facturas del corte. Sin CAFC configurado hay que pararlo antes.
     */
    public function test_un_motivo_que_exige_cafc_se_para_antes_de_emitir(): void
    {
        config(['siat.cafc.codigo' => null]);
        $this->prepararEmision(doblarEmision: false);
        app(HomologacionMatriz::class)->generar($this->setting, 6);

        $caso = SiatHomologacionCaso::where('caso', 'e6-s1-m5-n500')->firstOrFail();

        try {
            app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 1);
            $this->fail('Tenía que exigir el CAFC.');
        } catch (SiatException $e) {
            $this->assertStringContainsString('exige un CAFC', $e->getMessage());
        }

        $this->assertSame(0, SiatEvento::where('codigo_motivo_evento', 5)->count(), 'No debe abrir el corte.');
        $this->assertSame(0, SiatInvoice::count(), 'No debe emitir ninguna factura.');
    }

    /** Los motivos de conectividad van sin CAFC: mandarlo el SIN lo rechaza. */
    public function test_los_motivos_de_conectividad_no_piden_cafc(): void
    {
        config(['siat.cafc.codigo' => null]);
        $this->prepararEmision(doblarEmision: false);
        $this->setting->update(['leyenda' => 'Ley N 453: El proveedor debe habilitar medios e instancias de atencion.']);
        $this->fakeContingencia();

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionPaqueteFactura')->once()
                ->withArgs(fn (...$args) => end($args) === null)
                ->andReturn([
                    'codigoRecepcion' => 'PAQ-1', 'codigoEstado' => 901,
                    'codigoDescripcion' => 'PENDIENTE', 'mensajes' => [], 'respuesta' => [],
                ]);
        });

        app(HomologacionMatriz::class)->generar($this->setting, 6);
        $caso = SiatHomologacionCaso::where('caso', 'e6-s1-m1-n500')->firstOrFail();
        $caso->update(['tamano_lote' => 1]);

        app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 1);

        $this->assertSame(1, $caso->fresh()->completados);
        $this->assertNull(SiatEvento::where('codigo_motivo_evento', 1)->value('cafc'));
    }

    /**
     * La masiva no tenía ninguna prueba que la ejecutara, y por eso se coló un
     * `$cuantas` sin declarar que solo apareció contra el piloto.
     */
    public function test_la_masiva_manda_tantos_lotes_como_pruebas_pida_el_caso(): void
    {
        $this->prepararEmision(doblarEmision: false);
        $this->setting->update(['leyenda' => 'Ley N 453: El proveedor debe habilitar medios e instancias de atencion.']);
        $this->fakeContingencia();

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionMasivaFactura')->times(2)->andReturn([
                'codigoRecepcion' => 'MAS-1', 'codigoEstado' => 901,
                'codigoDescripcion' => 'PENDIENTE', 'mensajes' => [], 'respuesta' => [],
            ]);
        });

        app(HomologacionMatriz::class)->generar($this->setting, 9);
        $caso = SiatHomologacionCaso::where('caso', 'e9-s1-pv0-n500')->firstOrFail();
        $caso->update(['tamano_lote' => 2]);

        $hechos = app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 2);

        $this->assertSame(2, $hechos);
        $this->assertSame(2, $caso->fresh()->completados);
        $this->assertSame(4, SiatInvoice::count(), 'Dos lotes de dos facturas.');
        $this->assertFalse((bool) $this->setting->fresh()->emision_masiva, 'El interruptor vuelve a su sitio.');
    }

    /** Sin tamaño de lote el paquete saldría vacío y quemaría una prueba. */
    public function test_un_caso_sin_tamano_de_lote_no_manda_un_paquete_vacio(): void
    {
        $this->prepararEmision();
        $this->fakeContingencia();

        app(HomologacionMatriz::class)->generar($this->setting, 6);

        $caso = SiatHomologacionCaso::where('caso', 'e6-s1-m1-n500')->firstOrFail();
        $caso->update(['tamano_lote' => null]);

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/tamaño de lote/');

        app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 1);
    }

    /**
     * Las 70 pruebas de la etapa V tienen que caber dentro de la vigencia del
     * CUFD, que empieza cuando se pidió. Escalonando las franjas hacia atrás en
     * bloques de cuatro minutos, un CUFD recién pedido no daba ni para tres
     * cortes: la etapa era imposible de terminar. Empaquetadas hacia adelante,
     * entran.
     */
    public function test_muchos_cortes_caben_bajo_un_cufd_recien_pedido(): void
    {
        $this->prepararEmision();
        $this->fakeContingencia();

        // Recién pedido: solo hay hueco entre su emisión y ahora.
        SiatCufdCode::query()->each(
            fn (SiatCufdCode $c) => $c->forceFill(['created_at' => now()->subMinutes(3)])->save()
        );

        $caso  = SiatHomologacionCaso::where('caso', 'e5-m1-pv0')->firstOrFail();
        $caso->update(['cantidad' => 10]);

        $hechos = app(HomologacionRunner::class)->ejecutar($caso, $this->setting);

        $this->assertSame(10, $hechos);
        $this->assertSame(10, $caso->fresh()->completados);

        $franjas = SiatEvento::orderBy('fecha_inicio')->get();
        $this->assertCount(10, $franjas);

        foreach ($franjas as $i => $evento) {
            $this->assertTrue(
                $evento->fecha_inicio->greaterThanOrEqualTo(SiatCufdCode::first()->created_at),
                'Una franja fuera de la vigencia del CUFD es un 984.',
            );
            $this->assertTrue($evento->fecha_fin->lessThanOrEqualTo(now()), 'Un corte no termina en el futuro.');

            if ($i > 0) {
                $this->assertTrue(
                    $franjas[$i - 1]->fecha_fin->lessThan($evento->fecha_inicio),
                    'Dos franjas solapadas son un 981.',
                );
            }
        }
    }

    /**
     * Cuando los cortes ya llenan la vigencia hasta ahora, la única franja
     * posible terminaría en el futuro. Se dice en vez de declararla.
     */
    public function test_sin_hueco_por_delante_se_explica_en_vez_de_inventar_franja(): void
    {
        $this->prepararEmision();
        $this->fakeContingencia();

        SiatEvento::create([
            'store_id'             => $this->store->id,
            'cufd_code_id'         => SiatCufdCode::first()->id,
            'codigo_motivo_evento' => 1,
            'descripcion'          => 'corte que llega hasta ahora',
            'fecha_inicio'         => now()->subHour(),
            'fecha_fin'            => now(),
            'estado'               => 'registrado',
        ]);

        $caso = SiatHomologacionCaso::where('caso', 'e5-m1-pv0')->firstOrFail();

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/no puede terminar en el futuro/');

        app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 1);
    }

    // ─── Etapa III: CUFD ────────────────────────────────────────────────────

    /**
     * `CasosDePruebaCUFD.xlsx` son dos filas —punto de venta 1 y punto de venta
     * 0— y la página pide «100 pruebas por cada caso»: 200 en total.
     *
     * No se cruza con el documento sector, a diferencia de la emisión: el CUFD
     * es del punto de venta y vale para cualquier documento emitido bajo él.
     */
    public function test_el_cufd_son_dos_casos_de_cien_pruebas(): void
    {
        $casos = app(HomologacionMatriz::class)->generar($this->setting, 3);

        $this->assertSame(['e3-pv0', 'e3-pv1'], array_map(fn ($c) => $c->caso, $casos));
        $this->assertSame([100, 100], array_map(fn ($c) => $c->cantidad, $casos));
        $this->assertSame(200, array_sum(array_map(fn ($c) => $c->cantidad, $casos)));
        $this->assertNull($casos[0]->documento_sector);
    }

    /**
     * Cada prueba es una llamada al SIN, no una lectura del CUFD vigente: por
     * `CufdProvider` —que es por donde va la emisión— devolvería el que ya está
     * activo sin hablar con el servicio, y el Portal no contaría nada.
     */
    public function test_cada_prueba_de_cufd_pide_uno_nuevo_al_sin(): void
    {
        $pedidos = 0;

        $this->mock(SiatCodigosService::class, function ($mock) use (&$pedidos): void {
            // Cierre de verdad y no flecha: `fn ()` captura por valor y el
            // contador de fuera se quedaría a cero.
            $mock->shouldReceive('solicitarCufd')->andReturnUsing(function () use (&$pedidos) {
                return new SiatCufdCode(['codigo' => 'CUFD-' . ++$pedidos]);
            });
        });

        app(HomologacionMatriz::class)->generar($this->setting, 3);
        $caso = SiatHomologacionCaso::where('caso', 'e3-pv1')->firstOrFail();

        $hechos = app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 3);

        $this->assertSame(3, $hechos);
        $this->assertSame(3, $pedidos);
        $this->assertSame(3, $caso->fresh()->completados);
        $this->assertSame('CUFD-3', $caso->fresh()->referencia);
        // Y por el punto de venta del caso, que es lo que distingue las dos filas.
        $this->assertSame(1, (int) $this->setting->fresh()->codigo_punto_venta);
    }

    /**
     * Con un corte abierto, rotar el CUFD deja su franja fuera de vigencia y el
     * SIN solo lo dice al enviar el paquete —«984 EL EVENTO NO CORRESPONDE AL
     * CUFD»—, o sea cuando ya se emitieron sus quinientas facturas. Se para
     * antes de la primera llamada.
     */
    public function test_no_se_piden_cufd_con_un_corte_abierto(): void
    {
        $this->mock(SiatCodigosService::class, function ($mock): void {
            $mock->shouldNotReceive('solicitarCufd');
        });

        app(HomologacionMatriz::class)->generar($this->setting, 3);

        SiatEvento::create([
            'store_id'             => $this->store->id,
            'codigo_motivo_evento' => 1,
            'descripcion'          => 'CORTE DE INTERNET',
            'fecha_inicio'         => now()->subHour(),
            'estado'               => 'abierto',
        ]);

        $caso = SiatHomologacionCaso::where('caso', 'e3-pv0')->firstOrFail();

        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/corte abierto/');

        app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 1);
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

    /**
     * Revertir devuelve la factura a «enviada», así que vuelve al montón. Sin
     * avanzar, las 250 anulaciones de la etapa VII serían una sola repetida.
     */
    public function test_cada_anulacion_toma_un_documento_distinto(): void
    {
        $this->prepararEmision(doblarEmision: false);

        $facturas = collect(range(1, 3))->map(fn (int $n) => $this->facturaHomologada($n));
        $anuladas = [];

        $this->mock(SiatService::class, function ($mock) use (&$anuladas): void {
            $mock->shouldReceive('cancelInvoice')->andReturnUsing(
                function (SiatInvoice $f) use (&$anuladas): void { $anuladas[] = $f->id; },
            );
            $mock->shouldReceive('revertCancellation')->andReturn(['codigoEstado' => 907]);
        });

        app(HomologacionMatriz::class)->generar($this->setting, 7);
        $caso = SiatHomologacionCaso::where('caso', 'e7-s1-pv0')->firstOrFail();

        app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 3);

        $this->assertSame($facturas->pluck('id')->all(), $anuladas);
        $this->assertSame(3, $caso->fresh()->completados);
    }

    /** Se agota el montón antes que las pruebas y hay que decirlo, no repetir. */
    public function test_sin_documentos_bastantes_la_anulacion_lo_dice(): void
    {
        $this->prepararEmision(doblarEmision: false);
        $this->facturaHomologada(1);

        $this->mock(SiatService::class, function ($mock): void {
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
            $mock->shouldReceive('revertCancellation')->andReturn(['codigoEstado' => 907]);
        });

        app(HomologacionMatriz::class)->generar($this->setting, 7);
        $caso = SiatHomologacionCaso::where('caso', 'e7-s1-pv0')->firstOrFail();

        try {
            app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 2);
            $this->fail('Tenía que quedarse sin facturas.');
        } catch (SiatException $e) {
            $this->assertStringContainsString('Se agotaron las facturas', $e->getMessage());
        }

        // La primera sí se hizo y no se pierde.
        $this->assertSame(1, $caso->fresh()->completados);
    }

    /**
     * `sales.folio` es único y los lotes de las etapas VI y IX se emiten dentro
     * del mismo segundo. Con un sufijo aleatorio de tres cifras —novecientos
     * valores— el lote reventaba por clave duplicada antes de la factura
     * cincuenta.
     */
    public function test_un_lote_emitido_de_golpe_no_repite_folio(): void
    {
        $this->prepararEmision();
        $caso = $this->caso('e4-s1-pv0');

        app(HomologacionRunner::class)->ejecutar($caso, $this->setting, limite: 60);

        $folios = Sale::where('folio', 'like', HomologacionRunner::PREFIJO . '%')->pluck('folio');

        $this->assertCount(60, $folios);
        $this->assertCount(60, $folios->unique(), 'Dos ventas del mismo lote comparten folio.');
    }

    private function facturaHomologada(int $numero): SiatInvoice
    {
        $turno = CashShift::query()->firstOrFail();

        $venta = Sale::create([
            'cash_shift_id' => $turno->id, 'user_id' => $turno->user_id,
            'folio' => HomologacionRunner::PREFIJO . "-{$numero}",
            'subtotal' => 100, 'total' => 100, 'amount_paid' => 100,
            'payment_method' => 'cash', 'status' => 'completed',
        ]);

        return SiatInvoice::create([
            'sale_id' => $venta->id, 'store_id' => $this->store->id,
            'cufd_code_id'  => SiatCufdCode::where('codigo', 'CUFD-PV0')->value('id'),
            'numero_factura' => $numero, 'fecha_emision' => now(),
            'cuf' => 'CUF-HOMOL-' . $numero, 'cufd' => 'CUFD-PV0',
            'importe_total' => 100, 'importe_base_cf' => 100,
            'tipo_factura' => 1, 'estado' => 'enviada',
        ]);
    }

    private function caso(string $nombre): SiatHomologacionCaso
    {
        app(HomologacionMatriz::class)->generar($this->setting, 4);

        return SiatHomologacionCaso::where('caso', $nombre)->firstOrFail();
    }

    /** Dobla la emisión: el runner no habla con el SIN en las pruebas. */
    private function prepararEmision(bool $rechazada = false, bool $doblarEmision = true): void
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
        // Cada punto de venta lleva su propia cadena: los envíos de paquete van
        // por el punto 1, que es el que usa el Excel de la etapa VI.
        foreach ([0, 1] as $pv) {
            SiatCufdCode::create([
                'store_id' => $this->store->id,
                'punto_venta_id' => SiatPuntoVenta::where('codigo', $pv)->value('id'),
                'codigo' => "CUFD-PV{$pv}", 'codigo_control' => "CTRL{$pv}",
                'fecha_vigencia' => now()->addHours(20), 'consecutivo' => 0, 'estado' => 'activo',
            ])->forceFill(['created_at' => now()->subHours(4)])->save();
        }

        // Los casos que solo cuentan emisiones no necesitan facturas de verdad.
        // Los de paquete sí: `enviarPaquete` las vuelve a buscar en la base.
        if (! $doblarEmision) {
            return;
        }

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
            $mock->shouldReceive('actividades')->andReturn(['4741100' => 'VENTA AL POR MENOR DE COMPUTADORAS']);
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
