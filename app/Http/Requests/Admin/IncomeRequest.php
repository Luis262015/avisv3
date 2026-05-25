<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IncomeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cash_shift_id'  => ['nullable', 'exists:cash_shifts,id'],
            'category'       => ['required', 'string', 'max:100'],
            'description'    => ['required', 'string', 'max:255'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,transfer'],
            'reference'      => ['nullable', 'string', 'max:100'],
            'date'           => ['required', 'date'],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
