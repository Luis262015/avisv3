<?php

namespace App\Services\Siat;

use App\Models\SiatSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Servicio "FacturacionSincronizacion": las paramétricas del SIN.
 *
 * Los códigos de leyenda, actividad, unidad de medida y motivo de anulación los
 * publica el SIN y cambian sin aviso, así que se consultan en vez de fijarlos en
 * el código. Se cachean porque son estables dentro de una jornada.
 */
class SiatSincronizacionService
{
    private const SERVICIO = 'sincronizacion';

    /**
     * Los catálogos que publica el servicio, con el método que resuelve cada uno.
     *
     * Son 17, y con `sincronizarFechaHora` —que no devuelve lista y por eso no se
     * cachea— completan las 18 operaciones del WSDL que exige la etapa II de la
     * homologación.
     *
     * @var array<string, string> clave de caché => método
     */
    public const CATALOGOS = [
        'leyendas'               => 'leyendas',
        'actividades'            => 'actividades',
        'documentos_sector'      => 'documentosSector',
        'productos'              => 'productosServicios',
        'unidades_medida'        => 'unidadesMedida',
        'motivos_anulacion'      => 'motivosAnulacion',
        'eventos_significativos' => 'eventosSignificativos',
        'tipos_emision'          => 'tiposEmision',
        'tipos_factura'          => 'tiposFactura',
        'tipos_documento_sector' => 'tiposDocumentoSector',
        'tipos_doc_identidad'    => 'tiposDocumentoIdentidad',
        'tipos_metodo_pago'      => 'tiposMetodoPago',
        'tipos_moneda'           => 'tiposMoneda',
        'tipos_punto_venta'      => 'tiposPuntoVenta',
        'paises_origen'          => 'paisesOrigen',
        'tipos_habitacion'       => 'tiposHabitacion',
        'mensajes_servicios'     => 'mensajesServicios',
    ];

    /**
     * Leyendas obligatorias por actividad económica.
     *
     * @return array<string, list<string>> actividad => leyendas
     */
    public function leyendas(SiatSetting $setting): array
    {
        return $this->cacheado($setting, 'leyendas', function () use ($setting) {
            $lista = $this->lista(
                $setting,
                'sincronizarListaLeyendasFactura',
                'listaLeyendas'
            );

            $porActividad = [];

            foreach ($lista as $item) {
                $porActividad[(string) ($item['codigoActividad'] ?? '')][] = (string) ($item['descripcionLeyenda'] ?? '');
            }

            return $porActividad;
        });
    }

    /**
     * Una leyenda válida para la actividad, o null si la actividad no tiene
     * ninguna registrada (que es señal de que la actividad no corresponde al NIT).
     */
    public function leyendaPara(SiatSetting $setting, string $actividad): ?string
    {
        $leyendas = $this->leyendas($setting)[$actividad] ?? [];

        return $leyendas[0] ?? null;
    }

    /**
     * Actividades económicas del contribuyente.
     *
     * @return array<string, string> código => descripción
     */
    public function actividades(SiatSetting $setting): array
    {
        return $this->cacheado($setting, 'actividades', function () use ($setting) {
            $lista = $this->lista($setting, 'sincronizarActividades', 'listaActividades');

            return collect($lista)
                ->mapWithKeys(fn($a) => [(string) $a['codigoCaeb'] => (string) $a['descripcion']])
                ->all();
        });
    }

    /**
     * Productos homologados del SIN, por actividad.
     *
     * @return list<array{actividad: string, codigo: int, descripcion: string}>
     */
    public function productosServicios(SiatSetting $setting): array
    {
        return $this->cacheado($setting, 'productos', function () use ($setting) {
            $lista = $this->lista($setting, 'sincronizarListaProductosServicios', 'listaCodigos');

            return array_map(fn($p) => [
                'actividad'   => (string) $p['codigoActividad'],
                'codigo'      => (int) $p['codigoProducto'],
                'descripcion' => (string) $p['descripcionProducto'],
            ], $lista);
        });
    }

    /**
     * Documentos sector habilitados para cada actividad del contribuyente.
     *
     * Es la única forma exacta de saber qué documentos puede emitir este NIT: el
     * SIN publica 28 sectores, pero solo cuentan los asociados a sus actividades.
     * De aquí sale el alcance real de la homologación.
     *
     * @return array<string, array<int, string>> actividad => [código sector => nombre]
     */
    public function documentosSector(SiatSetting $setting): array
    {
        return $this->cacheado($setting, 'documentos_sector', function () use ($setting) {
            $lista = $this->lista(
                $setting,
                'sincronizarListaActividadesDocumentoSector',
                'listaActividadesDocumentoSector'
            );

            $porActividad = [];

            foreach ($lista as $item) {
                $actividad = (string) ($item['codigoActividad'] ?? '');

                $porActividad[$actividad][(int) ($item['codigoDocumentoSector'] ?? 0)]
                    = (string) ($item['tipoDocumentoSector'] ?? '');
            }

            return $porActividad;
        });
    }

    /**
     * Documentos sector de una actividad concreta. Vacío significa que la
     * actividad no pertenece al NIT, igual que ocurre con las leyendas.
     *
     * @return array<int, string> código => nombre
     */
    public function documentosSectorDe(SiatSetting $setting, string $actividad): array
    {
        return $this->documentosSector($setting)[$actividad] ?? [];
    }

    /**
     * Unidades de medida.
     *
     * @return array<int, string> código => descripción
     */
    public function unidadesMedida(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaUnidadMedida', 'unidades_medida');
    }

    /**
     * Motivos de anulación admitidos.
     *
     * @return array<int, string> código => descripción
     */
    public function motivosAnulacion(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaMotivoAnulacion', 'motivos_anulacion');
    }

    /**
     * Motivos de evento significativo: qué corte se puede declarar y con qué
     * código. Son los que admite `registroEventoSignificativo`.
     *
     * @return array<int, string> código => descripción
     */
    public function eventosSignificativos(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaEventosSignificativos', 'eventos_significativos');
    }

    /**
     * Tipos de emisión (en línea, fuera de línea, masiva).
     *
     * @return array<int, string> código => descripción
     */
    public function tiposEmision(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaTipoEmision', 'tipos_emision');
    }

    /**
     * Tipos de factura: 1 con derecho a crédito fiscal, 2 sin él, 3 documento de
     * ajuste (el de las notas de crédito-débito), 4 documento equivalente.
     *
     * @return array<int, string> código => descripción
     */
    public function tiposFactura(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaTiposFactura', 'tipos_factura');
    }

    /**
     * Catálogo de los 28 documentos sector que publica el SIN. Es la lista
     * completa; los que puede emitir este NIT salen de {@see documentosSector()}.
     *
     * @return array<int, string> código => descripción
     */
    public function tiposDocumentoSector(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaTipoDocumentoSector', 'tipos_documento_sector');
    }

    /**
     * Tipos de documento de identidad del comprador (1 NIT, 2 CI...).
     *
     * @return array<int, string> código => descripción
     */
    public function tiposDocumentoIdentidad(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaTipoDocumentoIdentidad', 'tipos_doc_identidad');
    }

    /**
     * Métodos de pago admitidos. El 2 es tarjeta, y exige número de tarjeta en
     * la factura.
     *
     * @return array<int, string> código => descripción
     */
    public function tiposMetodoPago(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaTipoMetodoPago', 'tipos_metodo_pago');
    }

    /**
     * Monedas.
     *
     * @return array<int, string> código => descripción
     */
    public function tiposMoneda(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaTipoMoneda', 'tipos_moneda');
    }

    /**
     * Tipos de punto de venta. Los necesita `registroPuntoVenta` al dar de alta
     * el punto de venta 1 que exige la homologación.
     *
     * @return array<int, string> código => descripción
     */
    public function tiposPuntoVenta(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaTipoPuntoVenta', 'tipos_punto_venta');
    }

    /**
     * Países de origen.
     *
     * @return array<int, string> código => descripción
     */
    public function paisesOrigen(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaPaisOrigen', 'paises_origen');
    }

    /**
     * Tipos de habitación. Solo lo usa el sector hotelero; se sincroniza porque
     * la etapa II de la homologación lo exige igual.
     *
     * @return array<int, string> código => descripción
     */
    public function tiposHabitacion(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarParametricaTipoHabitacion', 'tipos_habitacion');
    }

    /**
     * Catálogo de mensajes de los servicios: el significado de cada código de
     * respuesta del SIN (908 validada, 915 tipo de factura inválido...).
     *
     * @return array<int, string> código => descripción
     */
    public function mensajesServicios(SiatSetting $setting): array
    {
        return $this->parametrica($setting, 'sincronizarListaMensajesServicios', 'mensajes_servicios');
    }

    /**
     * Hora oficial del SIN. Sirve para detectar un desfase del reloj local, que
     * invalida el CUF porque la fecha va dentro de su cálculo.
     */
    public function fechaHora(SiatSetting $setting): ?string
    {
        $respuesta = $this->llamar($setting, 'sincronizarFechaHora');

        return data_get($respuesta, 'fechaHora');
    }

    public function olvidarCache(SiatSetting $setting): void
    {
        foreach (array_keys(self::CATALOGOS) as $clave) {
            Cache::forget($this->clave($setting, $clave));
        }
    }

    /** @return array<int, string> */
    private function parametrica(SiatSetting $setting, string $operacion, string $clave): array
    {
        return $this->cacheado($setting, $clave, function () use ($setting, $operacion) {
            $lista = $this->lista($setting, $operacion, 'listaCodigos');

            return collect($lista)
                ->mapWithKeys(fn($c) => [(int) $c['codigoClasificador'] => (string) $c['descripcion']])
                ->all();
        });
    }

    /**
     * Todas las operaciones de sincronización comparten la misma solicitud.
     *
     * Protegido —y no privado— para que las pruebas puedan doblar la llamada SOAP
     * sin tocar la red: es la única costura del servicio.
     *
     * @return array<string, mixed>
     */
    protected function llamar(SiatSetting $setting, string $operacion): array
    {
        if (blank($setting->cuis)) {
            throw new SiatException('Las paramétricas del SIN requieren un CUIS vigente y esta tienda no lo tiene.');
        }

        $client = new SiatSoapClient($setting);

        return $client->call(
            self::SERVICIO,
            $operacion,
            $this->solicitud($setting, $client->codigoAmbiente()),
            envoltura: 'SolicitudSincronizacion',
        );
    }

    /**
     * La solicitud que comparten las 18 operaciones.
     *
     * `solicitudSincronizacion` del WSDL son exactamente estos seis campos: no
     * lleva `codigoModalidad`, a diferencia de la solicitud de CUFD.
     *
     * @return array<string, mixed>
     */
    protected function solicitud(SiatSetting $setting, int $codigoAmbiente): array
    {
        return [
            'codigoAmbiente'   => $codigoAmbiente,
            'codigoPuntoVenta' => (int) $setting->codigo_punto_venta,
            'codigoSistema'    => (string) $setting->codigo_sistema,
            'codigoSucursal'   => (int) $setting->codigo_sucursal,
            'cuis'             => $setting->cuis,
            'nit'              => (int) $setting->nit,
        ];
    }

    /**
     * Extrae la lista de la respuesta.
     *
     * `SiatSoapClient::normalize()` ya retira la envoltura "RespuestaX" cuando es
     * la única clave, así que la lista suele quedar en la raíz; se busca un nivel
     * más adentro por si alguna operación devuelve algo junto a ella. Una lista de
     * un solo elemento llega como objeto y no como array.
     *
     * @return list<array<string, mixed>>
     */
    private function lista(SiatSetting $setting, string $operacion, string $clave): array
    {
        $respuesta = $this->llamar($setting, $operacion);
        $lista     = $respuesta[$clave] ?? null;

        if ($lista === null) {
            foreach ($respuesta as $valor) {
                if (is_array($valor) && isset($valor[$clave])) {
                    $lista = $valor[$clave];
                    break;
                }
            }
        }

        if (blank($lista)) {
            return [];
        }

        return array_is_list($lista) ? $lista : [$lista];
    }

    private function cacheado(SiatSetting $setting, string $clave, \Closure $resolver): array
    {
        return Cache::remember(
            $this->clave($setting, $clave),
            now()->addHours((int) config('siat.cache_catalogos_horas')),
            $resolver,
        );
    }

    private function clave(SiatSetting $setting, string $clave): string
    {
        return "siat:{$setting->ambiente}:{$setting->store_id}:{$clave}";
    }
}
