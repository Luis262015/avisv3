<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiatCufdCode extends Model
{
    protected $fillable = [
        'store_id',
        'codigo',
        'codigo_control',
        'fecha_vigencia',
        'consecutivo',
        'estado',
    ];

    protected $casts = [
        'fecha_vigencia' => 'datetime',
        'consecutivo'    => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SiatInvoice::class, 'cufd_code_id');
    }

    public function isValid(): bool
    {
        return $this->estado === 'activo' && $this->fecha_vigencia->isFuture();
    }

    public function nextConsecutivo(): int
    {
        $this->increment('consecutivo');
        return $this->consecutivo;
    }
}
