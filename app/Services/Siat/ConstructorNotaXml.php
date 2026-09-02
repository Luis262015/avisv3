<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\SaleReturn;
use App\Models\SiatCufdCode;
use App\Models\SiatNota;
use App\Models\SiatSetting;
use Carbon\CarbonInterface;

/**
 * Traduce una devolución de venta al XML de la Nota de Crédito-Débito.
 *
 * {@see NotaCreditoDebitoXml} sabe de estructura y de XSD; esto sabe de dónde
 * sale cada dato. La diferencia con la factura es que la nota lleva **dos
 * detalles**: la foto de lo que se facturó (`codigoDetalleTransaccion` 1) y la de
 * lo que se devuelve (código 2). El SIN necesita las dos para calcular el ajuste.
 */
final class ConstructorNotaXml
{
    public function __construct(
        private readonly NotaCreditoDebitoXml $xml,
        private readonly ConstructorFacturaXml $constructorFactura,
    ) {}

    public function construir(
        SiatNota $nota,
        SiatSetting $setting,
        SiatCufdCode $cufd,
        CarbonInterface $fechaEmision,
        ?SaleReturn $devolucion = null,
    ): string {
        $devolucion ??= $nota->saleReturn;

        if ($devolucion === null) {
            throw new SiatException("La nota #{$nota->numero_nota} no tiene devolución asociada.");
        }

        $devolucion->loadMissing(['items.product', 'sale.items.product', 'user']);
        $nota->loadMissing('invoice');

        return $this->xml->build(
            nota: $nota,
            setting: $setting,
            cufd: $cufd,
            fechaEmision: $fechaEmision,
            detalles: $this->detalles($devolucion, $setting),
            leyenda: $this->constructorFactura->leyenda($setting),
            usuario: $devolucion->user?->name ?? 'sistema',
        );
    }

    /**
     * Las dos mitades del detalle, en orden: primero la venta original tal y como
     * se facturó, después lo que se devuelve.
     *
     * @return list<array<string, mixed>>
     */
    public function detalles(SaleReturn $devolucion, SiatSetting $setting): array
    {
        $venta = $devolucion->sale
            ?? throw new SiatException('La devolución no tiene venta asociada.');

        $originales = $this->constructorFactura->detalles($venta, $setting);

        foreach ($originales as $indice => $linea) {
            $originales[$indice]['codigoDetalleTransaccion'] = NotaCreditoDebitoXml::TRANSACCION_ORIGINAL;
        }

        return [...$originales, ...$this->devueltos($devolucion, $setting)];
    }

    /**
     * Una línea por producto devuelto. Reutiliza la homologación del producto:
     * si no está homologado, falla igual que al facturar y con el mismo aviso.
     *
     * @return list<array<string, mixed>>
     */
    private function devueltos(SaleReturn $devolucion, SiatSetting $setting): array
    {
        if ($devolucion->items->isEmpty()) {
            throw new SiatException('La devolución no tiene líneas: no hay nada que ajustar.');
        }

        return $devolucion->items->map(function ($item) use ($setting): array {
            $producto = $item->product;

            $codigoSin = $producto?->codigo_producto_sin
                ?? config('siat.factura.codigo_producto_sin_default');

            if (blank($codigoSin)) {
                throw new SiatException(
                    "El producto \"{$producto?->name}\" no tiene código de producto del SIN. "
                    . 'Homológuelo en Facturación SIAT → Homologación SIN antes de emitir la nota.'
                );
            }

            $cantidad = (float) $item->quantity;
            $precio   = (float) $item->unit_price;

            return [
                'actividadEconomica' => (string) $setting->actividad_economica,
                'codigoProductoSin'  => (int) $codigoSin,
                'codigoProducto'     => (string) ($producto?->sku ?: $producto?->id ?: 'SIN-SKU'),
                'descripcion'        => (string) ($producto?->name ?? 'Producto'),
                'cantidad'           => $cantidad,
                'unidadMedida'       => (int) ($producto?->unidad_medida_sin
                    ?? config('siat.factura.unidad_medida_default')),
                'precioUnitario'     => $precio,
                'montoDescuento'     => null,
                // El SIN recalcula el subtotal; se manda el de la devolución solo
                // si cuadra con cantidad × precio.
                'subTotal'           => round($cantidad * $precio, 2),
                'codigoDetalleTransaccion' => NotaCreditoDebitoXml::TRANSACCION_DEVUELTA,
            ];
        })->values()->all();
    }
}
