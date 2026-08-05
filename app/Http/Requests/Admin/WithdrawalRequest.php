<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WithdrawalRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Un retiro es por definición efectivo que sale del cajón: siempre
            // pertenece a un turno, y ese turno debe seguir abierto.
            'cash_shift_id' => ['required', Rule::exists('cash_shifts', 'id')->where('status', 'open')],
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'reason'        => ['required', 'string', 'max:255'],
            'date'          => ['required', 'date'],
            'authorized_by' => ['nullable', 'string', 'max:150'],
            'notes'         => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'cash_shift_id.required' => 'Debe indicar el turno de caja del que sale el efectivo.',
            'cash_shift_id.exists'   => 'El turno de caja seleccionado no está abierto.',
        ];
    }
}
