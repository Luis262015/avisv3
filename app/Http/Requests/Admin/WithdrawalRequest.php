<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cash_shift_id' => ['nullable', 'exists:cash_shifts,id'],
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'reason'        => ['required', 'string', 'max:255'],
            'date'          => ['required', 'date'],
            'authorized_by' => ['nullable', 'string', 'max:150'],
            'notes'         => ['nullable', 'string'],
        ];
    }
}
