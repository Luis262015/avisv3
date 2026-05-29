<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PayrollItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worked_days'      => ['required', 'integer', 'min:0', 'max:31'],
            'antiquity_bonus'  => ['required', 'numeric', 'min:0'],
            'overtime_amount'  => ['required', 'numeric', 'min:0'],
            'other_earnings'   => ['required', 'numeric', 'min:0'],
            'loans_deduction'  => ['required', 'numeric', 'min:0'],
            'other_deductions' => ['required', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string'],
        ];
    }
}
