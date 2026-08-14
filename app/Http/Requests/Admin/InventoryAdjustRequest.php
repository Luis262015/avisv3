<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajuste manual de existencias.
 *
 * `store_id` es **obligatorio**: las existencias viven por tienda y un ajuste sin
 * tienda no tiene dónde aplicarse. Antes era opcional y ese caso terminaba
 * escribiendo el total global, descuadrándolo de la suma de sus tiendas.
 */
final class InventoryAdjustRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'store_id'   => ['required', 'integer', 'exists:stores,id'],
            'new_stock'  => ['required', 'integer', 'min:0'],
            'reason'     => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'Indique en qué tienda se ajustan las existencias.',
            'reason.required'   => 'Un ajuste sin motivo no se puede auditar después.',
        ];
    }

    public function attributes(): array
    {
        return [
            'product_id' => 'producto',
            'store_id'   => 'tienda',
            'new_stock'  => 'existencias',
            'reason'     => 'motivo',
        ];
    }
}
