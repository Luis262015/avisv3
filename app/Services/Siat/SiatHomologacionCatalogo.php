<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\SiatSetting;

/**
 * Catálogos del SIN necesarios para homologar un producto: la lista de Productos
 * y Servicios de la actividad económica de la tienda y las unidades de medida.
 *
 * Vive aparte de {@see SiatSincronizacionService} porque la pantalla de
 * homologación necesita algo que aquel no da: no reventar cuando el SIN no
 * contesta. Sin CUIS, sin token o con el servicio caído la pantalla tiene que
 * seguir abriéndose y explicar por qué no hay catálogo, en vez de devolver un 500.
 */
final class SiatHomologacionCatalogo
{
    public function __construct(private readonly SiatSincronizacionService $sincronizacion) {}

    /**
     * @return array{
     *     productos: list<array{codigo: int, descripcion: string}>,
     *     unidades: list<array{codigo: int, descripcion: string}>,
     *     error: ?string
     * }
     */
    public function para(?SiatSetting $setting): array
    {
        if ($setting === null) {
            return $this->vacio(
                'No hay ninguna configuración SIAT activa. Cree una en Configuración SIAT '
                . 'antes de homologar productos.'
            );
        }

        if ($setting->ambiente === 'simulado') {
            return $this->vacio(
                'El ambiente simulado no consulta las paramétricas del SIN, así que no hay catálogo '
                . 'que ofrecer. Los códigos se pueden escribir a mano, pero no se validan.'
            );
        }

        try {
            $productos = $this->productosDeLaActividad($setting);
            $unidades  = $this->unidades($setting);
        } catch (SiatException $e) {
            return $this->vacio($e->getMessage());
        }

        // Una actividad sin productos homologados casi siempre significa que la
        // actividad configurada no es una de las registradas para este NIT: el
        // mismo síntoma que ya delata la falta de leyendas al emitir.
        if ($productos === []) {
            return [
                'productos' => [],
                'unidades'  => $unidades,
                'error'     => sprintf(
                    'La actividad económica "%s" no tiene productos homologados en el SIN. '
                    . 'Suele significar que esa actividad no corresponde a este NIT: revísela en la configuración.',
                    $setting->actividad_economica,
                ),
            ];
        }

        return ['productos' => $productos, 'unidades' => $unidades, 'error' => null];
    }

    /**
     * Códigos de producto admitidos, para validar lo que llega del formulario.
     *
     * @return list<int>
     */
    public function codigosValidos(?SiatSetting $setting): array
    {
        return array_column($this->para($setting)['productos'], 'codigo');
    }

    /** @return list<int> */
    public function unidadesValidas(?SiatSetting $setting): array
    {
        return array_column($this->para($setting)['unidades'], 'codigo');
    }

    /**
     * El detalle de la factura declara una sola actividad económica —la de la
     * configuración—, así que ofrecer productos de otra actividad solo llevaría a
     * facturas rechazadas.
     *
     * @return list<array{codigo: int, descripcion: string}>
     */
    private function productosDeLaActividad(SiatSetting $setting): array
    {
        $actividad = (string) $setting->actividad_economica;

        $productos = array_values(array_filter(
            $this->sincronizacion->productosServicios($setting),
            fn (array $p): bool => $p['actividad'] === $actividad,
        ));

        usort($productos, fn (array $a, array $b): int => strcmp($a['descripcion'], $b['descripcion']));

        return array_map(fn (array $p): array => [
            'codigo'      => $p['codigo'],
            'descripcion' => $p['descripcion'],
        ], $productos);
    }

    /** @return list<array{codigo: int, descripcion: string}> */
    private function unidades(SiatSetting $setting): array
    {
        $unidades = $this->sincronizacion->unidadesMedida($setting);

        asort($unidades);

        return array_map(
            fn (int $codigo, string $descripcion): array => ['codigo' => $codigo, 'descripcion' => $descripcion],
            array_keys($unidades),
            array_values($unidades),
        );
    }

    /**
     * @return array{productos: list<array{codigo: int, descripcion: string}>, unidades: list<array{codigo: int, descripcion: string}>, error: string}
     */
    private function vacio(string $error): array
    {
        return ['productos' => [], 'unidades' => [], 'error' => $error];
    }
}
