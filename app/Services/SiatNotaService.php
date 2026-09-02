<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SaleReturn;
use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\SiatNota;
use App\Models\SiatSetting;
use App\Services\Siat\ConstructorNotaXml;
use App\Services\Siat\CufGenerator;
use App\Services\Siat\SiatDocumentoAjusteService;
use App\Services\Siat\SiatException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ciclo de vida de la Nota de Crédito-Débito.
 *
 * Es el equivalente de {@see SiatService} para los documentos sector 24 y 47:
 * emite, anula, revierte y consulta. La nota nace siempre de una devolución de
 * venta cuya factura ya esté validada por el SIN — sin factura original no hay
 * nada que ajustar, y el SIN la rechaza.
 */
class SiatNotaService
{
    public function __construct(
        private readonly SiatService $siat,
        private readonly CufGenerator $cufGenerator,
        private readonly ConstructorNotaXml $constructor,
        private readonly SiatDocumentoAjusteService $ajuste,
    ) {}

    /**
     * Emite la nota que corresponde a una devolución.
     *
     * El correlativo se consume dentro de la transacción, igual que en la
     * factura: el número entra en el CUF, así que un rechazo lo quema. Por eso
     * todo lo comprobable —factura validada, importes, homologación— se verifica
     * antes de pedirlo.
     *
     * @param  int|null  $documentoSector  24 o 47; por defecto se deduce del descuento de la factura.
     */
    public function emitir(SaleReturn $devolucion, ?int $documentoSector = null): SiatNota
    {
        $devolucion->loadMissing(['sale.siatInvoice', 'sale.items.product', 'items.product', 'user']);

        $factura = $this->facturaOriginal($devolucion);
        $setting = $this->siat->getActiveSetting((int) $factura->store_id)
            ?? throw new SiatException('No hay configuración SIAT activa para la tienda de esta factura.');

        if (SiatNota::where('sale_return_id', $devolucion->id)->whereNot('estado', 'anulada')->exists()) {
            throw new SiatException("La devolución {$devolucion->folio} ya tiene una nota emitida.");
        }

        $documentoSector ??= $this->sectorPara($factura);
        $montos = $this->montos($devolucion, $factura);

        $cufd = $this->siat->getOrCreateCufd($setting);

        return DB::transaction(function () use ($devolucion, $factura, $setting, $cufd, $documentoSector, $montos) {
            $numero       = SiatNota::siguienteNumero((int) $factura->store_id, $documentoSector);
            $fechaEmision = Carbon::now(config('siat.timezone'));

            $cuf = $this->cufGenerator->generate(
                nit: $setting->nit,
                fechaEmision: $fechaEmision,
                sucursal: (int) $setting->codigo_sucursal,
                modalidad: (int) $setting->modalidad,
                // La nota no tiene paquete ni masiva: siempre en línea.
                tipoEmision: CufGenerator::EMISION_ONLINE,
                tipoFactura: (int) config('siat.nota.tipo_factura'),
                tipoDocumentoSector: $documentoSector,
                numeroFactura: $numero,
                puntoVenta: (int) $setting->codigo_punto_venta,
                codigoControl: $cufd->codigo_control,
            );

            $nota = SiatNota::create([
                'store_id'        => $factura->store_id,
                'sale_return_id'  => $devolucion->id,
                'siat_invoice_id' => $factura->id,
                'cufd_code_id'    => $cufd->id,
                'documento_sector' => $documentoSector,
                'numero_nota'     => $numero,
                'fecha_emision'   => $fechaEmision,
                'cuf'             => $cuf,
                'cufd'            => $cufd->codigo,
                // Los datos del comprador se copian de la factura: el SIN exige
                // que coincidan con los del documento que se ajusta.
                'nit_ci'              => $factura->nit_ci,
                'tipo_doc_identidad'  => $factura->tipo_doc_identidad,
                'codigo_excepcion'    => $factura->codigo_excepcion,
                'nombre_razon_social' => $factura->nombre_razon_social,
                'complemento'         => $factura->complemento,
                'monto_total_original' => $montos['original'],
                'monto_total_devuelto' => $montos['devuelto'],
                'monto_descuento'      => $montos['descuento'],
                'monto_efectivo'       => $montos['efectivo'],
                'descuento_adicional'  => $montos['descuento_adicional'],
                'estado'          => 'pendiente',
                'codigo_qr'       => $this->siat->generateQrContent(
                    $setting->ambiente, $setting->nit, $cuf, $numero,
                ),
            ]);

            $this->enviar($nota, $setting, $cufd, $devolucion);

            return $nota->refresh();
        });
    }

    /**
     * Reenvía una nota que quedó pendiente o rechazada.
     *
     * @return array<string, mixed>
     */
    public function reenviar(SiatNota $nota): array
    {
        $setting = $this->settingDe($nota);

        $nota->loadMissing(['cufdCode', 'invoice', 'saleReturn.items.product', 'saleReturn.sale.items.product']);

        $xml = $this->constructor->construir(
            $nota,
            $setting,
            $nota->cufdCode,
            $nota->fecha_emision ?? $nota->created_at,
        );

        $resultado = $this->ajuste->recepcionDocumentoAjuste(
            $setting, $xml, $nota->cufdCode->codigo, now(), (int) $nota->documento_sector,
        );

        $nota->update([
            'estado'           => 'enviada',
            'codigo_recepcion' => $resultado['codigoRecepcion'],
            'enviado_at'       => now(),
            'mensaje_error'    => null,
        ]);

        return $resultado;
    }

    /**
     * Anula una nota ya recibida por el SIN.
     *
     * @param  int  $codigoMotivo  Del catálogo `sincronizarParametricaMotivoAnulacion`.
     */
    public function anular(SiatNota $nota, int $codigoMotivo = SiatService::ANULACION_NOTA_MAL_EMITIDA): array
    {
        $setting = $this->settingDe($nota);

        if ($nota->estado === 'anulada') {
            throw new SiatException("La nota #{$nota->numero_nota} ya está anulada.");
        }

        if (blank($nota->codigo_recepcion)) {
            throw new SiatException(
                "La nota #{$nota->numero_nota} no llegó a enviarse al SIN, así que no hay nada que anular."
            );
        }

        $resultado = $this->ajuste->anulacionDocumentoAjuste(
            $setting, $nota->cuf, $nota->cufd, $codigoMotivo, (int) $nota->documento_sector,
        );

        $nota->update([
            'estado'           => 'anulada',
            'anulado_at'       => now(),
            'motivo_anulacion' => $codigoMotivo,
        ]);

        return $resultado;
    }

    /** Deshace la anulación: la nota vuelve a quedar vigente. */
    public function revertirAnulacion(SiatNota $nota): array
    {
        $setting = $this->settingDe($nota);

        if ($nota->estado !== 'anulada') {
            throw new SiatException("La nota #{$nota->numero_nota} no está anulada.");
        }

        $resultado = $this->ajuste->reversionAnulacionDocumentoAjuste(
            $setting, $nota->cuf, $nota->cufd, (int) $nota->documento_sector,
        );

        $nota->update([
            'estado'           => 'validada',
            'anulado_at'       => null,
            'motivo_anulacion' => null,
        ]);

        return $resultado;
    }

    /** @return array<string, mixed> */
    public function consultarEstado(SiatNota $nota): array
    {
        return $this->ajuste->verificacionEstadoDocumentoAjuste(
            $this->settingDe($nota), $nota->cuf, $nota->cufd, (int) $nota->documento_sector,
        );
    }

    /**
     * La factura que se ajusta, con las dos condiciones que impone el SIN: que
     * exista y que esté vigente.
     */
    private function facturaOriginal(SaleReturn $devolucion): SiatInvoice
    {
        $factura = $devolucion->sale?->siatInvoice
            ?? throw new SiatException(
                "La venta de la devolución {$devolucion->folio} no tiene factura del SIN. "
                . 'Una nota de crédito-débito solo ajusta una factura ya emitida.'
            );

        if (! in_array($factura->estado, ['enviada', 'validada'], true)) {
            throw new SiatException(
                "La factura #{$factura->numero_factura} está en estado «{$factura->estado}»: "
                . 'solo se puede ajustar una factura vigente en el SIN.'
            );
        }

        return $factura;
    }

    /**
     * Qué documento sector corresponde.
     *
     * El 47 existe para las facturas que llevaron descuento adicional, porque su
     * XML tiene el campo donde declararlo; sin descuento el 24 es el que toca.
     */
    private function sectorPara(SiatInvoice $factura): int
    {
        return (float) $factura->descuento > 0
            ? SiatNota::SECTOR_NOTA_DESCUENTO
            : SiatNota::SECTOR_NOTA;
    }

    /**
     * Los importes de la cabecera.
     *
     * `montoEfectivoCreditoDebito` **no es el efectivo devuelto al cliente**: la
     * documentación del SIN lo define como el 13 % del monto devuelto, es decir el
     * crédito fiscal que se revierte.
     *
     * @return array{original: float, devuelto: float, descuento: float, efectivo: float, descuento_adicional: ?float}
     */
    private function montos(SaleReturn $devolucion, SiatInvoice $factura): array
    {
        $original = round((float) $factura->importe_base_cf, 2);
        $devuelto = round((float) $devolucion->items->sum(
            fn ($item) => (float) $item->quantity * (float) $item->unit_price
        ), 2);

        if ($devuelto <= 0) {
            throw new SiatException('El importe devuelto tiene que ser mayor que cero.');
        }

        if ($devuelto > $original) {
            throw new SiatException(
                "La devolución suma {$devuelto} Bs y la factura original {$original} Bs: "
                . 'no se puede ajustar más de lo facturado.'
            );
        }

        $descuentoFactura = (float) $factura->descuento;

        return [
            'original'  => $original,
            'devuelto'  => $devuelto,
            // Prorrateado: la parte del descuento de la factura que corresponde a
            // lo devuelto.
            'descuento' => $original > 0
                ? round($descuentoFactura * ($devuelto / $original), 2)
                : 0.0,
            'efectivo'  => round($devuelto * (float) config('siat.nota.alicuota_iva'), 2),
            'descuento_adicional' => $descuentoFactura > 0 ? round($descuentoFactura, 2) : null,
        ];
    }

    /**
     * Construye el XML y lo manda. Un fallo de red no deshace la nota: queda
     * "pendiente" con el motivo y se reenvía, igual que la factura.
     */
    private function enviar(
        SiatNota $nota,
        SiatSetting $setting,
        SiatCufdCode $cufd,
        SaleReturn $devolucion,
    ): void {
        try {
            $xml = $this->constructor->construir(
                $nota, $setting, $cufd, $nota->fecha_emision, $devolucion,
            );

            $resultado = $this->ajuste->recepcionDocumentoAjuste(
                $setting, $xml, $cufd->codigo, now(), (int) $nota->documento_sector,
            );

            $nota->update([
                'estado'           => 'enviada',
                'codigo_recepcion' => $resultado['codigoRecepcion'],
                'enviado_at'       => now(),
                'mensaje_error'    => null,
            ]);
        } catch (SiatException $e) {
            Log::error('SIAT: no se pudo enviar la nota de crédito-débito', [
                'nota'  => $nota->id,
                'cuf'   => $nota->cuf,
                'error' => $e->getMessage(),
            ]);

            $nota->update(['estado' => 'rechazada', 'mensaje_error' => $e->getMessage()]);
        }
    }

    private function settingDe(SiatNota $nota): SiatSetting
    {
        return $this->siat->getActiveSetting((int) $nota->store_id)
            ?? throw new SiatException('No hay configuración SIAT activa para esta tienda.');
    }
}
