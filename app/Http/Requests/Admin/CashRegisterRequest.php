<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CashRegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'store_id'  => ['required', 'exists:stores,id'],
            'name'      => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
