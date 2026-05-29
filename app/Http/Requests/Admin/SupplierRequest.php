<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'contact_name'   => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'address'        => ['nullable', 'string', 'max:500'],
            'rfc'            => ['nullable', 'string', 'max:20'],
            'tax_id'         => ['nullable', 'string', 'max:20'],
            'payment_terms'  => ['nullable', 'string', 'max:100'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'website'        => ['nullable', 'url', 'max:255'],
            'bank_account'   => ['nullable', 'string', 'max:100'],
            'is_active'      => ['boolean'],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
