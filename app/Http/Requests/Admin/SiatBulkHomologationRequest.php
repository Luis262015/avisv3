<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/**
 * Homologación en bloque: el mismo código del SIN para varios productos.
 *
 * Es lo habitual en un catálogo real, donde decenas de artículos caen bajo el
 * mismo código de la paramétrica (que es mucho más grueso que un SKU).
 */
class SiatBulkHomologationRequest extends SiatHomologationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'product_ids'   => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);
    }
}
