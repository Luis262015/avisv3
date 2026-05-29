<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FinancialReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period'   => ['nullable', 'in:month,quarter,year,custom'],
            'from'     => ['nullable', 'date', 'required_if:period,custom'],
            'to'       => ['nullable', 'date', 'after_or_equal:from', 'required_if:period,custom'],
            'store_id' => ['nullable', 'exists:stores,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'from' => 'fecha desde',
            'to'   => 'fecha hasta',
        ];
    }
}
