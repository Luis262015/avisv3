<?php

use App\Enums\BillingCycle;
use App\Enums\TenantStatus;
use App\Models\Central\Plan;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\Tenant;
use App\Services\Billing\SubscriptionService;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantProvisioner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

/*
| Plataforma
*/

Artisan::command('platform:admin {email} {--name=} {--password=}', function (string $email) {
    $password = $this->option('password') ?: Str::password(16);

    $admin = PlatformAdmin::updateOrCreate(
        ['email' => Str::lower($email)],
        ['name' => $this->option('name') ?: 'Administrador', 'password' => $password],
    );

    $this->info("Administrador de la plataforma listo: {$admin->email}");

    if (! $this->option('password')) {
        $this->line("Contraseña generada: {$password}");
    }
})->purpose('Crea o restablece un administrador del panel de la plataforma');

Artisan::command('billing:mark-overdue', function (SubscriptionService $subscriptions) {
    $this->info($subscriptions->markOverdue().' suscripciones marcadas como vencidas.');
})->purpose('Marca como vencidas las suscripciones que agotaron su plazo');

Schedule::command('billing:mark-overdue')->dailyAt('03:00');

/*
| Empresas
*/

Artisan::command('tenants:adopt {slug} {--name=} {--email=} {--plan=}', function (string $slug, Tenancy $tenancy, SubscriptionService $subscriptions) {
    if (Tenant::where('is_legacy', true)->exists()) {
        $this->error('La base de la plataforma ya está registrada como empresa.');

        return 1;
    }

    $plan = $this->option('plan')
        ? Plan::where('slug', $this->option('plan'))->first()
        : Plan::orderByDesc('sort_order')->first();

    if ($plan === null) {
        $this->error('No hay planes. Corre primero: php artisan db:seed --class=PlatformSeeder');

        return 1;
    }

    $tenant = Tenant::create([
        'name' => $this->option('name') ?: config('app.name'),
        'slug' => Str::lower($slug),
        'database' => config('database.connections.'.$tenancy->centralConnection().'.database'),
        'is_legacy' => true,
        'owner_name' => 'Administrador',
        'owner_email' => $this->option('email') ?: 'admin@admin.com',
        'status' => TenantStatus::Active,
    ]);

    // Es la casa: no se le cobra ni vence.
    $subscriptions->changePlan($tenant, $plan, BillingCycle::Yearly)->update(['current_period_end' => null]);

    $this->info("Los datos que ya existían quedan como la empresa «{$tenant->name}».");
    $this->line('Entra por: '.$tenant->url('login'));
})->purpose('Registra como empresa los datos que ya vivían en la base de la plataforma');

Artisan::command('tenants:migrate {--tenant= : Dirección de una sola empresa}', function (Tenancy $tenancy, TenantProvisioner $provisioner) {
    $tenants = Tenant::query()
        ->where('is_legacy', false)
        ->when($this->option('tenant'), fn ($q, $slug) => $q->where('slug', $slug))
        ->get();

    foreach ($tenants as $tenant) {
        $tenancy->run($tenant, fn () => $provisioner->migrate());
        $this->line("Migrada: {$tenant->slug}");
    }

    $this->info($tenants->count().' empresas al día.');
})->purpose('Aplica las migraciones pendientes en la base de cada empresa');

Artisan::command('tenants:run {tenant : Dirección de la empresa} {artisan : Comando entre comillas, p. ej. "siat:parametricas"}', function (string $tenant, string $artisan, Tenancy $tenancy) {
    $model = Tenant::where('slug', $tenant)->first();

    if ($model === null) {
        $this->error("No existe la empresa «{$tenant}».");

        return 1;
    }

    return $tenancy->run($model, fn () => Artisan::call($artisan, [], $this->output));
})->purpose('Corre un comando de artisan dentro de una empresa');
