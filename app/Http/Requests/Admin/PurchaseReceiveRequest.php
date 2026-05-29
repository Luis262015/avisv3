<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseReceiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.id'                     => ['required', 'exists:purchase_items,id'],
            'items.*.received_quantity'      => ['required', 'numeric', 'min:0'],
        ];
    }
}
