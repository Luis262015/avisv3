<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un punto de venta declarado ante el SIN.
 *
 * El código **lo asigna el SIN** al registrarlo (`registroPuntoVenta` no admite
 * pedir uno concreto), salvo el 0, que es la casa matriz y existe sin registro.
 * Cada punto de venta lleva su propio CUIS y su propia cadena de CUFD, y por
 * tanto su propio correlativo de facturas.
 */
class SiatPuntoVenta extends Model
{
    protected $table = 'siat_puntos_venta';

    protected $fillable = [
        'store_id',
        'codigo',
        'codigo_sucursal',
        'nombre',
        'descripcion',
        'tipo',
        'cuis',
        'cuis_fecha_solicitud',
        'cuis_fecha_vigencia',
        'es_principal',
        'estado',
        'cerrado_at',
    ];

    /** El CUIS es una credencial: no tiene por qué llegar al navegador. */
    protected $hidden = ['cuis'];

    protected $appends = ['tiene_cuis'];

    protected $casts = [
        'codigo'               => 'integer',
        'codigo_sucursal'      => 'integer',
        'tipo'                 => 'integer',
        'es_principal'         => 'boolean',
        'cuis_fecha_solicitud' => 'datetime',
        'cuis_fecha_vigencia'  => 'datetime',
        'cerrado_at'           => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cufdCodes(): HasMany
    {
        return $this->hasMany(SiatCufdCode::class, 'punto_venta_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', 'activo');
    }

    public function getTieneCuisAttribute(): bool
    {
        return filled($this->cuis);
    }

    /**
     * Si el CUIS sigue vigente. El SIN no permite volver a consultarlo —responde
     * 980— así que perderlo obliga a dar de baja el punto de venta en el Portal.
     */
    public function cuisVigente(): bool
    {
        return filled($this->cuis)
            && ($this->cuis_fecha_vigencia === null || $this->cuis_fecha_vigencia->isFuture());
    }

    public function getEsCasaMatrizAttribute(): bool
    {
        return $this->codigo === 0;
    }
}
