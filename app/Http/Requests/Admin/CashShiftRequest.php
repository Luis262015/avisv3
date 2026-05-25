<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CashShiftRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cash_register_id' => ['required', 'exists:cash_registers,id'],
            'opening_amount'   => ['required', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string'],
        ];
    }
}
