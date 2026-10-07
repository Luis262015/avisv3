<?php

namespace App\Providers;

use App\Models\Payable;
use App\Observers\PayableObserver;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Payable::observe(PayableObserver::class);

        // Sin envoltorio «data»: Inertia lo añadía a cada recurso anidado y el
        // frontend recibía `subscription.plan.data.name`. Las listas paginadas
        // lo conservan, que ahí sí separa las filas de la paginación.
        JsonResource::withoutWrapping();
    }
}
