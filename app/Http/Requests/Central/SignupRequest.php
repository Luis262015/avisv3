<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\BillingCycle;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Tenancy\TenantDatabaseManager;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::lower(trim((string) $this->input('slug'))),
            'owner_email' => Str::lower(trim((string) $this->input('owner_email'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => self::slugRules(),
            'nit' => ['nullable', 'string', 'regex:/^[0-9]{5,15}$/'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'string', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'plan' => ['required', Rule::exists(Plan::class, 'slug')->where('is_active', true)->where('is_public', true)],
            'cycle' => ['required', Rule::enum(BillingCycle::class)],
            'terms' => ['accepted'],
        ];
    }

    /**
     * Reglas del subdominio, compartidas con el alta desde el panel.
     *
     * @return list<mixed>
     */
    public static function slugRules(): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:30',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::notIn(config('tenancy.reserved_slugs')),
            Rule::unique(Tenant::class, 'slug'),
            // Una base con ese nombre que no figure como empresa no se pisa.
            function (string $attribute, mixed $value, Closure $fail): void {
                $databases = app(TenantDatabaseManager::class);

                if (is_string($value) && preg_match('/^[a-z0-9-]+$/', $value) && $databases->exists($databases->nameFor($value))) {
                    $fail('Esa dirección no está disponible.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Usa solo minúsculas, números y guiones, sin espacios.',
            'slug.not_in' => 'Esa dirección está reservada. Elige otra.',
            'slug.unique' => 'Esa dirección ya la usa otra empresa.',
            'nit.regex' => 'El NIT lleva solo números, entre 5 y 15 dígitos.',
            'terms.accepted' => 'Debes aceptar las condiciones para crear la cuenta.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre de la empresa',
            'slug' => 'dirección',
            'owner_name' => 'tu nombre',
            'owner_email' => 'correo',
            'phone' => 'teléfono',
            'city' => 'ciudad',
            'password' => 'contraseña',
        ];
    }
}
