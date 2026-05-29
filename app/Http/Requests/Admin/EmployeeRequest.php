<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')?->id;

        return [
            'user_id'                 => ['nullable', 'exists:users,id'],
            'department_id'           => ['nullable', 'exists:departments,id'],
            'employee_code'           => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_code')->ignore($employeeId)],

            'first_name'              => ['required', 'string', 'max:255'],
            'last_name'               => ['required', 'string', 'max:255'],
            'document_type'           => ['required', 'in:ci,passport,other'],
            'document_number'         => ['nullable', 'string', 'max:30'],
            'birth_date'              => ['nullable', 'date'],
            'gender'                  => ['nullable', 'in:male,female,other'],
            'marital_status'          => ['nullable', 'in:single,married,divorced,widowed,free_union'],
            'nationality'             => ['nullable', 'string', 'max:255'],
            'phone'                   => ['nullable', 'string', 'max:30'],
            'email'                   => ['nullable', 'email', 'max:255'],
            'address'                 => ['nullable', 'string', 'max:500'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],

            'position'                => ['required', 'string', 'max:255'],
            'hire_date'               => ['required', 'date'],
            'termination_date'        => ['nullable', 'date', 'after_or_equal:hire_date'],
            'contract_type'           => ['required', 'in:indefinite,fixed_term,part_time,intern,services'],
            'status'                  => ['required', 'in:active,on_leave,suspended,terminated'],
            'base_salary'             => ['required', 'numeric', 'min:0'],

            'bank_name'               => ['nullable', 'string', 'max:255'],
            'bank_account'            => ['nullable', 'string', 'max:100'],
            'afp_name'                => ['nullable', 'string', 'max:255'],
            'afp_number'              => ['nullable', 'string', 'max:60'],
            'cuns'                    => ['nullable', 'string', 'max:60'],
            'notes'                   => ['nullable', 'string'],
        ];
    }
}
