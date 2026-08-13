<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\SiatSetting;
use App\Services\Siat\SiatHomologacionCatalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Asignación del código de producto y la unidad de medida del SIN a un producto.
 *
 * Los códigos se contrastan contra las paramétricas cuando se pueden consultar:
 * un código que no está en la lista de la actividad hace que el SIN rechace la
 * factura entera, y descubrirlo aquí sale más barato que al emitir. Si el
 * catálogo no está disponible se acepta el número tal cual, porque bloquear la
 * homologación por una caída del SIN sería peor que permitir un valor a revisar.
 */
class SiatHomologationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $catalogo = app(SiatHomologacionCatalogo::class);
        $setting  = $this->setting();

        $codigos  = $setting ? $catalogo->codigosValidos($setting) : [];
        $unidades = $setting ? $catalogo->unidadesValidas($setting) : [];

        return [
            'setting_id' => ['required', 'integer', 'exists:siat_settings,id'],

            'codigo_producto_sin' => array_filter([
                'required', 'integer', 'min:1',
                $codigos !== [] ? Rule::in($codigos) : null,
            ]),

            'unidad_medida_sin' => array_filter([
                'required', 'integer', 'min:1',
                $unidades !== [] ? Rule::in($unidades) : null,
            ]),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo_producto_sin.in' => 'Ese código no está en la lista de Productos y Servicios '
                . 'de la actividad económica configurada.',
            'unidad_medida_sin.in'   => 'Esa unidad de medida no existe en la paramétrica del SIN.',
        ];
    }

    /**
     * La configuración marca contra qué catálogo se valida: los códigos dependen
     * de la actividad económica, y esa vive en la configuración.
     */
    public function setting(): ?SiatSetting
    {
        $id = $this->integer('setting_id');

        return $id > 0 ? SiatSetting::find($id) : null;
    }
}
