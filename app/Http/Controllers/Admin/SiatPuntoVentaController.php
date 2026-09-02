<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiatPuntoVenta;
use App\Models\SiatSetting;
use App\Services\Siat\SiatSincronizacionService;
use App\Services\SiatPuntoVentaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Puntos de venta del SIN.
 *
 * La homologación repite cada caso con punto de venta 0 y 1, así que desde aquí se
 * da de alta el segundo punto, se le pide su CUIS y se elige cuál emite.
 */
class SiatPuntoVentaController extends Controller
{
    public function __construct(
        private readonly SiatPuntoVentaService $puntos,
        private readonly SiatSincronizacionService $sincronizacion,
    ) {}

    public function index(Request $request): Response
    {
        $setting = $this->setting($request);

        return Inertia::render('admin/siat/puntos-venta/index', [
            'setting' => $setting?->only([
                'id', 'store_id', 'codigo_sucursal', 'codigo_punto_venta', 'ambiente',
            ]),
            'settings' => SiatSetting::with('store:id,name')->get()
                ->map(fn (SiatSetting $s) => [
                    'id' => $s->id, 'store_id' => $s->store_id, 'store' => $s->store?->name,
                ]),
            'puntos' => $setting
                ? SiatPuntoVenta::where('store_id', $setting->store_id)
                    ->orderBy('codigo')->get()
                : [],
            'tipos' => $this->tipos($setting),
        ]);
    }

    /**
     * Da de alta un punto de venta ante el SIN.
     *
     * No se puede elegir el número: lo asigna el SIN. Y no se deshace salvo dando
     * de baja el punto, así que la pantalla lo confirma antes.
     */
    public function store(Request $request, SiatSetting $setting)
    {
        $datos = $request->validate([
            'nombre'      => ['required', 'string', 'max:100'],
            'descripcion' => ['required', 'string', 'max:500'],
            'tipo'        => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        try {
            $punto = $this->puntos->registrar(
                $setting, $datos['nombre'], $datos['descripcion'], $datos['tipo'],
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with(
            'success',
            "Punto de venta registrado. El SIN le asignó el código {$punto->codigo}; "
            . 'solicite su CUIS antes de emitir por él.'
        );
    }

    /** Reconcilia con lo que el SIN tiene registrado. Es de solo lectura. */
    public function sync(SiatSetting $setting)
    {
        try {
            $resultado = $this->puntos->sincronizar($setting);
        } catch (\Throwable $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with(
            'success',
            "El SIN tiene {$resultado['sincronizados']} punto(s) de venta; "
            . "{$resultado['nuevos']} no estaban registrados aquí."
        );
    }

    public function requestCuis(SiatSetting $setting, SiatPuntoVenta $punto)
    {
        try {
            $this->puntos->solicitarCuis($setting, $punto);
        } catch (\Throwable $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', "CUIS obtenido para el punto de venta {$punto->codigo}.");
    }

    public function activate(SiatSetting $setting, SiatPuntoVenta $punto)
    {
        try {
            $this->puntos->activar($setting, $punto);
        } catch (\Throwable $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', "Ahora se emite por el punto de venta {$punto->codigo}.");
    }

    public function close(SiatSetting $setting, SiatPuntoVenta $punto)
    {
        try {
            $this->puntos->cerrar($setting, $punto);
        } catch (\Throwable $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', "Punto de venta {$punto->codigo} dado de baja ante el SIN.");
    }

    private function setting(Request $request): ?SiatSetting
    {
        return $request->filled('setting')
            ? SiatSetting::find($request->integer('setting'))
            : SiatSetting::where('is_active', true)->orderBy('store_id')->first();
    }

    /**
     * Tipos de punto de venta del SIN. Si el servicio no responde, la pantalla
     * sigue en pie sin el desplegable en vez de caerse.
     *
     * @return array<int, string>
     */
    private function tipos(?SiatSetting $setting): array
    {
        if ($setting === null) {
            return [];
        }

        try {
            return $this->sincronizacion->tiposPuntoVenta($setting);
        } catch (\Throwable) {
            return [];
        }
    }
}
