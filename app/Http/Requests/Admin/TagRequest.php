<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('tag')?->id;
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('tags', 'name')->ignore($id)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('tags', 'slug')->ignore($id)],
        ];
    }
}
