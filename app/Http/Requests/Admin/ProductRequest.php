<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('product')?->id;
        return [
            'name'            => ['required', 'string', 'max:255'],
            'slug'            => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($id)->whereNull('deleted_at')],
            'sku'             => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($id)->whereNull('deleted_at')],
            'barcode'         => ['nullable', 'string', 'max:100'],
            'category_id'     => ['nullable', 'exists:categories,id'],
            'brand_id'        => ['nullable', 'exists:brands,id'],
            'description'     => ['nullable', 'string'],
            'price'           => ['required', 'numeric', 'min:0'],
            'cost'            => ['required', 'numeric', 'min:0'],
            // `stock` no se acepta desde aquí: es la suma de las existencias por
            // tienda y solo lo escribe InventoryService. Aceptarlo permitía crear
            // un producto con 25 unidades que ninguna tienda tenía, visibles en el
            // listado pero imposibles de vender.
            'min_stock'       => ['required', 'integer', 'min:0'],
            'unit'            => ['required', 'string', 'max:20'],
            'status'          => ['required', Rule::in(['active', 'inactive', 'out_of_stock'])],
            'track_inventory' => ['boolean'],
            'tags'            => ['nullable', 'array'],
            'tags.*'          => ['exists:tags,id'],
            'images'          => ['nullable', 'array'],
            'images.*'        => ['image', 'max:2048'],
        ];
    }
}
