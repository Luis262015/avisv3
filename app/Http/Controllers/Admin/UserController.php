<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Las personas que entran al sistema de la empresa y con qué rol.
 */
final class UserController extends Controller
{
    private const ROLE_LABELS = [
        'admin' => 'Administrador',
        'operador' => 'Operador',
        'vendedor' => 'Vendedor',
    ];

    public function index(): Response
    {
        return Inertia::render('admin/users/index', [
            'users' => User::with('roles:id,name')->orderBy('name')->paginate(20)->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
                'role_label' => self::ROLE_LABELS[$user->roles->first()?->name] ?? 'Sin rol',
                'created_at' => $user->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/form', ['user' => null, 'roles' => $this->roles()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->email_verified_at = now();
        $user->save();
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('success', "{$user->name} ya puede entrar al sistema.");
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/form', [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->roles->first()?->name],
            'roles' => $this->roles(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($data['role'] !== 'admin' && $this->isLastAdmin($user)) {
            return back()->with('error', 'La empresa necesita al menos un administrador.');
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('success', "Datos de {$user->name} guardados.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta desde aquí.');
        }

        if ($this->isLastAdmin($user)) {
            return back()->with('error', 'La empresa necesita al menos un administrador.');
        }

        // Ventas, compras y turnos guardan quién los hizo: con historial, la
        // cuenta no se puede borrar sin romper ese rastro.
        if ($user->sales()->exists() || $user->purchases()->exists() || $user->cashShifts()->exists()) {
            return back()->with('error', "{$user->name} tiene ventas, compras o turnos registrados y no se puede eliminar. Cámbiale la contraseña para cortarle el acceso.");
        }

        $user->delete();

        return back()->with('success', "{$user->name} ya no tiene acceso.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(array_keys(self::ROLE_LABELS))],
        ]);
    }

    private function isLastAdmin(User $user): bool
    {
        return $user->hasRole('admin') && User::role('admin')->count() <= 1;
    }

    /** @return list<array{value: string, label: string}> */
    private function roles(): array
    {
        return Role::whereIn('name', array_keys(self::ROLE_LABELS))->pluck('name')
            ->map(fn (string $name) => ['value' => $name, 'label' => self::ROLE_LABELS[$name]])
            ->values()
            ->all();
    }
}
