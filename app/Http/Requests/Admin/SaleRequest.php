<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // El turno debe estar abierto: vender contra un turno cerrado descuadra
            // un arqueo ya calculado.
            'cash_shift_id'        => ['required', Rule::exists('cash_shifts', 'id')->where('status', 'open')],
            'customer_id'          => ['nullable', 'exists:customers,id'],
            'promotion_id'         => ['nullable', 'exists:promotions,id'],
            'discount'             => ['nullable', 'numeric', 'min:0'],
            'tax'                  => ['nullable', 'numeric', 'min:0'],
            'amount_paid'          => ['required', 'numeric', 'min:0'],
            'payment_method'       => ['required', Rule::in(['cash', 'card', 'transfer', 'mixed'])],
            'notes'                => ['nullable', 'string'],
            // Los combos aplicados: el POS los expande en líneas de producto, así
            // que sin declararlos aquí la venta no dejaría rastro de haberlos usado.
            'combos'               => ['nullable', 'array'],
            'combos.*.promotion_id' => ['required', 'exists:promotions,id'],
            'combos.*.quantity'    => ['nullable', 'integer', 'min:1'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.01'],
            'items.*.price'        => ['required', 'numeric', 'min:0'],
            'items.*.discount'     => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'cash_shift_id.exists' => 'El turno de caja seleccionado no está abierto.',
        ];
    }
}
