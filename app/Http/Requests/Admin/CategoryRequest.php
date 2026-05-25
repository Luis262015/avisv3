<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('category')?->id;
        return [
            'parent_id'   => ['nullable', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['boolean'],
        ];
    }
}
