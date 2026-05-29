<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $promotionId = $this->route('promotion')?->id;

        return [
            'name'         => ['required', 'string', 'max:255'],
            'code'         => ['nullable', 'string', 'max:50', Rule::unique('promotions', 'code')->ignore($promotionId)],
            'type'         => ['required', 'in:percentage,fixed,buy_x_get_y,combo'],
            'value'        => ['nullable', 'numeric', 'min:0', Rule::requiredIf(fn() => in_array($this->type, ['percentage', 'fixed']))],
            'combo_price'  => ['nullable', 'numeric', 'min:0', Rule::requiredIf(fn() => $this->type === 'combo')],
            'scope'        => ['required', 'in:all,product,category'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'buy_qty'      => ['nullable', 'integer', 'min:1', Rule::requiredIf(fn() => $this->type === 'buy_x_get_y')],
            'get_qty'      => ['nullable', 'integer', 'min:1', Rule::requiredIf(fn() => $this->type === 'buy_x_get_y')],
            'starts_at'    => ['nullable', 'date'],
            'ends_at'      => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit'  => ['nullable', 'integer', 'min:1'],
            'is_active'    => ['boolean'],
            'notes'        => ['nullable', 'string'],
            'product_ids'   => ['array', Rule::requiredIf(fn() => $this->scope === 'product' && $this->type !== 'combo')],
            'product_ids.*' => ['exists:products,id'],
            'category_ids'   => ['array', Rule::requiredIf(fn() => $this->scope === 'category' && $this->type !== 'combo')],
            'category_ids.*' => ['exists:categories,id'],
            'combo_items'                => ['array', Rule::requiredIf(fn() => $this->type === 'combo'), Rule::when($this->type === 'combo', ['min:1'])],
            'combo_items.*.product_id'   => ['required_with:combo_items', 'exists:products,id'],
            'combo_items.*.quantity'     => ['required_with:combo_items', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'combo_items.required' => 'Agrega al menos un producto al combo.',
            'combo_items.min'      => 'Agrega al menos un producto al combo.',
            'combo_price.required' => 'Indica el precio del combo.',
        ];
    }
}
