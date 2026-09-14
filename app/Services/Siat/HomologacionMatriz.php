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
     * Volumen que pide cada etapa, y en qué unidad lo pide.
     *
     * La página oficial usa dos redacciones distintas y significan cosas
     * distintas, cosa que costó una etapa mal dimensionada:
     *
     * - «son N pruebas **por cada caso**» → el número se multiplica por los casos
     *   de la matriz (etapas I, II, III, V, VI y IX).
     * - «debe realizar N emisiones / N anulaciones» → parecía ser el **total de
     *   la etapa** (IV, VII y VIII).
     *
     * **Y esa segunda lectura era falsa en la VII.** La página dice «debe
     * realizar 250 anulaciones» y el panel de Seguimiento pide **125 en cada uno
     * de sus 12 casos = 1500**. La redacción de la página no decide nada: lo que
     * se puntúa es la columna «Pruebas Esperadas» del panel, caso por caso.
     * Comprobado el 2026-09-10 sobre el caso del sector 1 por el punto 1, que el
     * panel daba en 42/125 (33 %) —los mismos 42 que tenía la matriz local, que
     * los creía 42 de 21 y por eso lo marcaba «completado»—.
     *
     * Confirmado con el panel de Seguimiento del Portal: la etapa II son 18
     * catálogos × 2 puntos de venta × 50 pruebas = **1800**.
     */
    public const POR_CASO = [
        1 => 2,    // CUIS
        2 => 50,   // Sincronización de catálogos
        3 => 100,  // CUFD
        5 => 5,    // Eventos significativos
        6 => 10,   // Paquetes
        7 => 125,  // Anulación y reversión — del panel, no de la página
        9 => 10,   // Emisión masiva
    ];

    /**
     * Etapas cuyo número es el total, no el de cada caso.
     *
     * Queda solo la IV, y **está sin contrastar con el panel**: es la otra que
     * usa la redacción «debe realizar N emisiones», la misma que resultó ser
     * falsa en la VII. Sus 12 casos figuran con 42 de 42 cada uno; si el panel
     * pidiera 125, estaría igual de corta.
     */
    public const TOTAL_ETAPA = [
        4 => 500,  // Emisión individual — sin confirmar contra el panel
        8 => 250,  // Firma digital — N/A en modalidad computarizada
    ];

    /** Los sectores de nota, que van por otro servicio y no tienen paquete ni masiva. */
    public const SECTORES_NOTA = [
        CufGenerator::SECTOR_NOTA_CRED_DEB,
        CufGenerator::SECTOR_NOTA_CRED_DEB_DESCUENTO,
    ];

    /**
     * El punto de venta con que el Excel de la etapa VI manda cada tamaño de
     * lote: «igual a 500» por el punto 1 y «menor a 500» por el 0.
     *
     * Va aquí y no suelto en {@see self::paquetes} porque es un dato del Excel,
     * no una decisión: la columna «cantidad de facturas» y la de «código punto
     * venta» se mueven juntas fila a fila.
     */
    public const PUNTO_POR_LOTE = [500 => 1, 250 => 0];

    /** Las etapas que este generador sabe ejecutar. */
    public const EJECUTABLES = [2, 3, 4, 5, 6, 7, 9];

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

        // Las etapas «por caso» no reparten: cada caso pide el número entero.
        $porCaso = isset(self::POR_CASO[$etapa])
            ? ($volumen ?? self::POR_CASO[$etapa])
            : (int) max(1, ceil(
                ($volumen ?? self::TOTAL_ETAPA[$etapa] ?? count($definiciones)) / count($definiciones)
            ));

        $casos = [];

        foreach ($definiciones as $definicion) {
            $caso = SiatHomologacionCaso::firstOrNew([
                'store_id' => $setting->store_id,
                'etapa'    => $etapa,
                'caso'     => $definicion['caso'],
            ]);

            $caso->fill($definicion);

            // El tamaño del lote lo fija el Excel (500 o 1000 facturas) y no tiene
            // que ver con cuántas pruebas pide el caso.
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
            3       => $this->cufd($setting),
            4, 7    => $this->porSectorYPunto($setting, $etapa),
            5       => $this->eventos($setting),
            6       => $this->paquetes($setting),
            9       => $this->masiva($setting),
            default => [],
        };
    }

    /**
     * Un caso por **cada catálogo y cada punto de venta**, que es como los cuenta
     * el Portal: 18 operaciones × 2 puntos = 36 casos, y 50 pruebas cada uno.
     */
    private function sincronizacion(SiatSetting $setting): array
    {
        $definiciones = [];

        foreach (self::CATALOGOS_ETAPA_II as $catalogo) {
            foreach ($this->puntosVenta($setting) as $pv) {
                $definiciones[] = [
                    'caso'        => "e2-{$catalogo}-pv{$pv}",
                    'catalogo'    => $catalogo,
                    'punto_venta' => $pv,
                ];
            }
        }

        return $definiciones;
    }

    /**
     * Las 18 operaciones del servicio de sincronización: los 17 catálogos más
     * `sincronizarFechaHora`, que no devuelve lista pero cuenta igual.
     *
     * @return list<string>
     */
    private const CATALOGOS_ETAPA_II = [
        'leyendas', 'actividades', 'documentos_sector', 'productos', 'unidades_medida',
        'motivos_anulacion', 'eventos_significativos', 'tipos_emision', 'tipos_factura',
        'tipos_documento_sector', 'tipos_doc_identidad', 'tipos_metodo_pago', 'tipos_moneda',
        'tipos_punto_venta', 'paises_origen', 'tipos_habitacion', 'mensajes_servicios',
        'fecha_hora',
    ];

    /**
     * Solicitud de CUFD, tal como la enumera `CasosDePruebaCUFD.xlsx`: **un caso
     * por punto de venta** —el Excel lista solo dos filas, la del punto 1 y la
     * del 0— con cien pruebas cada uno, o sea 200 en total.
     *
     * No se cruza con el documento sector, a diferencia de la emisión: el CUFD
     * es del punto de venta y sirve para cualquier documento que se emita bajo
     * él. Sus únicas variables son el CUIS y el punto, y el CUIS ya va atado al
     * punto.
     */
    private function cufd(SiatSetting $setting): array
    {
        return array_map(fn (int $pv) => [
            'caso'        => "e3-pv{$pv}",
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
                ];
            }
        }

        return $definiciones;
    }

    /**
     * Paquetes de contingencia, tal como los enumera `CasosDePruebaEmisionPor
     * Paquetes.xlsx`: **16 casos** por documento sector.
     *
     * Catorce envíos —cada motivo de evento con un lote de «igual a 500» y otro
     * de «menor a 500»— y **dos validaciones**, una por punto de venta. La
     * validación es un caso aparte porque puntúa otra cosa: el envío responde
     * 901 PENDIENTE y la consulta posterior 908 RECEPCION VALIDADA.
     *
     * **El tamaño del lote y el punto de venta son la misma columna, no dos
     * ejes.** El Excel manda «igual a 500» por el punto 1 y «menor a 500» por
     * el 0 —{@see self::PUNTO_POR_LOTE}—, así que los catorce envíos se reparten
     * siete y siete. Mandarlos todos por el punto 1, como se hacía, deja los
     * siete casos del punto 0 sin puntuar y le carga al 1 el doble de pruebas
     * de las que su fila pide. Ojo que es al revés que en la etapa IX, donde el
     * Excel sí cruza los dos tamaños con los dos puntos ({@see self::masiva}).
     *
     * Y se repite **por cada sector facturable**, no solo por la compra-venta:
     * el Excel trae 27 sectores con estos mismos 16 casos cada uno, y el panel
     * cuenta los que el SIN asocia al NIT. Las notas no están, coherente con
     * que no tengan servicio de paquete.
     */
    private function paquetes(SiatSetting $setting): array
    {
        $definiciones = [];
        $puntos       = $this->puntosVenta($setting);

        foreach ($this->sectoresConPaquete($setting) as $sector) {
            foreach (array_keys($this->motivosEvento($setting)) as $motivo) {
                foreach (self::PUNTO_POR_LOTE as $lote => $pv) {
                    $definiciones[] = [
                        'caso'             => "e6-s{$sector}-m{$motivo}-n{$lote}",
                        'punto_venta'      => in_array($pv, $puntos, true) ? $pv : $puntos[0],
                        'documento_sector' => $sector,
                        'tipo_factura'     => $this->tipoFactura($sector),
                        'motivo_evento'    => (int) $motivo,
                        'tamano_lote'      => $lote,
                        'operacion'        => 'envio',
                    ];
                }
            }

            foreach ($puntos as $pv) {
                $definiciones[] = [
                    'caso'             => "e6-s{$sector}-val-pv{$pv}",
                    'punto_venta'      => $pv,
                    'documento_sector' => $sector,
                    'tipo_factura'     => $this->tipoFactura($sector),
                    'operacion'        => 'validacion',
                ];
            }
        }

        return $definiciones;
    }

    /**
     * Emisión masiva, tal como la enumera `CasosDePruebaEmisionMasiva.xlsx`:
     * **8 casos** por documento sector.
     *
     * Cuatro envíos —«igual a 1000» y «menor a 1000», por cada punto de venta— y
     * la validación de cada uno. La página dice «hasta 2000», pero el Excel
     * puntúa 1000.
     *
     * Ocho **por cada sector facturable**: con los cuatro del NIT son 32 casos y
     * 320 pruebas, que es lo que muestra el panel.
     */
    private function masiva(SiatSetting $setting): array
    {
        $definiciones = [];

        foreach ($this->sectoresConPaquete($setting) as $sector) {
            foreach ($this->puntosVenta($setting) as $pv) {
                foreach ([1000, 500] as $lote) {
                    $comun = [
                        'punto_venta'      => $pv,
                        'documento_sector' => $sector,
                        'tipo_factura'     => $this->tipoFactura($sector),
                        'tamano_lote'      => $lote,
                    ];

                    $definiciones[] = $comun + [
                        'caso'      => "e9-s{$sector}-pv{$pv}-n{$lote}",
                        'operacion' => 'envio',
                    ];

                    $definiciones[] = $comun + [
                        'caso'      => "e9-s{$sector}-val-pv{$pv}-n{$lote}",
                        'operacion' => 'validacion',
                    ];
                }
            }
        }

        return $definiciones;
    }

    /**
     * Los documentos sector que el SIN asocia al **contribuyente**.
     *
     * No son los de la actividad de la tienda: son los de **todas** las
     * actividades del NIT. La página lo dice en las etapas IV, VI y IX —«para
     * todos los tipos de documento sector que estén asociados a su actividad
     * económica»— y el panel de Seguimiento los cuenta todos.
     *
     * Fijar el alcance en `siat_settings.actividad_economica` costó caro: ese
     * NIT tiene 21 actividades y entre todas suman **seis** sectores (1, 23, 24,
     * 34, 35 y 47), no los tres de la actividad de las tiendas. Con tres, la
     * etapa IX salía de 80 pruebas cuando el panel pide 320 —cuatro sectores
     * facturables por ocho casos por diez pruebas— y se daba por vencida con la
     * cuarta parte hecha.
     *
     * @return list<int>
     */
    public function sectores(SiatSetting $setting): array
    {
        $sectores = [];

        foreach (array_keys($this->sincronizacion->actividades($setting)) as $actividad) {
            foreach ($this->sincronizacion->documentosSectorDe($setting, (string) $actividad) as $sector => $nombre) {
                $sectores[(int) $sector] = true;
            }
        }

        if ($sectores === []) {
            throw new SiatException(
                "El SIN no asocia ningún documento sector a las actividades del NIT {$setting->nit}. "
                . 'Suele significar que el catálogo de actividades no se ha sincronizado todavía.'
            );
        }

        $sectores = array_keys($sectores);
        sort($sectores);

        return $sectores;
    }

    /**
     * Los sectores que se pueden enviar por paquete y por lote masivo.
     *
     * Las notas de crédito-débito quedan fuera: las emite
     * `ServicioFacturacionDocumentoAjuste`, que **no tiene** `recepcionPaquete`
     * ni `recepcionMasiva`. Por eso la etapa IX son cuatro sectores y no seis,
     * que es justo lo que hace cuadrar sus 320 pruebas.
     *
     * @return list<int>
     */
    public function sectoresConPaquete(SiatSetting $setting): array
    {
        return array_values(array_filter(
            $this->sectores($setting),
            fn (int $sector) => ! in_array($sector, self::SECTORES_NOTA, true),
        ));
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
        return in_array($sector, self::SECTORES_NOTA, true)
            ? (int) config('siat.nota.tipo_factura')
            : CufGenerator::FACTURA_CON_CREDITO_FISCAL;
    }
}
