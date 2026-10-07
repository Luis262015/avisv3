<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Central\Tenant;
use App\Services\Billing\Gateways\GatewayManager;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantDatabaseManager;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantDatabaseManager::class);
        $this->app->singleton(Tenancy::class);
        $this->app->singleton(GatewayManager::class);
    }

    public function boot(): void
    {
        // Se resuelve ya, mientras la conexión por defecto es todavía la
        // central: es la que recuerda como tal.
        $tenancy = $this->app->make(Tenancy::class);

        // La base central lleva también estas tablas. Las de cada empresa se
        // migran aparte, indicando su carpeta, y nunca las reciben.
        $this->loadMigrationsFrom(database_path('migrations/central'));

        $this->keepQueueCentral($tenancy);
    }

    /**
     * Una sola cola para toda la plataforma, en la base central. Cada trabajo
     * anota de qué empresa salió y el worker entra en ella antes de correrlo.
     */
    private function keepQueueCentral(Tenancy $tenancy): void
    {
        if (config('queue.connections.database.connection') === null) {
            config(['queue.connections.database.connection' => $tenancy->centralConnection()]);
        }

        Queue::createPayloadUsing(fn (): array => $tenancy->initialized()
            ? ['tenant_id' => $tenancy->tenant()->id]
            : []);

        Event::listen(JobProcessing::class, function (JobProcessing $event) use ($tenancy): void {
            // En «sync» el trabajo corre dentro de la propia petición, que ya
            // está en su empresa y debe seguir en ella al terminar.
            if ($event->connectionName === 'sync') {
                return;
            }

            $id = $event->job->payload()['tenant_id'] ?? null;
            $id ? $tenancy->initialize(Tenant::findOrFail($id)) : $tenancy->end();
        });

        $leave = function (JobProcessed|JobExceptionOccurred $event) use ($tenancy): void {
            if ($event->connectionName !== 'sync') {
                $tenancy->end();
            }
        };

        Event::listen(JobProcessed::class, $leave);
        Event::listen(JobExceptionOccurred::class, $leave);
    }
}
