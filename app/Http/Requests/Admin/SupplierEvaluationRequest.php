<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SupplierEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_id'    => ['nullable', 'exists:purchases,id'],
            'overall_score'  => ['required', 'numeric', 'min:1', 'max:5'],
            'delivery_score' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'quality_score'  => ['nullable', 'numeric', 'min:1', 'max:5'],
            'price_score'    => ['nullable', 'numeric', 'min:1', 'max:5'],
            'comments'       => ['nullable', 'string', 'max:1000'],
            'evaluated_at'   => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
