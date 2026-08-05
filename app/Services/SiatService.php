<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\SiatSetting;
use App\Services\Siat\CufGenerator;
use App\Services\Siat\SiatCodigosService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SiatService
{
    public function __construct(
        private readonly SiatCodigosService $codigos,
        private readonly CufGenerator $cufGenerator,
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

            $fechaEmision = Carbon::now();
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
                'cuf'                => $cuf,
                'cufd'               => $cufd->codigo,
                'nit_ci'             => $nit,
                'tipo_doc_identidad' => $tipoDoc,
                'nombre_razon_social'=> $nombre,
                'importe_total'      => $total,
                'importe_base_cf'    => $baseCf,
                'descuento'          => $descuento,
                'tipo_factura'       => $tipoFact,
                'tipo_emision'       => $setting->modalidad,
                'metodo_pago'        => $metodoPago,
                'estado'             => 'pendiente',
                'codigo_qr'          => $qr,
            ]);

            // En ambiente piloto/producción, intentar envío inmediato si es online
            if ($setting->ambiente !== 'simulado' && $setting->modalidad === self::EMISION_ONLINE) {
                $this->sendInvoiceToSin($invoice, $setting);
            }

            return $invoice;
        });
    }

    /**
     * Anula una factura SIAT.
     */
    public function cancelInvoice(SiatInvoice $invoice, string $motivo): void
    {
        if ($invoice->estado === 'anulada') {
            throw new \RuntimeException('La factura ya está anulada.');
        }

        $setting = $this->getActiveSetting($invoice->store_id);

        if ($setting && $setting->ambiente !== 'simulado') {
            // La anulación ante el SIN no está implementada. Se deja constancia de
            // que la factura queda anulada solo en el sistema local, para que la
            // regularización ante Impuestos no se dé por hecha.
            Log::warning('SIAT: anulación local sin notificar al SIN', [
                'invoice_id'     => $invoice->id,
                'numero_factura' => $invoice->numero_factura,
                'ambiente'       => $setting->ambiente,
                'motivo'         => $motivo,
            ]);
        }

        $invoice->update([
            'estado'           => 'anulada',
            'anulado_at'       => now(),
            'motivo_anulacion' => $motivo,
        ]);
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
     * Envía la factura al SIN mediante SOAP (stub para integración futura).
     */
    private function sendInvoiceToSin(SiatInvoice $invoice, SiatSetting $setting): void
    {
        // TODO: implementar llamada WSDL al SIN
        // $wsdlUrl = $setting->ambiente === 'produccion'
        //     ? 'https://facturacion.impuestos.gob.bo/ServicioFacturacion?wsdl'
        //     : 'https://piloto.facturacion.impuestos.gob.bo/ServicioFacturacion?wsdl';
        //
        // $client = new \SoapClient($wsdlUrl, ['trace' => true]);
        // $xml = $this->buildXml($invoice, $setting);
        // $response = $client->recepcionFactura(['xml' => base64_encode($xml)]);
        // if ($response->codigoDescripcion === 'PENDIENTE') {
        //     $invoice->update(['estado' => 'enviada', 'enviado_at' => now()]);
        // }

        Log::info("SIAT: factura #{$invoice->numero_factura} pendiente de envío a SIN (ambiente: {$setting->ambiente})");
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
