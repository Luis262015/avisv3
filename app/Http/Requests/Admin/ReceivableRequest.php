<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReceivableRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'sale_id'        => ['nullable', 'exists:sales,id'],
            'customer_name'  => ['required', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'description'    => ['required', 'string', 'max:255'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'due_date'       => ['required', 'date'],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
