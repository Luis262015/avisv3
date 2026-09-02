<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\SiatHomologacionCaso;
use App\Models\SiatPuntoVenta;
use App\Models\SiatSetting;

/**
 * La matriz de casos de la homologación Fase I para un contribuyente concreto.
 *
 * No es una lista fija: sale de cruzar tres cosas —los documentos sector que el
 * SIN asocia a la actividad, los puntos de venta dados de alta y los motivos de
 * evento de la paramétrica— porque el alcance real depende de las tres y no se
 * puede suponer.
 *
 * @see https://siatinfo.impuestos.gob.bo/index.php/724-fase-i
 */
final class HomologacionMatriz
{
    /**
     * Volumen que pide cada etapa, según la página oficial.
     *
     * Es el total de la etapa, no el de cada caso: se reparte entre los casos que
     * salgan de la matriz. La página es ambigua —en la etapa I cuadra como total
     * (2 pruebas, 2 casos) pero en la V no (5 pruebas, 14 casos)—, así que el
     * número que manda es el del panel de Seguimiento del Portal SIAT y por eso
     * se puede sobrescribir al ejecutar.
     */
    public const VOLUMEN = [
        1 => 2,    // CUIS
        2 => 50,   // Sincronización de catálogos
        3 => 100,  // CUFD
        4 => 500,  // Emisión individual
        5 => 5,    // Eventos significativos
        6 => 10,   // Paquetes
        7 => 250,  // Anulación y reversión
        8 => 250,  // Firma digital — N/A en modalidad computarizada
        9 => 10,   // Emisión masiva
    ];

    /** Las etapas que este generador sabe ejecutar. */
    public const EJECUTABLES = [2, 4, 5, 6, 7, 9];

    public function __construct(private readonly SiatSincronizacionService $sincronizacion) {}

    /**
     * Construye —o actualiza— las filas de una etapa y las devuelve.
     *
     * @param  int|null  $volumen  Total de la etapa; por defecto el oficial.
     * @return list<SiatHomologacionCaso>
     */
    public function generar(SiatSetting $setting, int $etapa, ?int $volumen = null): array
    {
        $definiciones = $this->definiciones($setting, $etapa);

        if ($definiciones === []) {
            return [];
        }

        $total = $volumen ?? self::VOLUMEN[$etapa] ?? count($definiciones);
        $porCaso = (int) max(1, ceil($total / count($definiciones)));

        $casos = [];

        foreach ($definiciones as $definicion) {
            $caso = SiatHomologacionCaso::firstOrNew([
                'store_id' => $setting->store_id,
                'etapa'    => $etapa,
                'caso'     => $definicion['caso'],
            ]);

            $caso->fill($definicion);

            // La cantidad de un lote la fija el Excel (500 o 1000 facturas), no el
            // reparto del total de la etapa.
            $caso->cantidad ??= $porCaso;

            if (! isset($definicion['cantidad'])) {
                $caso->cantidad = $porCaso;
            }

            $caso->completados ??= 0;
            $caso->estado ??= 'pendiente';
            $caso->save();

            $casos[] = $caso;
        }

        return $casos;
    }

    /**
     * Los casos de una etapa, antes de repartirles volumen.
     *
     * @return list<array<string, mixed>>
     */
    private function definiciones(SiatSetting $setting, int $etapa): array
    {
        return match ($etapa) {
            2       => $this->sincronizacion($setting),
            4, 7    => $this->porSectorYPunto($setting, $etapa),
            5       => $this->eventos($setting),
            6       => $this->paquetes($setting),
            9       => $this->masiva($setting),
            default => [],
        };
    }

    /** Un caso por punto de venta: la etapa consume los 17 catálogos de golpe. */
    private function sincronizacion(SiatSetting $setting): array
    {
        return array_map(fn (int $pv): array => [
            'caso'        => "e2-pv{$pv}",
            'punto_venta' => $pv,
        ], $this->puntosVenta($setting));
    }

    /**
     * Emisión individual y anulación: cada documento sector de la actividad, por
     * cada punto de venta.
     */
    private function porSectorYPunto(SiatSetting $setting, int $etapa): array
    {
        $definiciones = [];

        foreach ($this->sectores($setting) as $sector) {
            foreach ($this->puntosVenta($setting) as $pv) {
                $definiciones[] = [
                    'caso'             => "e{$etapa}-s{$sector}-pv{$pv}",
                    'punto_venta'      => $pv,
                    'documento_sector' => $sector,
                    'tipo_factura'     => $this->tipoFactura($sector),
                ];
            }
        }

        return $definiciones;
    }

    /** Los siete motivos de la paramétrica, por punto de venta. */
    private function eventos(SiatSetting $setting): array
    {
        $definiciones = [];

        foreach (array_keys($this->motivosEvento($setting)) as $motivo) {
            foreach ($this->puntosVenta($setting) as $pv) {
                $definiciones[] = [
                    'caso'          => "e5-m{$motivo}-pv{$pv}",
                    'punto_venta'   => $pv,
                    'motivo_evento' => (int) $motivo,
                    'cantidad'      => 1,
                ];
            }
        }

        return $definiciones;
    }

    /**
     * Paquetes de contingencia: el Excel pide un lote de **exactamente 500** y
     * otro de menos, por cada motivo de evento y punto de venta. Solo aplica a la
     * factura de compra-venta: las notas no tienen envío por paquete.
     */
    private function paquetes(SiatSetting $setting): array
    {
        $definiciones = [];

        foreach (array_keys($this->motivosEvento($setting)) as $motivo) {
            foreach ($this->puntosVenta($setting) as $indice => $pv) {
                // El Excel alterna: un punto lleva el lote completo y el otro uno
                // parcial.
                $cantidad = $indice === 0 ? 500 : 250;

                $definiciones[] = [
                    'caso'             => "e6-m{$motivo}-pv{$pv}",
                    'punto_venta'      => $pv,
                    'documento_sector' => CufGenerator::SECTOR_COMPRA_VENTA,
                    'tipo_factura'     => 1,
                    'motivo_evento'    => (int) $motivo,
                    'cantidad'         => $cantidad,
                ];
            }
        }

        return $definiciones;
    }

    /**
     * Emisión masiva: un lote de **exactamente 1000** y otro de menos, por punto
     * de venta. La página dice «hasta 2000», pero el Excel puntúa 1000.
     */
    private function masiva(SiatSetting $setting): array
    {
        $definiciones = [];

        foreach ($this->puntosVenta($setting) as $pv) {
            foreach ([1000, 500] as $cantidad) {
                $definiciones[] = [
                    'caso'             => "e9-pv{$pv}-n{$cantidad}",
                    'punto_venta'      => $pv,
                    'documento_sector' => CufGenerator::SECTOR_COMPRA_VENTA,
                    'tipo_factura'     => 1,
                    'cantidad'         => $cantidad,
                ];
            }
        }

        return $definiciones;
    }

    /**
     * Los documentos sector habilitados para la actividad del contribuyente.
     *
     * @return list<int>
     */
    public function sectores(SiatSetting $setting): array
    {
        $sectores = array_keys(
            $this->sincronizacion->documentosSectorDe($setting, (string) $setting->actividad_economica)
        );

        if ($sectores === []) {
            throw new SiatException(
                "El SIN no asocia ningún documento sector a la actividad {$setting->actividad_economica}. "
                . 'Suele significar que esa actividad no corresponde a este NIT.'
            );
        }

        sort($sectores);

        return $sectores;
    }

    /** @return list<int> */
    public function puntosVenta(SiatSetting $setting): array
    {
        $puntos = SiatPuntoVenta::where('store_id', $setting->store_id)
            ->where('codigo_sucursal', (int) $setting->codigo_sucursal)
            ->activos()
            ->whereNotNull('cuis')
            ->orderBy('codigo')
            ->pluck('codigo')
            ->all();

        if ($puntos === []) {
            throw new SiatException(
                'No hay ningún punto de venta con CUIS. La homologación repite cada caso con el punto '
                . 'de venta 0 y el 1: déles de alta en Facturación SIAT → Puntos de venta.'
            );
        }

        return array_map('intval', $puntos);
    }

    /** @return array<int, string> */
    private function motivosEvento(SiatSetting $setting): array
    {
        return $this->sincronizacion->eventosSignificativos($setting);
    }

    /**
     * El tipo de factura que el Excel asocia a cada sector: 1 con crédito fiscal
     * para la compra-venta, 3 documento de ajuste para las notas.
     */
    private function tipoFactura(int $sector): int
    {
        return in_array($sector, [24, 47], true)
            ? (int) config('siat.nota.tipo_factura')
            : CufGenerator::FACTURA_CON_CREDITO_FISCAL;
    }
}
