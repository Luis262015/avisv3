<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'provider'    => ['nullable', 'string', 'max:255'],
            'modality'    => ['required', 'in:internal,external,online'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'hours'       => ['required', 'numeric', 'min:0'],
            'cost'        => ['required', 'numeric', 'min:0'],
            'status'      => ['required', 'in:planned,in_progress,completed,cancelled'],
            'notes'       => ['nullable', 'string'],

            'employee_ids'   => ['array'],
            'employee_ids.*' => ['exists:employees,id'],
        ];
    }
}
