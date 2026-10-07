<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrada de un solo uso tras el alta: quien acaba de registrarse en el sitio
 * llega aquí ya dentro de su empresa, sin volver a escribir su contraseña.
 */
final class WelcomeController extends Controller
{
    public function __invoke(Request $request, string $token, Tenancy $tenancy): RedirectResponse
    {
        $tenant = $tenancy->tenant();

        $valid = $tenant !== null
            && $tenant->access_token !== null
            && $tenant->access_token_expires_at?->isFuture()
            && hash_equals($tenant->access_token, hash('sha256', $token));

        if (! $valid) {
            return redirect()->route('login');
        }

        $tenant->forceFill(['access_token' => null, 'access_token_expires_at' => null])->save();

        $user = User::where('email', $tenant->owner_email)->first();

        if ($user === null) {
            return redirect()->route('login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', "Tu espacio está listo. Bienvenido a {$tenant->name}.");
    }
}
