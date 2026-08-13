<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un envío por lote al SIN, ya sea de contingencia (paquete) o masivo.
 */
class SiatPaquete extends Model
{
    protected $table = 'siat_paquetes';

    protected $fillable = [
        'store_id',
        'evento_id',
        'tipo',
        'gestion',
        'periodo',
        'cantidad_facturas',
        'hash_archivo',
        'codigo_recepcion',
        'codigo_estado',
        'estado',
        'mensaje_error',
        'enviado_at',
        'validado_at',
    ];

    protected $casts = [
        'cantidad_facturas' => 'integer',
        'gestion'           => 'integer',
        'periodo'           => 'integer',
        'codigo_estado'     => 'integer',
        'enviado_at'        => 'datetime',
        'validado_at'       => 'datetime',
    ];

    protected $appends = ['estado_label'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(SiatEvento::class, 'evento_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SiatInvoice::class, 'paquete_id');
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            'pendiente' => 'Sin enviar',
            'enviado'   => 'Enviado, pendiente de validación',
            'validado'  => 'Validado por el SIN',
            'rechazado' => 'Rechazado por el SIN',
            default     => $this->estado,
        };
    }
}
