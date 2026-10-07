<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\PlatformAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class AdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('platform/admins', [
            'admins' => PlatformAdmin::orderBy('name')->get(['id', 'name', 'email', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique(PlatformAdmin::class, 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        PlatformAdmin::create($data);

        return back()->with('success', "{$data['name']} ya puede entrar al panel.");
    }

    public function destroy(Request $request, PlatformAdmin $admin): RedirectResponse
    {
        if ($admin->is($request->user('platform'))) {
            return back()->with('error', 'No puedes quitarte a ti mismo.');
        }

        $admin->delete();

        return back()->with('success', "{$admin->name} ya no tiene acceso.");
    }
}
