<?php

use App\Http\Controllers\Platform\AdminController;
use App\Http\Controllers\Platform\AuthController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\PaymentController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SettingController;
use App\Http\Controllers\Platform\TenantController;
use Illuminate\Support\Facades\Route;

/*
| Panel de la plataforma: todo lo que tiene que ver con rentar el sistema.
| Vive solo en el dominio central y entra con su propio guard.
*/
Route::middleware('central')->prefix('plataforma')->name('platform.')->group(function () {
    Route::middleware('guest:platform')->group(function () {
        Route::get('ingresar', [AuthController::class, 'create'])->name('login');
        Route::post('ingresar', [AuthController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:platform')->group(function () {
        Route::post('salir', [AuthController::class, 'destroy'])->name('logout');

        Route::get('/', DashboardController::class)->name('dashboard');

        Route::resource('empresas', TenantController::class)
            ->parameters(['empresas' => 'tenant'])
            ->names('tenants')
            ->except(['edit']);
        Route::post('empresas/{tenant}/suspender', [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('empresas/{tenant}/reactivar', [TenantController::class, 'reactivate'])->name('tenants.reactivate');
        Route::post('empresas/{tenant}/plan', [TenantController::class, 'changePlan'])->name('tenants.plan');
        Route::post('empresas/{tenant}/vencimiento', [TenantController::class, 'extend'])->name('tenants.extend');

        Route::resource('planes', PlanController::class)
            ->parameters(['planes' => 'plan'])
            ->names('plans')
            ->except(['show']);

        Route::get('pagos', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('pagos', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('pagos/{payment}/aprobar', [PaymentController::class, 'approve'])->name('payments.approve');
        Route::post('pagos/{payment}/rechazar', [PaymentController::class, 'reject'])->name('payments.reject');
        Route::get('pagos/{payment}/comprobante', [PaymentController::class, 'proof'])->name('payments.proof');

        Route::get('ajustes', [SettingController::class, 'edit'])->name('settings.edit');
        Route::post('ajustes', [SettingController::class, 'update'])->name('settings.update');
        Route::get('ajustes/qr', [SettingController::class, 'qr'])->name('settings.qr');

        Route::get('administradores', [AdminController::class, 'index'])->name('admins.index');
        Route::post('administradores', [AdminController::class, 'store'])->name('admins.store');
        Route::delete('administradores/{admin}', [AdminController::class, 'destroy'])->name('admins.destroy');
    });
});
