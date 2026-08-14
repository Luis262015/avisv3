<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un número de serie o IMEI declarado al SIN dentro de una factura.
 *
 * Una fila por unidad física vendida: si la línea lleva tres portátiles, hacen
 * falta tres series. No se envía sola —el SIN recibe la lista entera de la
 * factura en una sola llamada—, así que el estado del envío vive en la factura y
 * no aquí.
 */
class SiatAnexo extends Model
{
    /** Paramétrica del SIN para `tipoCodigo`. */
    public const TIPO_SERIE = 1;
    public const TIPO_IMEI  = 2;

    /** @var array<int, string> */
    public const TIPOS = [
        self::TIPO_SERIE => 'Nº de serie',
        self::TIPO_IMEI  => 'IMEI',
    ];

    protected $table = 'siat_anexos';

    protected $fillable = [
        'siat_invoice_id',
        'sale_item_id',
        'codigo',
        'tipo_codigo',
    ];

    protected $casts = [
        'tipo_codigo' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SiatInvoice::class, 'siat_invoice_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    public function getTipoCodigoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo_codigo] ?? 'Desconocido';
    }
}
