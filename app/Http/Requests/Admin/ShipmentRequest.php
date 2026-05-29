<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'carrier'         => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'address'         => ['nullable', 'string', 'max:500'],
            'cost'            => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string'],
        ];
    }
}
