<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiatSetting extends Model
{
    protected $fillable = [
        'store_id',
        'nit',
        'razon_social',
        'municipio',
        'telefono',
        'direccion',
        'actividad_economica',
        'actividad_descripcion',
        'codigo_sucursal',
        'codigo_punto_venta',
        'nombre_punto_venta',
        'modalidad',
        'ambiente',
        'tipo_factura_default',
        'cuis',
        'cuis_fecha_solicitud',
        'token_api',
        'is_active',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'cuis_fecha_solicitud' => 'datetime',
        'modalidad'            => 'integer',
        'tipo_factura_default' => 'integer',
        'codigo_sucursal'      => 'integer',
        'codigo_punto_venta'   => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cufdCodes(): HasMany
    {
        return $this->hasMany(SiatCufdCode::class, 'store_id', 'store_id');
    }

    public function getModalidadLabelAttribute(): string
    {
        return match ($this->modalidad) {
            1 => 'En línea',
            2 => 'Fuera de línea',
            default => 'Desconocido',
        };
    }

    public function getAmbienteLabelAttribute(): string
    {
        return match ($this->ambiente) {
            'piloto'     => 'Piloto SIN',
            'produccion' => 'Producción SIN',
            'simulado'   => 'Simulado (sin conexión SIN)',
            default      => $this->ambiente,
        };
    }
}
