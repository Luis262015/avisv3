<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'         => ['required', 'in:warning,suspension,memo,recognition,complaint,other'],
            'severity'     => ['required', 'in:low,medium,high'],
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'date'         => ['required', 'date'],
            'action_taken' => ['nullable', 'string', 'max:255'],
        ];
    }
}
