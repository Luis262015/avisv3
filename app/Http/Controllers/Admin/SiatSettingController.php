<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiatSettingRequest;
use App\Models\SiatCufdCode;
use App\Models\SiatSetting;
use App\Models\Store;
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
        $setting->update($request->validated());
        return redirect()->route('admin.siat.settings.index')
            ->with('success', 'Configuración SIAT actualizada.');
    }

    public function destroy(SiatSetting $setting)
    {
        $setting->delete();
        return back()->with('success', 'Configuración eliminada.');
    }

    /**
     * Genera un nuevo CUFD (manualmente o desde SIN según ambiente).
     */
    public function generateCufd(SiatSetting $setting)
    {
        $cufd = $this->siat->getOrCreateCufd($setting);
        return back()->with('success', "CUFD generado: válido hasta {$cufd->fecha_vigencia->format('d/m/Y H:i')}");
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
