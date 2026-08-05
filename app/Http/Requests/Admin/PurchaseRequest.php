<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'          => ['nullable', 'exists:suppliers,id'],
            // Obligatorio: el inventario se lleva por tienda (store_stocks) y una
            // compra sin tienda entra por la vía global, que luego queda pisada
            // al recalcularse product.stock como suma de las tiendas.
            'store_id'             => ['required', 'exists:stores,id'],
            'purchase_order_id'    => ['nullable', 'exists:purchase_orders,id'],
            'date'                 => ['required', 'date'],
            'invoice_number'       => ['nullable', 'string', 'max:50'],
            'invoice_date'         => ['nullable', 'date'],
            'tax'                  => ['nullable', 'numeric', 'min:0'],
            'notes'                => ['nullable', 'string'],
            'audit_notes'          => ['nullable', 'string'],
            'items'                => ['required', 'array', 'min:1'],
            // Se permite repetir producto: son lotes distintos con costos distintos.
            // El costo promedio ponderado los mezcla línea por línea al recibirlos.
            'items.*.product_id'   => ['required', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.01'],
            'items.*.cost'         => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required'    => 'Debe seleccionar la tienda que recibirá la mercadería.',
            'items.required'       => 'Agregue al menos un producto a la compra.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a cero.',
        ];
    }
}
