<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\SiatCufdCode;
use App\Models\SiatSetting;

/**
 * El CUFD vigente de una tienda, pidiéndolo al SIN si hace falta.
 *
 * Vive aparte porque lo necesitan tanto la emisión normal como el registro de un
 * evento significativo, y hacer que el servicio de contingencia dependiera de
 * `SiatService` —que a su vez lo usa a él— habría cerrado un ciclo.
 */
final class CufdProvider
{
    public function __construct(private readonly SiatCodigosService $codigos) {}

    /**
     * Devuelve el CUFD activo y no vencido, o consigue uno nuevo.
     */
    public function vigente(SiatSetting $setting): SiatCufdCode
    {
        $activo = $this->activo($setting);

        if ($activo !== null) {
            return $activo;
        }

        SiatCufdCode::where('store_id', $setting->store_id)
            ->where('estado', 'activo')
            ->update(['estado' => 'vencido']);

        if ($setting->ambiente === 'simulado') {
            return $this->simulado($setting);
        }

        // Piloto y producción piden el CUFD real al SIN. Nunca se cae a un CUFD
        // simulado: eso produciría facturas con apariencia legal y sin respaldo.
        return $this->codigos->solicitarCufd($setting);
    }

    /**
     * El CUFD activo, sin pedir uno nuevo. Sirve cuando lo que interesa es el que
     * estaba en uso —por ejemplo, el `cufdEvento` de un corte— y no el de ahora.
     */
    public function activo(SiatSetting $setting): ?SiatCufdCode
    {
        return SiatCufdCode::where('store_id', $setting->store_id)
            ->where('estado', 'activo')
            ->where('fecha_vigencia', '>', now())
            ->latest()
            ->first();
    }

    /** Para el ambiente "simulado", que no habla con el SIN. */
    private function simulado(SiatSetting $setting): SiatCufdCode
    {
        $codigo = strtoupper(hash('sha256', uniqid($setting->nit, true)));

        return SiatCufdCode::create([
            'store_id'       => $setting->store_id,
            'codigo'         => $codigo,
            'codigo_control' => strtoupper(substr(hash('sha1', $codigo), 0, 8)),
            'fecha_vigencia' => now()->addHours(24),
            'consecutivo'    => 0,
            'estado'         => 'activo',
        ]);
    }
}
