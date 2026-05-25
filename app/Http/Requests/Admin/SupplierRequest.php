<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'address'      => ['nullable', 'string', 'max:500'],
            'rfc'          => ['nullable', 'string', 'max:20'],
            'is_active'    => ['boolean'],
            'notes'        => ['nullable', 'string'],
        ];
    }
}
