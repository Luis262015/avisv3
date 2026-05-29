<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TrainingParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'       => ['required', 'in:enrolled,completed,failed,dropped'],
            'score'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'completed_at' => ['nullable', 'date'],
            'notes'        => ['nullable', 'string'],
        ];
    }
}
