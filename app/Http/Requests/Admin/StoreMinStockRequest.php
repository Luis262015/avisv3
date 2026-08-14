<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mínimo de existencias propio de una tienda.
 *
 * `min_stock` admite null a propósito: es la forma de decir «esta tienda no tiene
 * criterio propio, use el general del producto», y hace falta poder volver atrás
 * después de haber fijado uno.
 */
final class StoreMinStockRequest extends FormRequest
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
            'min_stock'  => ['present', 'nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'product_id' => 'producto',
            'store_id'   => 'tienda',
            'min_stock'  => 'mínimo',
        ];
    }
}
