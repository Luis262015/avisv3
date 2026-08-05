<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReceiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $purchaseId = $this->route('purchase')?->id;

        return [
            'items'   => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'required',
                // La línea debe pertenecer a ESTA compra: sin este filtro se podía
                // recibir contra los ítems de otra compra.
                Rule::exists('purchase_items', 'id')->where('purchase_id', $purchaseId),
            ],
            'items.*.received_quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.id.exists' => 'Una de las líneas recibidas no pertenece a esta compra.',
        ];
    }
}
