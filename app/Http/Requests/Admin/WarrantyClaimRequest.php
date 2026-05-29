<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WarrantyClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'        => ['required', 'date'],
            'description' => ['required', 'string'],
            'status'      => ['nullable', 'in:open,in_progress,resolved,rejected'],
            'resolution'  => ['nullable', 'string'],
        ];
    }
}
