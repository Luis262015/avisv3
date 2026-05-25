<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PayableRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'purchase_id' => ['nullable', 'exists:purchases,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'due_date'    => ['required', 'date'],
            'notes'       => ['nullable', 'string'],
        ];
    }
}
