<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReceivablePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,transfer'],
            'date'           => ['required', 'date'],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
