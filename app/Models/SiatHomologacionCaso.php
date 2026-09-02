<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un caso de prueba de la homologación Fase I y cómo fue.
 *
 * El `caso` es un identificador estable —«e4-s24-pv1», por ejemplo— para que
 * reejecutar la matriz no duplique filas y se pueda reanudar donde se quedó.
 */
class SiatHomologacionCaso extends Model
{
    protected $table = 'siat_homologacion_casos';

    protected $fillable = [
        'store_id',
        'etapa',
        'caso',
        'punto_venta',
        'documento_sector',
        'tipo_factura',
        'motivo_evento',
        'catalogo',
        'cantidad',
        'tamano_lote',
        'completados',
        'estado',
        'codigo_resultado',
        'mensaje',
        'referencia',
        'ejecutado_at',
    ];

    protected $casts = [
        'etapa'            => 'integer',
        'punto_venta'      => 'integer',
        'documento_sector' => 'integer',
        'tipo_factura'     => 'integer',
        'motivo_evento'    => 'integer',
        'cantidad'         => 'integer',
        'tamano_lote'      => 'integer',
        'completados'      => 'integer',
        'ejecutado_at'     => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Lo que queda por hacer: nunca ejecutado, a medias o fallido. */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereIn('estado', ['pendiente', 'en_curso', 'fallido']);
    }

    public function restantes(): int
    {
        return max(0, $this->cantidad - $this->completados);
    }

    public function estaCompleto(): bool
    {
        return $this->completados >= $this->cantidad;
    }
}
