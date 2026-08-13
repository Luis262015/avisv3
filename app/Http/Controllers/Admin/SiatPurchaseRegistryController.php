<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\SiatPaquete;
use App\Models\SiatSetting;
use App\Services\Siat\RegistroCompraXml;
use App\Services\Siat\RegistroComprasService;
use App\Services\Siat\SiatComprasService;
use App\Services\Siat\SiatException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registro de Compras: declarar ante el SIN las compras de un periodo.
 *
 * La pantalla trabaja por gestión y mes porque así lo hace el servicio, y avisa
 * de antemano de lo que le falta a cada compra: el paquete es todo o nada, y una
 * sola compra incompleta tumba la declaración entera.
 */
class SiatPurchaseRegistryController extends Controller
{
    public function __construct(
        private readonly RegistroComprasService $registro,
        private readonly SiatComprasService $compras,
        private readonly RegistroCompraXml $xml,
    ) {}

    public function index(Request $request): Response
    {
        $settings = SiatSetting::with('store')->where('is_active', true)->orderBy('id')->get();
        $setting  = $settings->firstWhere('id', $request->integer('setting_id')) ?? $settings->first();

        $gestion = $request->integer('gestion') ?: (int) now()->format('Y');
        $periodo = $request->integer('periodo') ?: (int) now()->format('n');

        $compras = $this->comprasDelPeriodo($gestion, $periodo);
        $faltan  = $this->registro->revisar($compras);

        return Inertia::render('admin/siat/purchases/index', [
            'compras' => $compras->map(fn (Purchase $c): array => [
                'id'                  => $c->id,
                'folio'               => $c->folio,
                'invoice_number'      => $c->invoice_number,
                'invoice_date'        => $c->invoice_date?->toDateString(),
                'proveedor'           => $c->razon_social_proveedor ?: $c->supplier?->name,
                'nit'                 => $c->nit_proveedor ?: $c->supplier?->rfc,
                'codigo_autorizacion' => $c->codigo_autorizacion,
                'total'               => $c->total,
                'tipo_compra'         => $c->tipo_compra,
                'paquete_id'          => $c->paquete_id,
                'problemas'           => $faltan[$c->id] ?? [],
            ])->values(),
            'paquetes' => SiatPaquete::where('tipo', 'compras')
                ->when($setting, fn ($q) => $q->where('store_id', $setting->store_id))
                ->latest()
                ->limit(20)
                ->get(),
            'filtros'  => ['gestion' => $gestion, 'periodo' => $periodo],
            'setting'  => $setting?->only(['id', 'ambiente']),
            'settings' => $settings->map(fn (SiatSetting $s): array => [
                'id'    => $s->id,
                'label' => $s->store?->name ?? $s->razon_social,
            ])->values(),
        ]);
    }

    /** Completa los datos que el SIN pide y el módulo de Compras no guardaba. */
    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        $purchase->update($request->validate([
            'codigo_autorizacion'    => ['nullable', 'string', 'max:100'],
            'invoice_number'         => ['nullable', 'string', 'max:50'],
            'numero_dui_dim'         => ['nullable', 'string', 'max:15'],
            'nit_proveedor'          => ['nullable', 'string', 'max:15'],
            'razon_social_proveedor' => ['nullable', 'string', 'max:240'],
            'tipo_compra'            => ['nullable', 'integer', 'min:1', 'max:255'],
            'codigo_control'         => ['nullable', 'string', 'max:20'],
            'credito_fiscal'         => ['nullable', 'numeric', 'min:0'],
        ]));

        return back()->with('success', "Compra {$purchase->folio} actualizada.");
    }

    /** Declara el periodo completo. */
    public function send(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'setting_id' => ['required', 'integer', 'exists:siat_settings,id'],
            'gestion'    => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodo'    => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $setting = SiatSetting::findOrFail($datos['setting_id']);

        // Las ya declaradas no se reenvían: el SIN las rechazaría por duplicadas.
        $compras = $this->comprasDelPeriodo((int) $datos['gestion'], (int) $datos['periodo'])
            ->whereNull('paquete_id');

        try {
            $paquete = $this->registro->enviarPeriodo(
                $setting, $compras, (int) $datos['gestion'], (int) $datos['periodo'],
            );
        } catch (SiatException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Periodo declarado con %d compra(s). Código de recepción: %s.',
            $paquete->cantidad_facturas,
            $paquete->codigo_recepcion,
        ));
    }

    public function validatePackage(SiatPaquete $paquete): RedirectResponse
    {
        $setting = SiatSetting::where('store_id', $paquete->store_id)->where('is_active', true)->first();

        if (! $setting) {
            return back()->withErrors(['siat' => 'No hay configuración SIAT activa para este paquete.']);
        }

        try {
            $resultado = $this->registro->validarPaquete($paquete, $setting);
        } catch (SiatException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Estado del paquete en el SIN: %s (código %s).',
            $resultado['codigoDescripcion'] ?: 'sin descripción',
            $resultado['codigoEstado'] ?? '?',
        ));
    }

    /**
     * Cierra el periodo ante el SIN: confirma que lo declarado es todo.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'setting_id' => ['required', 'integer', 'exists:siat_settings,id'],
            'gestion'    => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodo'    => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $setting = SiatSetting::findOrFail($datos['setting_id']);

        $cantidad = $this->comprasDelPeriodo((int) $datos['gestion'], (int) $datos['periodo'])
            ->whereNotNull('paquete_id')
            ->count();

        try {
            $resultado = $this->compras->confirmar(
                $setting, (int) $datos['gestion'], (int) $datos['periodo'], $cantidad,
            );
        } catch (SiatException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', 'Periodo confirmado ante el SIN: '
            . ($resultado['codigoDescripcion'] ?: 'sin descripción') . '.');
    }

    /**
     * Compras del periodo, por la fecha de la factura del proveedor: es la que
     * determina en qué periodo se declara, no la del registro en el sistema.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Purchase>
     */
    private function comprasDelPeriodo(int $gestion, int $periodo)
    {
        return Purchase::with('supplier')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($gestion, $periodo): void {
                $q->where(function ($sub) use ($gestion, $periodo): void {
                    $sub->whereNotNull('invoice_date')
                        ->whereYear('invoice_date', $gestion)
                        ->whereMonth('invoice_date', $periodo);
                })->orWhere(function ($sub) use ($gestion, $periodo): void {
                    $sub->whereNull('invoice_date')
                        ->whereYear('date', $gestion)
                        ->whereMonth('date', $periodo);
                });
            })
            ->orderBy('invoice_date')
            ->get();
    }
}
