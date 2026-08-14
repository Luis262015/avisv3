<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Solo tiendas activas: filtrar por una dada de baja daría un panel
            // vacío sin explicar por qué.
            'store_id' => ['nullable', 'integer', Rule::exists('stores', 'id')->where('is_active', true)],
        ];
    }

    public function attributes(): array
    {
        return ['store_id' => 'tienda'];
    }

    /** La tienda seleccionada, o null para agregar todas. */
    public function storeId(): ?int
    {
        $id = $this->validated()['store_id'] ?? null;

        return $id === null ? null : (int) $id;
    }
}
