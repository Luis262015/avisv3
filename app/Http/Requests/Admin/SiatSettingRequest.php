<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SiatSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'              => ['required', 'exists:stores,id'],
            'nit'                   => ['required', 'string', 'max:13', 'regex:/^\d+$/'],
            'razon_social'          => ['required', 'string', 'max:150'],
            'municipio'             => ['required', 'string', 'max:100'],
            'telefono'              => ['nullable', 'string', 'max:30'],
            'direccion'             => ['nullable', 'string', 'max:250'],
            'actividad_economica'   => ['required', 'string', 'max:10', 'regex:/^\d+$/'],
            'actividad_descripcion' => ['nullable', 'string', 'max:200'],
            'codigo_sucursal'       => ['required', 'integer', 'min:0', 'max:9999'],
            'codigo_punto_venta'    => ['required', 'integer', 'min:0', 'max:9999'],
            'nombre_punto_venta'    => ['required', 'string', 'max:50'],
            'modalidad'             => ['required', 'integer', 'in:1,2'],
            'ambiente'              => ['required', 'string', 'in:piloto,produccion,simulado'],
            'tipo_factura_default'  => ['required', 'integer', 'in:1,2'],
            'cuis'                  => ['nullable', 'string', 'max:512'],
            'token_api'             => ['nullable', 'string', 'max:512'],
            'is_active'             => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nit'                  => 'NIT',
            'razon_social'         => 'razón social',
            'actividad_economica'  => 'actividad económica',
            'codigo_sucursal'      => 'código de sucursal',
            'codigo_punto_venta'   => 'código de punto de venta',
            'nombre_punto_venta'   => 'nombre del punto de venta',
            'tipo_factura_default' => 'tipo de factura por defecto',
        ];
    }
}
