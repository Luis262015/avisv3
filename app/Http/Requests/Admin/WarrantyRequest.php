<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WarrantyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_id'       => ['nullable', 'exists:sales,id'],
            'sale_item_id'  => ['nullable', 'exists:sale_items,id'],
            'product_id'    => ['required', 'exists:products,id'],
            'customer_id'   => ['nullable', 'exists:customers,id'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
            'terms'         => ['nullable', 'string'],
            'status'        => ['nullable', 'in:active,expired,void'],
        ];
    }
}
