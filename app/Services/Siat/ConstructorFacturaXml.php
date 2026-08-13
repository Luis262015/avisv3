<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\Sale;
use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\SiatSetting;
use Carbon\CarbonInterface;

/**
 * Traduce una factura del dominio propio al XML que espera el SIN.
 *
 * {@see FacturaComputarizadaXml} sabe de estructura y de XSD; esto sabe de dónde
 * sale cada dato: qué código homologado lleva cada línea, qué leyenda corresponde
 * a la actividad y si hay que declarar un CAFC. Lo usan por igual la emisión en
 * línea, el reenvío y el empaquetado de contingencia, que antes habrían tenido
 * que duplicarlo.
 */
final class ConstructorFacturaXml
{
    public function __construct(
        private readonly FacturaComputarizadaXml $xml,
        private readonly SiatSincronizacionService $sincronizacion,
    ) {}

    public function construir(
        SiatInvoice $invoice,
        SiatSetting $setting,
        SiatCufdCode $cufd,
        CarbonInterface $fechaEmision,
        ?Sale $sale = null,
    ): string {
        $sale ??= $invoice->sale;

        if ($sale === null) {
            throw new SiatException("La factura #{$invoice->numero_factura} no tiene venta asociada.");
        }

        $sale->loadMissing(['items.product', 'user']);

        return $this->xml->build(
            invoice: $invoice,
            setting: $setting,
            cufd: $cufd,
            fechaEmision: $fechaEmision,
            detalles: $this->detalles($sale, $setting),
            leyenda: $this->leyenda($setting),
            usuario: $sale->user?->name ?? 'sistema',
            cafc: $invoice->cafc,
        );
    }

    /**
     * Una línea de XML por cada ítem de la venta.
     *
     * @return list<array<string, mixed>>
     */
    public function detalles(Sale $sale, SiatSetting $setting): array
    {
        $sale->loadMissing('items.product');

        return $sale->items->map(function ($item) use ($setting): array {
            $producto = $item->product;

            $codigoSin = $producto?->codigo_producto_sin
                ?? config('siat.factura.codigo_producto_sin_default');

            if (blank($codigoSin)) {
                throw new SiatException(
                    "El producto \"{$producto?->name}\" no tiene código de producto del SIN. "
                    . 'Homológuelo en Facturación SIAT → Homologación SIN antes de facturar.'
                );
            }

            $descuento = (float) $item->discount;

            return [
                'actividadEconomica' => (string) $setting->actividad_economica,
                'codigoProductoSin'  => (int) $codigoSin,
                'codigoProducto'     => (string) ($producto?->sku ?: $producto?->id ?: 'SIN-SKU'),
                'descripcion'        => (string) ($producto?->name ?? 'Producto'),
                'cantidad'           => (float) $item->quantity,
                'unidadMedida'       => (int) ($producto?->unidad_medida_sin
                    ?? config('siat.factura.unidad_medida_default')),
                'precioUnitario'     => (float) $item->price,
                'montoDescuento'     => $descuento,
                // El SIN recalcula el subtotal y rechaza la factura si no cuadra.
                'subTotal'           => round((float) $item->quantity * (float) $item->price - $descuento, 2),
            ];
        })->values()->all();
    }

    /**
     * La leyenda es obligatoria y depende de la actividad económica. Se usa la
     * guardada en la configuración; si no hay, se pide al SIN y se conserva.
     */
    public function leyenda(SiatSetting $setting): string
    {
        if (filled($setting->leyenda)) {
            return $setting->leyenda;
        }

        $leyenda = $this->sincronizacion->leyendaPara($setting, (string) $setting->actividad_economica);

        if (blank($leyenda)) {
            throw new SiatException(
                "La actividad económica \"{$setting->actividad_economica}\" no tiene leyendas registradas en el SIN. "
                . 'Suele significar que esa actividad no corresponde a este NIT: revísela en la configuración.'
            );
        }

        $setting->update(['leyenda' => $leyenda]);

        return $leyenda;
    }
}
