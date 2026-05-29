<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id'    => ['required', 'exists:employees,id'],
            'date'           => ['required', 'date'],
            'check_in'       => ['nullable', 'date_format:H:i'],
            'check_out'      => ['nullable', 'date_format:H:i', 'after_or_equal:check_in'],
            'worked_hours'   => ['nullable', 'numeric', 'min:0', 'max:24'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'status'         => ['required', 'in:present,late,absent,leave,holiday,rest'],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
