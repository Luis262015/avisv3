<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Central\Plan;
use App\Models\Central\PlatformSetting;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends Controller
{
    public function __invoke(Request $request, Tenancy $tenancy): Response|RedirectResponse
    {
        // En el subdominio de una empresa no hay sitio público: se entra.
        if ($tenancy->initialized()) {
            return redirect()->route($request->user() ? 'dashboard' : 'login');
        }

        return Inertia::render('welcome', [
            'plans' => PlanResource::collection(Plan::offered()->get())->resolve(),
            'modules' => config('plans.modules'),
            'signupEnabled' => $tenancy->enabled(),
            'contact' => Arr::only(PlatformSetting::values(), ['support_email', 'support_whatsapp']),
        ]);
    }
}
