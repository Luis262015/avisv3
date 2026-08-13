<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un evento significativo: el corte durante el cual se facturó sin conexión.
 */
class SiatEvento extends Model
{
    protected $table = 'siat_eventos';

    protected $fillable = [
        'store_id',
        'cufd_code_id',
        'codigo_motivo_evento',
        'descripcion',
        'cafc',
        'fecha_inicio',
        'fecha_fin',
        'codigo_recepcion_evento',
        'estado',
        'mensaje_error',
    ];

    protected $casts = [
        'fecha_inicio'         => 'datetime',
        'fecha_fin'            => 'datetime',
        'codigo_motivo_evento' => 'integer',
    ];

    protected $appends = ['estado_label'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cufdCode(): BelongsTo
    {
        return $this->belongsTo(SiatCufdCode::class, 'cufd_code_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SiatInvoice::class, 'evento_id');
    }

    public function paquetes(): HasMany
    {
        return $this->hasMany(SiatPaquete::class, 'evento_id');
    }

    /** El corte sigue en curso: todo lo que se venda se emite fuera de línea. */
    public function scopeAbierto(Builder $query): Builder
    {
        return $query->where('estado', 'abierto');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto';
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            'abierto'    => 'Corte en curso',
            'cerrado'    => 'Cerrado, sin declarar al SIN',
            'registrado' => 'Declarado al SIN',
            default      => $this->estado,
        };
    }
}
