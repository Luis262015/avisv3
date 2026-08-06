<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\SiatSetting;
use App\Services\Siat\CufGenerator;
use App\Services\Siat\FacturaComputarizadaXml;
use App\Services\Siat\SiatCodigosService;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatFacturacionService;
use App\Services\Siat\SiatSincronizacionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SiatService
{
    public function __construct(
        private readonly SiatCodigosService $codigos,
        private readonly CufGenerator $cufGenerator,
        private readonly FacturaComputarizadaXml $xml,
        private readonly SiatFacturacionService $facturacion,
        private readonly SiatSincronizacionService $sincronizacion,
    ) {}

    // ─── Tipos de documento de identidad ────────────────────────────────────
    const DOC_CI         = 1;
    const DOC_PASAPORTE  = 2;
    const DOC_CARNET_EXT = 3;
    const DOC_OTRO       = 4;
    const DOC_NIT        = 5;

    // ─── Tipos de factura ────────────────────────────────────────────────────
    const FACTURA_CON_CF  = 1; // con derecho a crédito fiscal
    const FACTURA_SIN_CF  = 2; // sin derecho a crédito fiscal

    // ─── Tipos de emisión ────────────────────────────────────────────────────
    const EMISION_ONLINE  = 1;
    const EMISION_OFFLINE = 2;

    // ─── Motivos de anulación (paramétrica del SIN) ─────────────────────────
    const ANULACION_MAL_EMITIDA      = 1;
    const ANULACION_NOTA_MAL_EMITIDA = 2;
    const ANULACION_DATOS_INCORRECTOS = 3;
    const ANULACION_DEVUELTA         = 4;

    // ─── Métodos de pago ────────────────────────────────────────────────────
    const PAGO_EFECTIVO      = 1;
    const PAGO_TARJETA       = 2;
    const PAGO_TRANSFERENCIA = 3;
    const PAGO_QR            = 7;

    /**
     * Obtiene la configuración SIAT activa para una tienda.
     */
    public function getActiveSetting(int $storeId): ?SiatSetting
    {
        return SiatSetting::where('store_id', $storeId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Obtiene o genera un CUFD válido para la tienda.
     * En ambiente "simulado" genera uno local. En piloto/producción llama al SIN.
     */
    public function getOrCreateCufd(SiatSetting $setting): SiatCufdCode
    {
        // Buscar CUFD activo aún vigente
        $active = SiatCufdCode::where('store_id', $setting->store_id)
            ->where('estado', 'activo')
            ->where('fecha_vigencia', '>', now())
            ->latest()
            ->first();

        if ($active) {
            return $active;
        }

        // Vencer los anteriores
        SiatCufdCode::where('store_id', $setting->store_id)
            ->where('estado', 'activo')
            ->update(['estado' => 'vencido']);

        if ($setting->ambiente === 'simulado') {
            return $this->createSimulatedCufd($setting);
        }

        // Piloto y producción piden el CUFD real al SIN. Nunca se cae a un CUFD
        // simulado: eso produciría facturas con apariencia legal y sin respaldo.
        return $this->codigos->solicitarCufd($setting);
    }

    /**
     * Crea una factura SIAT para una venta.
     *
     * @param  array{nit_ci?: string, tipo_doc?: int, nombre?: string, tipo_factura?: int} $buyerData
     */
    public function createInvoice(Sale $sale, array $buyerData = []): SiatInvoice
    {
        $sale->loadMissing(['cashShift.cashRegister.store', 'items']);
        $store = $sale->cashShift->cashRegister->store;

        $setting = $this->getActiveSetting($store->id);
        if (! $setting) {
            throw new \RuntimeException('No hay configuración SIAT activa para esta tienda.');
        }

        $cufd = $this->getOrCreateCufd($setting);

        return DB::transaction(function () use ($sale, $store, $setting, $cufd, $buyerData) {
            $numero = $cufd->nextConsecutivo();

            $nit       = $buyerData['nit_ci']     ?? '0';
            $tipoDoc   = (int) ($buyerData['tipo_doc']    ?? self::DOC_NIT);
            $nombre    = $buyerData['nombre']      ?? 'Sin Nombre';
            $tipoFact  = (int) ($buyerData['tipo_factura'] ?? $setting->tipo_factura_default);

            // Si tiene NIT, forzar CF; si solo CI, sin CF
            if ($nit !== '0' && $nit !== '' && $tipoDoc === self::DOC_NIT) {
                $tipoFact = self::FACTURA_CON_CF;
            }

            // En hora de Bolivia, no UTC: esta fecha entra en el CUF y el SIN la
            // valida contra su propio reloj.
            $fechaEmision = Carbon::now(config('siat.timezone'));
            $total        = (float) $sale->total;
            $descuento    = (float) $sale->discount;
            $baseCf       = $total; // sin ICE, exentos, etc.

            $metodoPago = $this->mapPaymentMethod($sale->payment_method);

            // El CUF se firma con el código de control del CUFD vigente, no con el
            // CUFD mismo; y el tipo de emisión es independiente de la modalidad.
            $cuf = $this->cufGenerator->generate(
                nit: $setting->nit,
                fechaEmision: $fechaEmision,
                sucursal: (int) $setting->codigo_sucursal,
                modalidad: (int) $setting->modalidad,
                tipoEmision: CufGenerator::EMISION_ONLINE,
                tipoFactura: $tipoFact,
                tipoDocumentoSector: CufGenerator::SECTOR_COMPRA_VENTA,
                numeroFactura: $numero,
                puntoVenta: (int) $setting->codigo_punto_venta,
                codigoControl: $cufd->codigo_control,
            );

            $qr = $this->generateQrContent($setting->ambiente, $setting->nit, $cuf, $numero);

            $invoice = SiatInvoice::create([
                'sale_id'            => $sale->id,
                'store_id'           => $store->id,
                'cufd_code_id'       => $cufd->id,
                'numero_factura'     => $numero,
                // Con milisegundos: es la fecha que va dentro del CUF y el SIN
                // exige que coincida con la del XML al reenviar.
                'fecha_emision'      => $fechaEmision,
                'cuf'                => $cuf,
                'cufd'               => $cufd->codigo,
                'nit_ci'             => $nit,
                'tipo_doc_identidad' => $tipoDoc,
                'nombre_razon_social'=> $nombre,
                'importe_total'      => $total,
                'importe_base_cf'    => $baseCf,
                'descuento'          => $descuento,
                'tipo_factura'       => $tipoFact,
                // El tipo de emisión no es la modalidad: aquí siempre se emite en
                // línea. Guardar la modalidad dejaba "offline" toda factura
                // computarizada y además impedía el envío.
                'tipo_emision'       => self::EMISION_ONLINE,
                'metodo_pago'        => $metodoPago,
                'estado'             => 'pendiente',
                'codigo_qr'          => $qr,
            ]);

            if ($setting->ambiente !== 'simulado') {
                $this->sendInvoiceToSin($invoice, $setting, $cufd, $sale, $fechaEmision);
            }

            return $invoice;
        });
    }

    /**
     * Anula una factura, también ante el SIN.
     *
     * @param  int  $codigoMotivo  Del catálogo del SIN: 1 factura mal emitida,
     *                             2 nota de crédito-débito mal emitida,
     *                             3 datos de emisión incorrectos, 4 devuelta.
     */
    public function cancelInvoice(SiatInvoice $invoice, string $motivo, int $codigoMotivo = self::ANULACION_MAL_EMITIDA): void
    {
        if ($invoice->estado === 'anulada') {
            throw new \RuntimeException('La factura ya está anulada.');
        }

        $setting = $this->getActiveSetting($invoice->store_id);

        // Anular solo en local dejaría una factura viva ante Impuestos, así que si
        // el SIN rechaza la anulación se propaga el error y no se toca nada.
        if ($setting && $setting->ambiente !== 'simulado' && $invoice->estado === 'enviada') {
            $this->facturacion->anulacionFactura(
                $setting,
                $invoice->cuf,
                $invoice->cufd,
                $codigoMotivo,
                (int) $invoice->tipo_factura,
            );
        }

        $invoice->update([
            'estado'           => 'anulada',
            'anulado_at'       => now(),
            'motivo_anulacion' => $motivo,
        ]);
    }

    /**
     * Consulta al SIN en qué estado quedó una factura ya enviada.
     *
     * @return array<string, mixed>
     */
    public function checkInvoiceStatus(SiatInvoice $invoice): array
    {
        $setting = $this->getActiveSetting($invoice->store_id)
            ?? throw new SiatException('No hay configuración SIAT activa para esta tienda.');

        return $this->facturacion->verificacionEstadoFactura(
            $setting, $invoice->cuf, $invoice->cufd, (int) $invoice->tipo_factura,
        );
    }

    /**
     * Contenido del QR impreso en la representación gráfica.
     *
     * @see https://siatinfo.impuestos.gob.bo/index.php/facturacion-en-linea/algoritmos-utilizados/codigo-respuesta-rapida-qr
     *
     * @param  int  $tamanio  1 = rollo, 2 = media hoja
     */
    public function generateQrContent(string $ambiente, string $nit, string $cuf, int $numero, int $tamanio = 1): string
    {
        $baseUrl = config("siat.qr_base.{$ambiente}") ?? config('siat.qr_base.piloto');

        return $baseUrl . '?' . http_build_query([
            'nit'    => $nit,
            'cuf'    => $cuf,
            'numero' => $numero,
            't'      => $tamanio,
        ]);
    }

    /**
     * Genera un CUFD simulado (para ambiente "simulado", sin conexión SIN).
     */
    private function createSimulatedCufd(SiatSetting $setting): SiatCufdCode
    {
        $codigo = strtoupper(hash('sha256', uniqid($setting->nit, true)));
        $codigoControl = strtoupper(substr(hash('sha1', $codigo), 0, 8));

        return SiatCufdCode::create([
            'store_id'       => $setting->store_id,
            'codigo'         => $codigo,
            'codigo_control' => $codigoControl,
            'fecha_vigencia' => now()->addHours(24),
            'consecutivo'    => 0,
            'estado'         => 'activo',
        ]);
    }

    /**
     * Construye el XML de la factura, lo valida contra el XSD y lo envía al SIN.
     *
     * Un fallo aquí no tumba la venta: la factura queda "pendiente" con el motivo
     * registrado y se puede reenviar. Anular la venta por un problema de red del
     * SIN sería peor que emitir con retraso.
     */
    private function sendInvoiceToSin(
        SiatInvoice $invoice,
        SiatSetting $setting,
        SiatCufdCode $cufd,
        Sale $sale,
        Carbon $fechaEmision,
    ): void {
        try {
            $xml = $this->xml->build(
                invoice: $invoice,
                setting: $setting,
                cufd: $cufd,
                fechaEmision: $fechaEmision,
                detalles: $this->buildDetalles($sale, $setting),
                leyenda: $this->resolveLeyenda($setting),
                usuario: $sale->user?->name ?? 'sistema',
            );

            $resultado = $this->facturacion->recepcionFactura(
                $setting, $xml, $cufd->codigo, now(), (int) $invoice->tipo_factura,
            );

            $invoice->update([
                'estado'           => 'enviada',
                'codigo_recepcion' => $resultado['codigoRecepcion'],
                'enviado_at'       => now(),
                'mensaje_error'    => null,
            ]);
        } catch (SiatException $e) {
            Log::error('SIAT: no se pudo enviar la factura', [
                'invoice_id'     => $invoice->id,
                'numero_factura' => $invoice->numero_factura,
                'error'          => $e->getMessage(),
            ]);

            $invoice->update([
                'estado'        => 'pendiente',
                'mensaje_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reenvía al SIN una factura que quedó pendiente.
     *
     * @return array<string, mixed>
     */
    public function resendInvoice(SiatInvoice $invoice): array
    {
        $setting = $this->getActiveSetting($invoice->store_id)
            ?? throw new SiatException('No hay configuración SIAT activa para esta tienda.');

        $invoice->loadMissing(['cufdCode', 'sale.user', 'sale.items.product']);

        $xml = $this->xml->build(
            invoice: $invoice,
            setting: $setting,
            cufd: $invoice->cufdCode,
            fechaEmision: $invoice->fecha_emision ?? $invoice->created_at,
            detalles: $this->buildDetalles($invoice->sale, $setting),
            leyenda: $this->resolveLeyenda($setting),
            usuario: $invoice->sale->user?->name ?? 'sistema',
        );

        $resultado = $this->facturacion->recepcionFactura(
            $setting, $xml, $invoice->cufdCode->codigo, now(), (int) $invoice->tipo_factura,
        );

        $invoice->update([
            'estado'           => 'enviada',
            'codigo_recepcion' => $resultado['codigoRecepcion'],
            'enviado_at'       => now(),
            'mensaje_error'    => null,
        ]);

        return $resultado;
    }

    /**
     * Una línea de XML por cada ítem de la venta.
     *
     * @return list<array<string, mixed>>
     */
    private function buildDetalles(Sale $sale, SiatSetting $setting): array
    {
        $sale->loadMissing('items.product');

        return $sale->items->map(function ($item) use ($setting) {
            $producto = $item->product;

            $codigoSin = $producto?->codigo_producto_sin
                ?? config('siat.factura.codigo_producto_sin_default');

            if (blank($codigoSin)) {
                throw new SiatException(
                    "El producto \"{$producto?->name}\" no tiene código de producto del SIN. "
                    . 'Homológuelo con la paramétrica de Productos y Servicios antes de facturar.'
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
    private function resolveLeyenda(SiatSetting $setting): string
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

    /**
     * Mapea el método de pago de la venta al código SIAT.
     */
    private function mapPaymentMethod(string $method): int
    {
        return match ($method) {
            'cash'     => self::PAGO_EFECTIVO,
            'card'     => self::PAGO_TARJETA,
            'transfer' => self::PAGO_TRANSFERENCIA,
            'mixed'    => self::PAGO_EFECTIVO, // mixto → efectivo por defecto
            default    => self::PAGO_EFECTIVO,
        };
    }
}
