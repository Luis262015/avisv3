<?php

use App\Http\Middleware\EnforcePlan;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\EnsureSubscriptionUsable;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Primero de todos: la sesión se abre ya dentro de la empresa.
        $middleware->web(prepend: [
            IdentifyTenant::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'tenant'             => EnsureTenantContext::class,
            'central'            => EnsureCentralContext::class,
            'subscribed'         => EnsureSubscriptionUsable::class,
            'plan'               => EnforcePlan::class,
        ]);

        // Antes que la autenticación: en el dominio equivocado la respuesta es
        // «aquí no es», no «inicia sesión».
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: EnsureTenantContext::class,
        );
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: EnsureCentralContext::class,
        );

        // El panel de la plataforma tiene su propio acceso.
        $platform = fn (Request $request): bool => $request->is('plataforma', 'plataforma/*');

        $middleware->redirectGuestsTo(fn (Request $request) => route($platform($request) ? 'platform.login' : 'login'));
        $middleware->redirectUsersTo(fn (Request $request) => route($platform($request) ? 'platform.dashboard' : 'dashboard'));

        // El aviso de la pasarela llega de su servidor, sin sesión ni token.
        $middleware->validateCsrfTokens(except: ['pagos/aviso/*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
