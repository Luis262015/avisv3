<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SiatSetting;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatSincronizacionService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Las 18 operaciones de sincronización del SIN.
 *
 * La etapa II de la homologación consiste en consumirlas todas, así que lo que se
 * comprueba aquí es que cada catálogo llame a la operación que le toca del WSDL,
 * que la solicitud lleve exactamente los siete campos de `solicitudSincronizacion`
 * y que los tres catálogos con forma propia —leyendas, productos y documentos
 * sector— se agrupen bien. La llamada SOAP va doblada: nada de esto toca la red.
 */
class SiatSincronizacionServiceTest extends TestCase
{
    private SiatSetting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // Sin tocar la base: al servicio solo le interesan estos campos.
        $this->setting = new SiatSetting([
            'store_id'         => 1,
            'nit'              => '6923448010',
            'codigo_sistema'   => 'SISTEMA-DE-PRUEBA',
            'codigo_sucursal'  => 0,
            'codigo_punto_venta' => 0,
            'modalidad'        => 2,
            'ambiente'         => 'piloto',
            'cuis'             => 'CUIS-DE-PRUEBA',
        ]);
        $this->setting->store_id = 1;
    }

    public function test_agrupa_los_documentos_sector_por_actividad(): void
    {
        $servicio = $this->servicio([
            'sincronizarListaActividadesDocumentoSector' => [
                'listaActividadesDocumentoSector' => [
                    ['codigoActividad' => '4741100', 'codigoDocumentoSector' => '1',  'tipoDocumentoSector' => 'FCV'],
                    ['codigoActividad' => '4741100', 'codigoDocumentoSector' => '24', 'tipoDocumentoSector' => 'NCD'],
                    ['codigoActividad' => '4741100', 'codigoDocumentoSector' => '47', 'tipoDocumentoSector' => 'NCDDE'],
                    ['codigoActividad' => '620900',  'codigoDocumentoSector' => '1',  'tipoDocumentoSector' => 'FCV'],
                ],
            ],
        ]);

        $porActividad = $servicio->documentosSector($this->setting);

        $this->assertSame([1 => 'FCV', 24 => 'NCD', 47 => 'NCDDE'], $porActividad['4741100']);
        $this->assertSame([1 => 'FCV'], $porActividad['620900']);
    }

    public function test_los_documentos_sector_de_una_actividad_ajena_al_nit_vienen_vacios(): void
    {
        $servicio = $this->servicio([
            'sincronizarListaActividadesDocumentoSector' => [
                'listaActividadesDocumentoSector' => [
                    ['codigoActividad' => '4741100', 'codigoDocumentoSector' => '1', 'tipoDocumentoSector' => 'FCV'],
                ],
            ],
        ]);

        $this->assertSame([1 => 'FCV'], $servicio->documentosSectorDe($this->setting, '4741100'));
        $this->assertSame([], $servicio->documentosSectorDe($this->setting, '999999'));
    }

    /**
     * Un solo elemento llega del SOAP como objeto y no como lista; sin
     * normalizarlo, el catálogo se perdería entero.
     */
    public function test_admite_una_respuesta_de_un_solo_elemento(): void
    {
        $servicio = $this->servicio([
            'sincronizarListaActividadesDocumentoSector' => [
                'listaActividadesDocumentoSector' => [
                    'codigoActividad' => '4741100', 'codigoDocumentoSector' => '1', 'tipoDocumentoSector' => 'FCV',
                ],
            ],
        ]);

        $this->assertSame(['4741100' => [1 => 'FCV']], $servicio->documentosSector($this->setting));
    }

    #[DataProvider('parametricas')]
    public function test_cada_parametrica_consume_su_operacion_del_wsdl(string $metodo, string $operacion): void
    {
        $servicio = $this->servicio([
            $operacion => [
                'listaCodigos' => [
                    ['codigoClasificador' => '7', 'descripcion' => 'UNA DESCRIPCIÓN'],
                ],
            ],
        ]);

        $this->assertSame([7 => 'UNA DESCRIPCIÓN'], $servicio->{$metodo}($this->setting));
        $this->assertSame([$operacion], $servicio->operaciones);
    }

    /** @return list<array{0: string, 1: string}> */
    public static function parametricas(): array
    {
        return [
            ['unidadesMedida',          'sincronizarParametricaUnidadMedida'],
            ['motivosAnulacion',        'sincronizarParametricaMotivoAnulacion'],
            ['eventosSignificativos',   'sincronizarParametricaEventosSignificativos'],
            ['tiposEmision',            'sincronizarParametricaTipoEmision'],
            ['tiposFactura',            'sincronizarParametricaTiposFactura'],
            ['tiposDocumentoSector',    'sincronizarParametricaTipoDocumentoSector'],
            ['tiposDocumentoIdentidad', 'sincronizarParametricaTipoDocumentoIdentidad'],
            ['tiposMetodoPago',         'sincronizarParametricaTipoMetodoPago'],
            ['tiposMoneda',             'sincronizarParametricaTipoMoneda'],
            ['tiposPuntoVenta',         'sincronizarParametricaTipoPuntoVenta'],
            ['paisesOrigen',            'sincronizarParametricaPaisOrigen'],
            ['tiposHabitacion',         'sincronizarParametricaTipoHabitacion'],
            ['mensajesServicios',       'sincronizarListaMensajesServicios'],
        ];
    }

    /**
     * El catálogo declarado tiene que cubrir las 17 listas: es lo que recorre
     * `olvidarCache()` y el comando de consola para dar la etapa II por cubierta.
     */
    public function test_el_catalogo_declarado_apunta_a_metodos_que_existen(): void
    {
        $this->assertCount(17, SiatSincronizacionService::CATALOGOS);

        foreach (SiatSincronizacionService::CATALOGOS as $clave => $metodo) {
            $this->assertTrue(
                method_exists(SiatSincronizacionService::class, $metodo),
                "El catálogo «{$clave}» apunta a {$metodo}(), que no existe.",
            );
        }
    }

    public function test_olvidar_cache_vacia_todos_los_catalogos(): void
    {
        $servicio = $this->servicio([
            'sincronizarParametricaTipoMoneda' => [
                'listaCodigos' => [['codigoClasificador' => '1', 'descripcion' => 'BOLIVIANO']],
            ],
        ]);

        $servicio->tiposMoneda($this->setting);
        $servicio->tiposMoneda($this->setting);

        $this->assertCount(1, $servicio->operaciones, 'La segunda lectura tenía que salir de la caché.');

        $servicio->olvidarCache($this->setting);
        $servicio->tiposMoneda($this->setting);

        $this->assertCount(2, $servicio->operaciones);
    }

    /**
     * `solicitudSincronizacion` son seis campos exactos. Mandar de más —había un
     * `codigoModalidad` heredado de la solicitud de CUFD— es ruido que no está en
     * el contrato del WSDL.
     */
    public function test_la_solicitud_lleva_los_seis_campos_del_wsdl(): void
    {
        $servicio = new class extends SiatSincronizacionService {
            /** @return array<string, mixed> */
            public function solicitudDePrueba(SiatSetting $setting): array
            {
                return $this->solicitud($setting, codigoAmbiente: 2);
            }
        };

        $solicitud = $servicio->solicitudDePrueba($this->setting);

        $this->assertSame(
            ['codigoAmbiente', 'codigoPuntoVenta', 'codigoSistema', 'codigoSucursal', 'cuis', 'nit'],
            array_keys($solicitud),
        );
        $this->assertSame(2, $solicitud['codigoAmbiente']);
        $this->assertSame(0, $solicitud['codigoPuntoVenta']);
        $this->assertSame('SISTEMA-DE-PRUEBA', $solicitud['codigoSistema']);
        $this->assertSame(6923448010, $solicitud['nit'], 'El NIT va como entero, no como cadena.');
    }

    public function test_sin_cuis_no_llega_a_llamar_al_sin(): void
    {
        $this->setting->cuis = null;

        $this->expectException(SiatException::class);

        (new SiatSincronizacionService())->tiposMoneda($this->setting);
    }

    /**
     * Servicio con la llamada SOAP doblada: devuelve lo que se le indique por
     * operación y anota cuáles se pidieron.
     *
     * @param  array<string, array<string, mixed>>  $respuestas
     */
    private function servicio(array $respuestas): SiatSincronizacionService
    {
        return new class($respuestas) extends SiatSincronizacionService {
            /** @var list<string> */
            public array $operaciones = [];

            /** @param array<string, array<string, mixed>> $respuestas */
            public function __construct(private readonly array $respuestas) {}

            protected function llamar(SiatSetting $setting, string $operacion): array
            {
                $this->operaciones[] = $operacion;

                return $this->respuestas[$operacion] ?? [];
            }
        };
    }
}
