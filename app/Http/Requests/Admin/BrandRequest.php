<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('brand')?->id;
        return [
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255', Rule::unique('brands', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['boolean'],
        ];
    }
}
