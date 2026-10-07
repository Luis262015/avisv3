<?php

use App\Http\Controllers\Central\AccessController;
use App\Http\Controllers\Central\GatewayWebhookController;
use App\Http\Controllers\Central\HomeController;
use App\Http\Controllers\Central\SignupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Tenant\SubscriptionController;
use App\Http\Controllers\Tenant\WelcomeController;
use Illuminate\Support\Facades\Route;

// En el dominio central es el sitio público; en el de una empresa, su entrada.
Route::get('/', HomeController::class)->name('home');

// ── Sitio público ──────────────────────────────────────────────────────────
Route::middleware('central')->group(function () {
    Route::get('registro', [SignupController::class, 'create'])->name('central.signup');
    Route::post('registro', [SignupController::class, 'store'])
        ->middleware('throttle:6,10')
        ->name('central.signup.store');

    Route::get('ingresar', [AccessController::class, 'create'])->name('central.access');
    Route::post('ingresar', [AccessController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('central.access.store');

    Route::post('pagos/aviso/{gateway}', GatewayWebhookController::class)->name('central.gateway.webhook');
});

// ── Sistema de cada empresa ────────────────────────────────────────────────
Route::middleware('tenant')->group(function () {
    Route::get('bienvenida/{token}', WelcomeController::class)
        ->middleware('throttle:10,1')
        ->name('tenant.welcome');

    Route::middleware('auth')->group(function () {
        Route::get('dashboard', DashboardController::class)
            ->middleware('subscribed')
            ->name('dashboard');

        // Fuera de «subscribed» a propósito: es adonde se llega cuando la
        // renta venció, y desde donde se regulariza.
        Route::get('suscripcion', [SubscriptionController::class, 'show'])->name('subscription.show');
        Route::get('suscripcion/qr', [SubscriptionController::class, 'qr'])->name('subscription.qr');

        Route::middleware('role:admin')->group(function () {
            Route::post('suscripcion/pagos', [SubscriptionController::class, 'storePayment'])->name('subscription.payments.store');
            Route::post('suscripcion/pago-en-linea', [SubscriptionController::class, 'payOnline'])->name('subscription.pay-online');
            Route::post('suscripcion/plan-gratuito', [SubscriptionController::class, 'switchToFree'])->name('subscription.free');
        });
    });

    require __DIR__.'/admin.php';
    require __DIR__.'/settings.php';
    require __DIR__.'/auth.php';
});

require __DIR__.'/platform.php';
