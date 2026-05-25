<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'from_store_id'        => ['required', 'exists:stores,id'],
            'to_store_id'          => ['required', 'exists:stores,id', 'different:from_store_id'],
            'notes'                => ['nullable', 'string', 'max:500'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_store_id.different' => 'La tienda destino debe ser diferente a la tienda origen.',
        ];
    }
}
