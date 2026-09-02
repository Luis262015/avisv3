<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SiatCufdCode;
use App\Models\SiatEvento;
use App\Models\SiatHomologacionCaso;
use App\Models\SiatInvoice;
use App\Models\SiatNota;
use App\Models\SiatPuntoVenta;
use App\Models\SiatSetting;
use App\Models\User;
use App\Services\SiatNotaService;
use App\Services\SiatPuntoVentaService;
use App\Services\SiatService;
use Illuminate\Support\Collection;

/**
 * Ejecuta los casos de la homologación contra el ambiente de pruebas del SIN.
 *
 * Reutiliza los mismos servicios que usa la aplicación de verdad —emisión,
 * notas, contingencia, masiva— porque lo que se homologa es el sistema, no un
 * banco de pruebas paralelo: si aquí se emitiera por otro camino, la
 * homologación no probaría nada.
 *
 * Todo lo que genera va marcado con el prefijo `HOMOL-` para poder distinguirlo
 * después de los datos reales.
 */
final class HomologacionRunner
{
    public const PREFIJO = 'HOMOL';

    public function __construct(
        private readonly SiatService $siat,
        private readonly SiatNotaService $notas,
        private readonly SiatPuntoVentaService $puntos,
        private readonly SiatContingenciaService $contingencia,
        private readonly SiatSincronizacionService $sincronizacion,
    ) {}

    /**
     * Ejecuta un caso hasta agotar su cantidad o hasta `$limite` documentos.
     *
     * Devuelve cuántos salieron bien. El caso queda con el resultado del SIN
     * anotado, así que una interrupción no obliga a empezar de cero.
     */
    public function ejecutar(SiatHomologacionCaso $caso, SiatSetting $setting, ?int $limite = null): int
    {
        $this->activarPunto($setting, $caso->punto_venta);

        $caso->update(['estado' => 'en_curso']);

        try {
            $hechos = match ($caso->etapa) {
                2 => $this->etapaSincronizacion($caso, $setting, $limite),
                4 => $this->etapaEmision($caso, $setting, $limite),
                5 => $this->etapaEvento($caso, $setting, $limite),
                6 => $this->etapaPaquete($caso, $setting),
                7 => $this->etapaAnulacion($caso, $setting, $limite),
                9 => $this->etapaMasiva($caso, $setting),
                default => throw new SiatException("La etapa {$caso->etapa} no se puede ejecutar desde aquí."),
            };
        } catch (\Throwable $e) {
            $caso->update([
                'estado'       => 'fallido',
                'mensaje'      => $e->getMessage(),
                'ejecutado_at' => now(),
            ]);

            throw $e;
        }

        // El total nuevo se calcula antes de escribirlo: consultarlo con `fresh()`
        // dentro del propio `update()` leería el valor viejo y el caso nunca se
        // daría por completado.
        $completados = $caso->completados + $hechos;

        $caso->update([
            'completados'  => $completados,
            'estado'       => $completados >= $caso->cantidad ? 'completado' : 'en_curso',
            'ejecutado_at' => now(),
        ]);

        return $hechos;
    }

    // ─── Etapas ─────────────────────────────────────────────────────────────

    /**
     * Consume **un** catálogo, tantas veces como pruebas pida el caso.
     *
     * El Portal cuenta una prueba por operación y punto de venta, no una por
     * barrido completo: son 18 operaciones × 2 puntos × 50 pruebas = 1800. Es de
     * solo lectura y no registra nada en el SIN.
     */
    private function etapaSincronizacion(SiatHomologacionCaso $caso, SiatSetting $setting, ?int $limite): int
    {
        $vueltas = $this->cuantas($caso, $limite);
        $metodo  = SiatSincronizacionService::CATALOGOS[$caso->catalogo] ?? null;

        if ($metodo === null && $caso->catalogo !== 'fecha_hora') {
            throw new SiatException("El caso apunta al catálogo «{$caso->catalogo}», que no existe.");
        }

        for ($i = 0; $i < $vueltas; $i++) {
            // Sin vaciar la caché la segunda llamada no saldría a la red y el SIN
            // no contaría la prueba.
            $this->sincronizacion->olvidarCache($setting);

            $metodo === null
                ? $this->sincronizacion->fechaHora($setting)
                : $this->sincronizacion->{$metodo}($setting);
        }

        $caso->update(['codigo_resultado' => 'OK', 'mensaje' => null]);

        return $vueltas;
    }

    /** Emisión individual: factura del sector 1 o nota de los sectores 24 y 47. */
    private function etapaEmision(SiatHomologacionCaso $caso, SiatSetting $setting, ?int $limite): int
    {
        $cuantas = $this->cuantas($caso, $limite);
        $hechos  = 0;

        for ($i = 0; $i < $cuantas; $i++) {
            $documento = $this->esNota($caso)
                ? $this->emitirNota($caso, $setting)
                : $this->emitirFactura($setting);

            $caso->update([
                'codigo_resultado' => $documento->estado,
                'referencia'       => $documento->cuf,
                'mensaje'          => $documento->mensaje_error,
            ]);

            if ($documento->estado === 'rechazada') {
                throw new SiatException("El SIN rechazó el documento: {$documento->mensaje_error}");
            }

            $hechos++;
        }

        return $hechos;
    }

    /** Un evento significativo por motivo: abrir, cerrar y declarar. */
    private function etapaEvento(SiatHomologacionCaso $caso, SiatSetting $setting, ?int $limite): int
    {
        $hechos = 0;

        for ($i = 0; $i < $this->cuantas($caso, $limite); $i++) {
            $this->declararUnCorte($caso, $setting);
            $hechos++;
        }

        return $hechos;
    }

    private function declararUnCorte(SiatHomologacionCaso $caso, SiatSetting $setting): void
    {
        $abierto = $this->contingencia->eventoAbierto($setting->store_id);

        if ($abierto !== null) {
            throw new SiatException(
                "Hay un corte abierto (#{$abierto->id}) en esta tienda. Ciérrelo antes de declarar otro."
            );
        }

        // Cada evento necesita su propia franja: el SIN responde «981 RANGO DE
        // FECHAS DE EVENTO SIGNIFICATIVO INVALIDO» cuando el rango se solapa con
        // otro ya registrado, así que se van escalonando hacia atrás.
        [$inicio, $fin] = $this->franjaLibre($setting);

        $evento = $this->contingencia->abrir(
            $setting,
            (int) $caso->motivo_evento,
            'Homologación Fase I — etapa V, motivo ' . $caso->motivo_evento,
            $inicio,
        );

        $this->contingencia->cerrar($evento, $fin);

        try {
            $evento = $this->contingencia->declarar($evento->refresh(), $setting);
        } catch (\Throwable $e) {
            // Un corte que el SIN no aceptó no existe: dejar la fila local haría
            // creer que ese rango está ocupado y desplazaría los siguientes.
            $evento->delete();

            throw $e;
        }

        $caso->update([
            'codigo_resultado' => $evento->estado,
            'referencia'       => $evento->codigo_recepcion_evento,
            'mensaje'          => $evento->mensaje_error,
        ]);
    }

    /**
     * Paquete de contingencia: abre el corte, emite las facturas fuera de línea
     * dentro de él y manda el lote.
     */
    private function etapaPaquete(SiatHomologacionCaso $caso, SiatSetting $setting): int
    {
        if ($this->contingencia->eventoAbierto($setting->store_id) !== null) {
            throw new SiatException('Hay un corte abierto en esta tienda; ciérrelo antes.');
        }

        $evento = $this->contingencia->abrir(
            $setting,
            (int) $caso->motivo_evento,
            'Homologación Fase I — etapa VI, lote de ' . $caso->tamano_lote,
            now()->subHours(2),
        );

        // Dentro del corte, `createInvoice` emite fuera de línea por sí solo.
        for ($i = 0; $i < (int) $caso->tamano_lote; $i++) {
            $this->emitirFactura($setting);
        }

        $this->contingencia->cerrar($evento);
        $evento  = $this->contingencia->declarar($evento->refresh(), $setting);
        $paquete = $this->contingencia->enviarPaquete($evento, $setting);

        $caso->update([
            'codigo_resultado' => (string) $paquete->codigo_estado,
            'referencia'       => $paquete->codigo_recepcion,
            'mensaje'          => $paquete->mensaje_error,
        ]);

        // Una prueba es un paquete enviado, no una factura suelta.
        return 1;
    }

    /**
     * Lote masivo: se emite con el punto de venta en modo masivo —el tipo de
     * emisión va dentro del CUF, así que se decide al emitir— y después se manda
     * el lote entero.
     */
    private function etapaMasiva(SiatHomologacionCaso $caso, SiatSetting $setting): int
    {
        $previo = (bool) $setting->emision_masiva;
        $setting->update(['emision_masiva' => true]);

        try {
            $facturas = new Collection();

            for ($i = 0; $i < (int) $caso->tamano_lote; $i++) {
                $facturas->push($this->emitirFactura($setting));
            }

            $paquete = $this->contingencia->enviarMasivo($setting, $facturas);
        } finally {
            $setting->update(['emision_masiva' => $previo]);
        }

        $caso->update([
            'codigo_resultado' => (string) $paquete->codigo_estado,
            'referencia'       => $paquete->codigo_recepcion,
            'mensaje'          => $paquete->mensaje_error,
        ]);

        return 1;
    }

    /**
     * Anulación y reversión sobre documentos ya validados.
     *
     * Anula lo que este mismo generador emitió en la etapa IV y no está anulado.
     * Si no queda nada, lo dice en vez de emitir de más: gastar correlativos para
     * poder anularlos es decisión de quien ejecuta, no del motor.
     */
    private function etapaAnulacion(SiatHomologacionCaso $caso, SiatSetting $setting, ?int $limite): int
    {
        $cuantas = $this->cuantas($caso, $limite);
        $hechos  = 0;

        for ($i = 0; $i < $cuantas; $i++) {
            if ($this->esNota($caso)) {
                $nota = $this->notaAnulable($caso, $setting);

                $this->notas->anular($nota, SiatService::ANULACION_NOTA_MAL_EMITIDA);
                $resultado = $this->notas->revertirAnulacion($nota->fresh());
                $referencia = $nota->cuf;
            } else {
                $factura = $this->facturaAnulable($setting, $caso->punto_venta);

                $this->siat->cancelInvoice($factura, 'Homologación Fase I — etapa VII');
                $resultado  = $this->siat->revertCancellation($factura->fresh());
                $referencia = $factura->cuf;
            }

            $caso->update([
                'codigo_resultado' => (string) ($resultado['codigoEstado'] ?? ''),
                'referencia'       => $referencia,
                'mensaje'          => null,
            ]);

            $hechos++;
        }

        return $hechos;
    }

    // ─── Emisión de documentos de prueba ────────────────────────────────────

    private function emitirFactura(SiatSetting $setting): SiatInvoice
    {
        return $this->siat->createInvoice($this->venta($setting), [
            'nit_ci'       => '9876543',
            'tipo_doc'     => SiatService::DOC_CI,
            'nombre'       => 'CLIENTE DE HOMOLOGACION',
            'tipo_factura' => SiatService::FACTURA_CON_CF,
        ]);
    }

    /**
     * Una nota necesita una factura vigente que ajustar y una devolución: se
     * emite la factura, se devuelve una unidad y se emite la nota del sector.
     */
    private function emitirNota(SiatHomologacionCaso $caso, SiatSetting $setting): SiatNota
    {
        $factura = $this->emitirFactura($setting);

        if ($factura->estado === 'rechazada') {
            throw new SiatException(
                "No se pudo emitir la factura que la nota tiene que ajustar: {$factura->mensaje_error}"
            );
        }

        $venta = $factura->sale;
        $item  = $venta->items->first();

        $devolucion = SaleReturn::create([
            'sale_id'       => $venta->id,
            'user_id'       => $venta->user_id,
            'folio'         => self::PREFIJO . '-DEV-' . $venta->id,
            'date'          => now(),
            'reason'        => 'Homologación Fase I — etapa IV',
            'refund_method' => 'cash',
            'refund_amount' => $item->price,
            'status'        => 'completed',
            'restock'       => false,
        ]);

        SaleReturnItem::create([
            'sale_return_id' => $devolucion->id,
            'sale_item_id'   => $item->id,
            'product_id'     => $item->product_id,
            'quantity'       => 1,
            'unit_price'     => $item->price,
            'subtotal'       => $item->price,
        ]);

        return $this->notas->emitir($devolucion->fresh(), (int) $caso->documento_sector);
    }

    /** Una venta sintética de una línea, con un producto homologado. */
    private function venta(SiatSetting $setting): Sale
    {
        $producto = $this->producto();
        $turno    = $this->turno($setting);

        $venta = Sale::create([
            'cash_shift_id'  => $turno->id,
            'user_id'        => $turno->user_id,
            'folio'          => self::PREFIJO . '-' . now()->format('ymdHis') . '-' . random_int(100, 999),
            'subtotal'       => $producto->price,
            'total'          => $producto->price,
            'amount_paid'    => $producto->price,
            'payment_method' => 'cash',
            'status'         => 'completed',
        ]);

        SaleItem::create([
            'sale_id'    => $venta->id,
            'product_id' => $producto->id,
            'quantity'   => 1,
            'price'      => $producto->price,
            'discount'   => 0,
            'subtotal'   => $producto->price,
        ]);

        return $venta->load('items.product');
    }

    private function producto(): Product
    {
        return Product::whereNotNull('codigo_producto_sin')
            ->where('status', 'active')
            ->inRandomOrder()
            ->first()
            ?? throw new SiatException(
                'No hay ningún producto homologado con el SIN. Homológuelos antes: sin '
                . '`codigo_producto_sin` la factura no se puede construir.'
            );
    }

    /**
     * Un turno de caja de la tienda. Si no hay ninguno abierto se abre uno propio
     * del generador, para no colgar las ventas de prueba de la caja real.
     */
    private function turno(SiatSetting $setting): CashShift
    {
        $abierto = CashShift::where('status', 'open')
            ->whereHas('cashRegister', fn ($q) => $q->where('store_id', $setting->store_id))
            ->latest()
            ->first();

        if ($abierto !== null) {
            return $abierto;
        }

        $caja = CashRegister::firstOrCreate(
            ['store_id' => $setting->store_id, 'name' => self::PREFIJO . ' — caja de pruebas'],
            ['is_active' => true],
        );

        return CashShift::create([
            'cash_register_id' => $caja->id,
            'user_id'          => User::query()->value('id'),
            'opening_amount'   => 0,
            'opened_at'        => now(),
            'status'           => 'open',
        ]);
    }

    // ─── Selección de documentos a anular ───────────────────────────────────

    private function facturaAnulable(SiatSetting $setting, int $puntoVenta): SiatInvoice
    {
        return SiatInvoice::where('store_id', $setting->store_id)
            ->whereIn('estado', ['enviada', 'validada'])
            ->whereHas('sale', fn ($q) => $q->where('folio', 'like', self::PREFIJO . '%'))
            ->whereHas('cufdCode.puntoVenta', fn ($q) => $q->where('codigo', $puntoVenta))
            ->oldest()
            ->first()
            ?? throw new SiatException(
                "No quedan facturas de homologación sin anular en el punto de venta {$puntoVenta}. "
                . 'Ejecute antes la etapa 4 para tener documentos que anular.'
            );
    }

    private function notaAnulable(SiatHomologacionCaso $caso, SiatSetting $setting): SiatNota
    {
        return SiatNota::where('store_id', $setting->store_id)
            ->where('documento_sector', $caso->documento_sector)
            ->whereIn('estado', ['enviada', 'validada'])
            ->oldest()
            ->first()
            ?? throw new SiatException(
                "No quedan notas del sector {$caso->documento_sector} sin anular. "
                . 'Ejecute antes la etapa 4.'
            );
    }

    // ─── Utilidades ─────────────────────────────────────────────────────────

    /**
     * Una franja horaria válida para declarar un corte.
     *
     * Tiene que cumplir dos condiciones a la vez, y son las que costaron dos
     * rechazos del SIN:
     *
     * - **No solaparse con otro corte ya declarado**, o responde «981 RANGO DE
     *   FECHAS DE EVENTO SIGNIFICATIVO INVALIDO».
     * - **Caber dentro de la vigencia del CUFD del evento**, o responde «984 EL
     *   EVENTO SIGNIFICATIVO NO CORRESPONDE AL CUFD DEL EVENTO REGISTRADO».
     *
     * Por eso las franjas se escalonan hacia atrás desde ahora, en bloques de
     * diez minutos separados entre sí, sin bajar del momento en que se obtuvo el
     * CUFD vigente.
     *
     * @return array{0: \Carbon\CarbonInterface, 1: \Carbon\CarbonInterface}
     */
    private function franjaLibre(SiatSetting $setting): array
    {
        $cufd = $this->cufdVigente($setting);

        // Solo cuentan los cortes que el SIN llegó a registrar: los intentos
        // fallidos dejan fila local pero no ocupan ningún rango allí.
        $primero = SiatEvento::where('store_id', $setting->store_id)
            ->where('estado', 'registrado')
            ->where('fecha_inicio', '>=', $cufd->created_at)
            ->min('fecha_inicio');

        // Cada corte nuevo se coloca justo antes del más temprano ya declarado,
        // así que la siguiente llamada retrocede sola sin llevar contador.
        $tope = $primero !== null ? \Carbon\Carbon::parse($primero) : now();

        $fin    = $tope->copy()->subMinutes(2);
        $inicio = $fin->copy()->subMinutes(2);

        if ($inicio->lessThan($cufd->created_at)) {
            throw new SiatException(
                'No queda hueco dentro de la vigencia del CUFD actual para declarar otro corte sin '
                . 'solaparlo con los anteriores. Pida un CUFD nuevo y reanude mañana.'
            );
        }

        return [$inicio, $fin];
    }

    private function cufdVigente(SiatSetting $setting): SiatCufdCode
    {
        return SiatCufdCode::where('store_id', $setting->store_id)
            ->where('punto_venta_id', $setting->puntoVentaActivo()?->id)
            ->where('estado', 'activo')
            ->where('fecha_vigencia', '>', now())
            ->latest()
            ->first()
            ?? throw new SiatException('No hay un CUFD vigente con el que declarar el corte.');
    }

    private function esNota(SiatHomologacionCaso $caso): bool
    {
        return in_array((int) $caso->documento_sector, [24, 47], true);
    }

    private function cuantas(SiatHomologacionCaso $caso, ?int $limite): int
    {
        $restantes = $caso->restantes();

        return $limite === null ? $restantes : min($restantes, $limite);
    }

    private function activarPunto(SiatSetting $setting, int $codigo): void
    {
        if ((int) $setting->codigo_punto_venta === $codigo) {
            return;
        }

        $punto = SiatPuntoVenta::where('store_id', $setting->store_id)
            ->where('codigo_sucursal', (int) $setting->codigo_sucursal)
            ->where('codigo', $codigo)
            ->first()
            ?? throw new SiatException("No hay ningún punto de venta {$codigo} dado de alta en esta tienda.");

        $this->puntos->activar($setting, $punto);
        $setting->refresh();
    }
}
