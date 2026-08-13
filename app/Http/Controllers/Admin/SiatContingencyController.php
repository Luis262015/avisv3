<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiatEvento;
use App\Models\SiatInvoice;
use App\Models\SiatPaquete;
use App\Models\SiatSetting;
use App\Services\Siat\SiatContingenciaService;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatSincronizacionService;
use App\Services\SiatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cortes de servicio y envío por lotes.
 *
 * Las operaciones contra el SIN se ejecutan aquí de forma síncrona y no en cola:
 * son pocas y el usuario necesita ver el resultado: si el SIN rechaza el evento,
 * enterarse tres minutos después por un log no sirve de nada. Para automatizarlo
 * —un reintento programado, por ejemplo— está {@see \App\Jobs\EnviarPaqueteContingencia}.
 */
class SiatContingencyController extends Controller
{
    public function __construct(
        private readonly SiatContingenciaService $contingencia,
        private readonly SiatSincronizacionService $sincronizacion,
    ) {}

    public function index(Request $request): Response
    {
        $settings = SiatSetting::with('store')->where('is_active', true)->orderBy('id')->get();
        $setting  = $settings->firstWhere('id', $request->integer('setting_id')) ?? $settings->first();

        $eventos = SiatEvento::with(['store', 'cufdCode'])
            ->when($setting, fn ($q) => $q->where('store_id', $setting->store_id))
            ->withCount([
                'invoices as facturas_totales',
                'invoices as facturas_pendientes' => fn ($q) => $q->where('estado', 'contingencia'),
            ])
            ->latest()
            ->limit(50)
            ->get();

        $paquetes = SiatPaquete::when($setting, fn ($q) => $q->where('store_id', $setting->store_id))
            ->latest()
            ->limit(50)
            ->get();

        return Inertia::render('admin/siat/contingency/index', [
            'eventos'  => $eventos,
            'paquetes' => $paquetes,
            'motivos'  => $this->motivos($setting),
            // Facturas que esperan un lote masivo: se emitieron con conexión pero
            // con tipoEmision 3, así que no pueden salir sueltas.
            'masivas_pendientes' => $setting
                ? SiatInvoice::where('store_id', $setting->store_id)
                    ->where('estado', 'pendiente')
                    ->where('tipo_emision', SiatService::EMISION_MASIVA)
                    ->count()
                : 0,
            'setting'  => $setting?->only(['id', 'ambiente']),
            'settings' => $settings->map(fn (SiatSetting $s): array => [
                'id'    => $s->id,
                'label' => $s->store?->name ?? $s->razon_social,
            ])->values(),
        ]);
    }

    /** Abre el corte: desde aquí se factura fuera de línea. */
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'setting_id'           => ['required', 'integer', 'exists:siat_settings,id'],
            'codigo_motivo_evento' => ['required', 'integer', 'min:1'],
            'descripcion'          => ['required', 'string', 'max:500'],
            // Opcional a propósito: solo lo exigen ciertos motivos, y mandarlo de
            // más hace que el SIN observe el paquete completo.
            'cafc'                 => ['nullable', 'string', 'max:50'],
        ]);

        $setting = SiatSetting::findOrFail($datos['setting_id']);

        try {
            $this->contingencia->abrir(
                $setting,
                (int) $datos['codigo_motivo_evento'],
                $datos['descripcion'],
                cafc: $datos['cafc'] ?? null,
            );
        } catch (SiatException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', 'Corte abierto: las ventas se facturarán fuera de línea hasta que lo cierre.');
    }

    public function close(SiatEvento $evento): RedirectResponse
    {
        try {
            $this->contingencia->cerrar($evento);
        } catch (SiatException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', 'Corte cerrado. Declárelo al SIN y envíe el paquete de facturas.');
    }

    /**
     * Declara el corte y envía en un solo paso el paquete de sus facturas: son las
     * dos mitades de la misma obligación y separarlas solo invita a dejarla a medias.
     */
    public function send(SiatEvento $evento): RedirectResponse
    {
        $setting = SiatSetting::where('store_id', $evento->store_id)->where('is_active', true)->first();

        if (! $setting) {
            return back()->withErrors(['siat' => 'No hay configuración SIAT activa para la tienda de este corte.']);
        }

        try {
            $this->contingencia->declarar($evento, $setting);
            $paquete = $this->contingencia->enviarPaquete($evento, $setting);
        } catch (SiatException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Paquete enviado con %d factura(s). Código de recepción: %s.',
            $paquete->cantidad_facturas,
            $paquete->codigo_recepcion,
        ));
    }

    /**
     * Envía en un lote masivo las facturas que esperan por volumen.
     *
     * No son de contingencia: se emitieron con conexión y con tipoEmision 3, así
     * que no llevan evento ni CAFC y no pueden enviarse sueltas.
     */
    public function sendMasivo(Request $request): RedirectResponse
    {
        $setting = SiatSetting::find($request->integer('setting_id'));

        if (! $setting) {
            return back()->withErrors(['siat' => 'No se encontró la configuración SIAT indicada.']);
        }

        $facturas = SiatInvoice::where('store_id', $setting->store_id)
            ->where('estado', 'pendiente')
            ->where('tipo_emision', SiatService::EMISION_MASIVA)
            ->with(['cufdCode', 'sale.user', 'sale.items.product'])
            ->get();

        if ($facturas->isEmpty()) {
            return back()->withErrors(['siat' => 'No hay facturas masivas pendientes de envío.']);
        }

        try {
            $paquete = $this->contingencia->enviarMasivo($setting, $facturas);
        } catch (SiatException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Lote masivo enviado con %d factura(s). Código de recepción: %s.',
            $paquete->cantidad_facturas,
            $paquete->codigo_recepcion,
        ));
    }

    /** Pregunta al SIN si el paquete quedó validado. */
    public function validatePackage(SiatPaquete $paquete): RedirectResponse
    {
        $setting = SiatSetting::where('store_id', $paquete->store_id)->where('is_active', true)->first();

        if (! $setting) {
            return back()->withErrors(['siat' => 'No hay configuración SIAT activa para la tienda de este paquete.']);
        }

        try {
            $resultado = $this->contingencia->validarPaquete($paquete, $setting);
        } catch (SiatException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', 'Estado del paquete en el SIN: '
            . ($resultado['codigoDescripcion'] ?? 'sin descripción'));
    }

    /**
     * Motivos de evento admitidos. Si el SIN no responde se ofrece la lista vacía
     * y el formulario acepta el código a mano: durante un corte, que es justo
     * cuando esto se usa, el SIN no está disponible por definición.
     *
     * @return array{opciones: list<array{codigo: int, descripcion: string}>, error: ?string}
     */
    private function motivos(?SiatSetting $setting): array
    {
        if (! $setting || $setting->ambiente === 'simulado') {
            return ['opciones' => [], 'error' => null];
        }

        try {
            $motivos = $this->sincronizacion->eventosSignificativos($setting);
        } catch (SiatException $e) {
            return ['opciones' => [], 'error' => $e->getMessage()];
        }

        return [
            'opciones' => array_map(
                fn (int $codigo, string $descripcion): array => ['codigo' => $codigo, 'descripcion' => $descripcion],
                array_keys($motivos),
                array_values($motivos),
            ),
            'error' => null,
        ];
    }
}
