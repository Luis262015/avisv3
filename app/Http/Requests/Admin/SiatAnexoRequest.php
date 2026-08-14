<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Números de serie e IMEI que se declararán al SIN por una factura.
 *
 * El formulario pinta una casilla por unidad vendida, así que llegan huecos
 * mientras la lista se completa: se descartan aquí para poder guardar a medias y
 * terminar después. Lo que no se admite es repetir un código, porque sería la
 * misma unidad física declarada dos veces.
 *
 * A qué línea pertenece cada código y de qué tipo es lo decide el catálogo, no el
 * formulario: {@see \App\Services\SiatService::guardarAnexos()}.
 */
class SiatAnexoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'anexos'                => ['present', 'array'],
            'anexos.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            // `distinct` compara contra el resto del envío; el índice único de
            // `siat_anexos` cubre además el caso de dos envíos a la vez.
            'anexos.*.codigo'       => ['required', 'string', 'max:100', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'anexos.*.codigo.distinct' => 'Ese número de serie o IMEI está repetido en la factura.',
            'anexos.*.codigo.max'      => 'Un número de serie o IMEI no puede pasar de 100 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $anexos = collect($this->input('anexos', []))
            ->map(fn ($anexo): array => [
                'sale_item_id' => $anexo['sale_item_id'] ?? null,
                'codigo'       => trim((string) ($anexo['codigo'] ?? '')),
            ])
            ->reject(fn (array $anexo): bool => $anexo['codigo'] === '')
            ->values()
            ->all();

        $this->merge(['anexos' => $anexos]);
    }
}
