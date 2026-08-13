<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SiatEvento;
use App\Models\SiatSetting;
use App\Services\Siat\SiatContingenciaService;
use App\Services\Siat\SiatException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Declara un corte ante el SIN y le envía las facturas que se emitieron durante él.
 *
 * Va en cola porque son dos llamadas SOAP encadenadas sobre un paquete que puede
 * pesar: hacerlo dentro de la petición dejaría al usuario esperando y perdería el
 * trabajo si el navegador corta. Se reintenta porque el motivo más habitual del
 * fallo es que el SIN aún no haya vuelto del todo.
 */
final class EnviarPaqueteContingencia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** El SIN suele tardar en estabilizarse tras una caída; no vale reintentar ya. */
    public int $backoff = 300;

    public function __construct(private readonly SiatEvento $evento) {}

    public function handle(SiatContingenciaService $contingencia): void
    {
        $setting = SiatSetting::where('store_id', $this->evento->store_id)
            ->where('is_active', true)
            ->first();

        if (! $setting) {
            throw new SiatException('No hay configuración SIAT activa para la tienda de este corte.');
        }

        // `declarar()` es idempotente: si el evento ya está registrado no vuelve a
        // llamar al SIN, así que un reintento tras un fallo de envío no duplica el
        // evento significativo.
        $contingencia->declarar($this->evento, $setting);
        $contingencia->enviarPaquete($this->evento, $setting);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SIAT: falló el envío del paquete de contingencia', [
            'evento_id' => $this->evento->id,
            'error'     => $e->getMessage(),
        ]);

        $this->evento->update(['mensaje_error' => $e->getMessage()]);
    }
}
