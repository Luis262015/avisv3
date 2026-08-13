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
        $claves = [
            'leyendas', 'actividades', 'productos', 'unidades_medida',
            'motivos_anulacion', 'eventos_significativos', 'tipos_emision',
        ];

        foreach ($claves as $clave) {
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
     * @return array<string, mixed>
     */
    private function llamar(SiatSetting $setting, string $operacion): array
    {
        if (blank($setting->cuis)) {
            throw new SiatException('Las paramétricas del SIN requieren un CUIS vigente y esta tienda no lo tiene.');
        }

        $client = new SiatSoapClient($setting);

        return $client->call(self::SERVICIO, $operacion, [
            'codigoAmbiente'   => $client->codigoAmbiente(),
            'codigoModalidad'  => (int) $setting->modalidad,
            'codigoPuntoVenta' => (int) $setting->codigo_punto_venta,
            'codigoSistema'    => (string) $setting->codigo_sistema,
            'codigoSucursal'   => (int) $setting->codigo_sucursal,
            'cuis'             => $setting->cuis,
            'nit'              => (int) $setting->nit,
        ], envoltura: 'SolicitudSincronizacion');
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
