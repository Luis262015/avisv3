<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'manager_id'  => ['nullable', 'exists:employees,id'],
            'is_active'   => ['boolean'],
        ];
    }
}
