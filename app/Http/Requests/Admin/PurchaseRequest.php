<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'supplier_id'          => ['nullable', 'exists:suppliers,id'],
            'store_id'             => ['nullable', 'exists:stores,id'],
            'date'                 => ['required', 'date'],
            'tax'                  => ['nullable', 'numeric', 'min:0'],
            'notes'                => ['nullable', 'string'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.01'],
            'items.*.cost'         => ['required', 'numeric', 'min:0'],
        ];
    }
}
