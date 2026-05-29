<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'document_type'   => ['required', 'in:ci,nit,none'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:255'],
            'address'         => ['nullable', 'string', 'max:500'],
            'notes'           => ['nullable', 'string'],
            'is_active'       => ['boolean'],
        ];
    }
}
