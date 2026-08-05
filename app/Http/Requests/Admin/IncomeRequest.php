<?php

namespace App\Http\Requests\Admin;

use App\Models\CashShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Un ingreso en efectivo entra al cajón: sin turno no se refleja en el
            // arqueo. Tarjeta y transferencia no tocan la caja.
            'cash_shift_id'  => [
                Rule::requiredIf(fn() => $this->input('payment_method') === 'cash'),
                'nullable',
                Rule::exists('cash_shifts', 'id')->where('status', 'open'),
            ],
            // Siempre atribuible a una tienda, tenga turno o no: sin esto los cobros
            // por tarjeta o transferencia desaparecían al filtrar reportes por tienda.
            'store_id'       => ['required', 'exists:stores,id'],
            'category'       => ['required', 'string', 'max:100'],
            'description'    => ['required', 'string', 'max:255'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,transfer'],
            'reference'      => ['nullable', 'string', 'max:100'],
            'date'           => ['required', 'date'],
            'notes'          => ['nullable', 'string'],
        ];
    }

    /**
     * Con turno seleccionado la tienda queda determinada por su caja; se rellena
     * sola para que el usuario no pueda contradecirla.
     */
    protected function prepareForValidation(): void
    {
        if ($shiftId = $this->input('cash_shift_id')) {
            $storeId = CashShift::whereKey($shiftId)
                ->with('cashRegister:id,store_id')
                ->first()?->cashRegister?->store_id;

            if ($storeId) {
                $this->merge(['store_id' => $storeId]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'cash_shift_id.required' => 'Un ingreso en efectivo debe registrarse contra un turno de caja abierto.',
            'cash_shift_id.exists'   => 'El turno de caja seleccionado no está abierto.',
            'store_id.required'      => 'Debe indicar la tienda a la que corresponde el ingreso.',
        ];
    }
}
