<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * «Ingresar» desde el sitio público: cada empresa entra por su propia
 * dirección, así que primero hay que saber cuál es.
 */
final class AccessController extends Controller
{
    public function create(Tenancy $tenancy): Response|RedirectResponse
    {
        // Una sola empresa: no hay nada que preguntar.
        if (! $tenancy->enabled()) {
            return redirect()->route('login');
        }

        return Inertia::render('central/access', [
            'baseDomain' => config('tenancy.base_domain'),
        ]);
    }

    public function store(Request $request): HttpResponse
    {
        $slug = Str::of((string) $request->input('slug'))
            ->lower()
            ->trim()
            // Hay quien pega la dirección entera.
            ->replaceMatches('#^https?://#', '')
            ->before('/')
            ->before('.'.config('tenancy.base_domain'))
            ->toString();

        $tenant = Tenant::where('slug', $slug)
            ->whereIn('status', [TenantStatus::Active, TenantStatus::Suspended])
            ->first();

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'slug' => 'No encontramos una empresa con esa dirección. Revisa cómo está escrita.',
            ]);
        }

        return Inertia::location($tenant->url('login'));
    }
}
