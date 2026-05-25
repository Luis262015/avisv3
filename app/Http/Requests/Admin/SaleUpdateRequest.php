<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'payment_method'     => ['required', Rule::in(['cash', 'card', 'transfer', 'mixed'])],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'tax'                => ['nullable', 'numeric', 'min:0'],
            'amount_paid'        => ['required', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.01'],
            'items.*.price'      => ['required', 'numeric', 'min:0'],
            'items.*.discount'   => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
