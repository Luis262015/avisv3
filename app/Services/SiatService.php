<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\SiatSetting;
use App\Services\Siat\ConstructorFacturaXml;
use App\Services\Siat\CufdProvider;
use App\Services\Siat\CufGenerator;
use App\Services\Siat\SiatContingenciaService;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatFacturacionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SiatService
{
    public function __construct(
        private readonly CufGenerator $cufGenerator,
        private readonly CufdProvider $cufds,
        private readonly ConstructorFacturaXml $constructor,
        private readonly SiatFacturacionService $facturacion,
        private readonly SiatContingenciaService $contingencia,
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
    // Paramétrica del SIN: 1 en línea, 2 fuera de línea, 3 masivo, 4 contingencia.
    // El 4 es otro flujo y no está implementado.
    const EMISION_ONLINE  = 1;
    const EMISION_OFFLINE = 2;
    const EMISION_MASIVA  = 3;

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
        return $this->cufds->vigente($setting);
    }

    /**
     * Crea una factura SIAT para una venta.
     *
     * @param  array{nit_ci?: string, tipo_doc?: int, nombre?: string, tipo_factura?: int, codigo_excepcion?: int, numero_tarjeta?: string} $buyerData
     */
    public function createInvoice(Sale $sale, array $buyerData = []): SiatInvoice
    {
        $sale->loadMissing(['cashShift.cashRegister.store', 'items']);
        $store = $sale->cashShift->cashRegister->store;

        $setting = $this->getActiveSetting($store->id);
        if (! $setting) {
            throw new \RuntimeException('No hay configuración SIAT activa para esta tienda.');
        }

        // Con un corte declarado abierto se emite fuera de línea: pedir un CUFD
        // nuevo implicaría llamar a un SIN que, por definición, no está.
        $evento = $setting->ambiente === 'simulado'
            ? null
            : $this->contingencia->eventoAbierto($store->id);

        $cufd = $evento
            ? $this->contingencia->cufdDelEvento($evento)
            : $this->getOrCreateCufd($setting);

        return DB::transaction(function () use ($sale, $store, $setting, $cufd, $buyerData, $evento) {
            $numero = $cufd->nextConsecutivo();

            $nit       = $buyerData['nit_ci']     ?? '0';
            $tipoDoc   = (int) ($buyerData['tipo_doc']    ?? self::DOC_NIT);
            $nombre    = $buyerData['nombre']      ?? 'Sin Nombre';
            $tipoFact  = (int) ($buyerData['tipo_factura'] ?? $setting->tipo_factura_default);

            // El SIN no acepta el documento 0 en ningún tipo, ni siquiera con
            // código de excepción: responde `1048 NUMERO DE DOCUMENTO NO VALIDO:
            // Numero documento esperado distinto de 0`. Emitir igualmente gastaría
            // un correlativo en una factura que nunca se podrá enviar, porque el
            // número ya va dentro del CUF.
            if (blank($nit) || $nit === '0') {
                throw new SiatException(
                    'El comprador necesita un documento: el SIN rechaza las facturas con número de '
                    . 'documento 0. Pida el NIT o el CI antes de emitir.'
                );
            }

            // Si tiene NIT, forzar CF; si solo CI, sin CF
            if ($tipoDoc === self::DOC_NIT) {
                $tipoFact = self::FACTURA_CON_CF;
            }

            // En hora de Bolivia, no UTC: esta fecha entra en el CUF y el SIN la
            // valida contra su propio reloj.
            $fechaEmision = Carbon::now(config('siat.timezone'));
            $total        = (float) $sale->total;
            $descuento    = (float) $sale->discount;
            $baseCf       = $total; // sin ICE, exentos, etc.

            $metodoPago = $this->mapPaymentMethod($sale->payment_method);

            // El SIN exige el número de tarjeta cuando el pago es con tarjeta y
            // rechaza la factura sin él (1012). Mejor detenerlo aquí, antes de
            // consumir un número de factura, que descubrirlo en el rechazo.
            $numeroTarjeta = $buyerData['numero_tarjeta'] ?? null;

            if ($metodoPago === self::PAGO_TARJETA && blank($numeroTarjeta)) {
                throw new SiatException(
                    'Esta venta se pagó con tarjeta y el SIN exige el número de tarjeta en la factura. '
                    . 'Regístrelo al emitir.'
                );
            }

            // Durante un corte se emite fuera de línea. El CAFC es el del corte y
            // puede ir vacío: solo lo exigen ciertos motivos de evento, y mandarlo
            // cuando no toca hace que el SIN observe el paquete entero.
            $offline = $evento !== null;
            $cafc    = $offline ? $evento->cafc : null;

            // Un punto de venta masivo tampoco envía factura a factura, pero por
            // volumen y no por avería: hay conexión, y las facturas se agrupan.
            $masiva = ! $offline && (bool) $setting->emision_masiva;

            $tipoEmision = match (true) {
                $offline => self::EMISION_OFFLINE,
                $masiva  => self::EMISION_MASIVA,
                default  => self::EMISION_ONLINE,
            };

            // El CUF se firma con el código de control del CUFD vigente, no con el
            // CUFD mismo; y el tipo de emisión es independiente de la modalidad.
            $cuf = $this->cufGenerator->generate(
                nit: $setting->nit,
                fechaEmision: $fechaEmision,
                sucursal: (int) $setting->codigo_sucursal,
                modalidad: (int) $setting->modalidad,
                // El tipo de emisión va dentro del CUF: si no coincide con el
                // del envío, el SIN rechaza la factura o el lote entero.
                tipoEmision: $tipoEmision,
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
                'evento_id'          => $evento?->id,
                'numero_factura'     => $numero,
                // Con milisegundos: es la fecha que va dentro del CUF y el SIN
                // exige que coincida con la del XML al reenviar.
                'fecha_emision'      => $fechaEmision,
                'cuf'                => $cuf,
                'cufd'               => $cufd->codigo,
                'cafc'               => $cafc,
                'nit_ci'             => $nit,
                'tipo_doc_identidad' => $tipoDoc,
                'codigo_excepcion'   => $buyerData['codigo_excepcion'] ?? null,
                'nombre_razon_social'=> $nombre,
                'importe_total'      => $total,
                'importe_base_cf'    => $baseCf,
                'descuento'          => $descuento,
                'tipo_factura'       => $tipoFact,
                // El tipo de emisión no es la modalidad: se emite en línea salvo
                // durante un corte declarado. Guardar la modalidad dejaba "offline"
                // toda factura computarizada y además impedía el envío.
                'tipo_emision'       => $tipoEmision,
                'metodo_pago'        => $metodoPago,
                'numero_tarjeta'     => $numeroTarjeta,
                // Ni las de contingencia ni las masivas se envían una a una:
                // viajan en un lote. Las primeras quedan marcadas como tales
                // porque su plazo y su tratamiento normativo son distintos.
                'estado'             => $offline ? 'contingencia' : 'pendiente',
                'codigo_qr'          => $qr,
            ]);

            // Ni en corte ni en masiva se intenta el envío individual: ambas
            // esperan a su lote.
            if ($setting->ambiente !== 'simulado' && ! $offline && ! $masiva) {
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
     * Deshace la anulación de una factura, que vuelve a quedar vigente.
     *
     * El SIN solo lo admite dentro del plazo que fija la normativa. Si lo rechaza
     * se propaga el error y la factura sigue anulada en local: darla por vigente
     * aquí mientras el SIN la tiene anulada sería peor que no revertirla.
     *
     * @return array<string, mixed>
     */
    public function revertCancellation(SiatInvoice $invoice): array
    {
        if ($invoice->estado !== 'anulada') {
            throw new SiatException('Solo se puede revertir la anulación de una factura anulada.');
        }

        $setting = $this->getActiveSetting($invoice->store_id)
            ?? throw new SiatException('No hay configuración SIAT activa para esta tienda.');

        $resultado = ['codigoDescripcion' => 'reversión local (modo simulado)'];

        if ($setting->ambiente !== 'simulado') {
            $resultado = $this->facturacion->reversionAnulacionFactura(
                $setting, $invoice->cuf, $invoice->cufd, (int) $invoice->tipo_factura,
            );
        }

        $invoice->update([
            'estado'           => 'enviada',
            'anulado_at'       => null,
            'motivo_anulacion' => null,
            'mensaje_error'    => null,
        ]);

        return $resultado;
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
            $xml = $this->constructor->construir($invoice, $setting, $cufd, $fechaEmision, $sale);

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

        // El CUF de una factura masiva declara emisión 3; enviarla por
        // `recepcionFactura`, que va como emisión 1, hace que el SIN la rechace
        // por incoherencia. Tiene que ir en su lote.
        if ((int) $invoice->tipo_emision === self::EMISION_MASIVA) {
            throw new SiatException(
                'Esta factura se emitió en modalidad masiva: no se envía suelta, sino en un lote '
                . 'desde Facturación SIAT → Contingencia.'
            );
        }

        $invoice->loadMissing(['cufdCode', 'sale.user', 'sale.items.product']);

        $xml = $this->constructor->construir(
            $invoice,
            $setting,
            $invoice->cufdCode,
            $invoice->fecha_emision ?? $invoice->created_at,
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
