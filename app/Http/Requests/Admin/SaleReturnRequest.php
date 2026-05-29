<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_id'             => ['required', 'exists:sales,id'],
            'date'                => ['required', 'date'],
            'reason'              => ['nullable', 'string', 'max:255'],
            'refund_method'       => ['required', 'in:cash,card,transfer,store_credit'],
            'restock'             => ['boolean'],
            'notes'               => ['nullable', 'string'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['nullable', 'exists:sale_items,id'],
            'items.*.product_id'  => ['required', 'exists:products,id'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0'],
        ];
    }
}
