<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiatSettingRequest;
use App\Models\SiatCufdCode;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Services\Siat\SiatCodigosService;
use App\Services\Siat\SiatException;
use App\Services\SiatService;
use Inertia\Inertia;
use Inertia\Response;

class SiatSettingController extends Controller
{
    public function __construct(private readonly SiatService $siat) {}

    public function index(): Response
    {
        $settings = SiatSetting::with('store')
            ->latest()
            ->get()
            ->map(fn ($s) => array_merge($s->toArray(), [
                'ambiente_label'  => $s->ambiente_label,
                'modalidad_label' => $s->modalidad_label,
            ]));

        return Inertia::render('admin/siat/settings/index', [
            'settings' => $settings,
            'stores'   => Store::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/siat/settings/form', [
            'stores'  => Store::where('is_active', true)->get(['id', 'name']),
            'setting' => null,
        ]);
    }

    public function store(SiatSettingRequest $request)
    {
        SiatSetting::create($request->validated());
        return redirect()->route('admin.siat.settings.index')
            ->with('success', 'Configuración SIAT guardada.');
    }

    public function edit(SiatSetting $setting): Response
    {
        return Inertia::render('admin/siat/settings/form', [
            'stores'  => Store::where('is_active', true)->get(['id', 'name']),
            'setting' => $setting,
        ]);
    }

    public function update(SiatSettingRequest $request, SiatSetting $setting)
    {
        $data = $request->validated();

        // El formulario ya no recibe las credenciales (van ocultas), así que llegan
        // vacías salvo que el usuario escriba una nueva. Dejarlas pasar en blanco
        // borraría el token y el CUIS ya guardados.
        foreach (['token_api', 'cuis'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $setting->update($data);

        return redirect()->route('admin.siat.settings.index')
            ->with('success', 'Configuración SIAT actualizada.');
    }

    public function destroy(SiatSetting $setting)
    {
        $setting->delete();
        return back()->with('success', 'Configuración eliminada.');
    }

    /**
     * Genera un nuevo CUFD (localmente en modo simulado, o pidiéndolo al SIN).
     */
    public function generateCufd(SiatSetting $setting)
    {
        try {
            $cufd = $this->siat->getOrCreateCufd($setting);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', "CUFD obtenido: válido hasta {$cufd->fecha_vigencia->format('d/m/Y H:i')}");
    }

    /**
     * Solicita el CUIS al SIN. Es el primer paso: sin CUIS no hay CUFD.
     */
    public function requestCuis(SiatSetting $setting, SiatCodigosService $codigos)
    {
        if ($setting->ambiente === 'simulado') {
            return back()->withErrors(['siat' => 'El modo simulado no requiere CUIS.']);
        }

        try {
            $cuis = $codigos->solicitarCuis($setting);
        } catch (SiatException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', 'CUIS obtenido correctamente: ' . substr($cuis, 0, 8) . '…');
    }

    /**
     * Prueba de conectividad y validez del Token Delegado.
     */
    public function testConnection(SiatSetting $setting, SiatCodigosService $codigos)
    {
        if ($setting->ambiente === 'simulado') {
            return back()->withErrors(['siat' => 'El modo simulado no se conecta al SIN.']);
        }

        try {
            $codigos->verificarComunicacion($setting);
        } catch (SiatException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', 'Comunicación con el SIN verificada correctamente.');
    }

    /**
     * Lista los CUFDs de una configuración.
     */
    public function cufdHistory(SiatSetting $setting): Response
    {
        $codes = SiatCufdCode::where('store_id', $setting->store_id)
            ->latest()
            ->paginate(20);

        return Inertia::render('admin/siat/settings/cufd-history', [
            'setting' => $setting->load('store'),
            'codes'   => $codes,
        ]);
    }
}
