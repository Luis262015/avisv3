<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Nota de Crédito-Débito emitida al SIN (documento sector 24 o 47).
 *
 * Ajusta una factura ya validada: devuelve parte o todo su importe y revierte el
 * crédito fiscal correspondiente. Nace de una devolución de venta y tiene su
 * propio correlativo y su propio CUF.
 */
class SiatNota extends Model
{
    public const SECTOR_NOTA           = 24;
    public const SECTOR_NOTA_DESCUENTO = 47;

    protected $fillable = [
        'store_id',
        'sale_return_id',
        'siat_invoice_id',
        'cufd_code_id',
        'documento_sector',
        'numero_nota',
        'fecha_emision',
        'cuf',
        'cufd',
        'nit_ci',
        'tipo_doc_identidad',
        'codigo_excepcion',
        'nombre_razon_social',
        'complemento',
        'monto_total_original',
        'monto_total_devuelto',
        'monto_descuento',
        'monto_efectivo',
        'descuento_adicional',
        'estado',
        'codigo_recepcion',
        'codigo_qr',
        'mensaje_error',
        'enviado_at',
        'anulado_at',
        'motivo_anulacion',
    ];

    /** Las pantallas los muestran siempre; sin esto llegarían vacíos a Inertia. */
    protected $appends = ['estado_label', 'sector_label'];

    protected $casts = [
        'documento_sector'     => 'integer',
        'numero_nota'          => 'integer',
        'tipo_doc_identidad'   => 'integer',
        'codigo_excepcion'     => 'integer',
        'motivo_anulacion'     => 'integer',
        'monto_total_original' => 'decimal:2',
        'monto_total_devuelto' => 'decimal:2',
        'monto_descuento'      => 'decimal:2',
        'monto_efectivo'       => 'decimal:2',
        'descuento_adicional'  => 'decimal:2',
        // `fecha_emision` no va aquí: necesita milisegundos y el cast datetime los
        // pierde al escribir. Ver el accessor de abajo.
        'enviado_at'           => 'datetime',
        'anulado_at'           => 'datetime',
    ];

    /**
     * Fecha de emisión con milisegundos, igual que en {@see SiatInvoice}: el CUF
     * la codifica como `yyyyMMddHHmmssSSS` y el SIN comprueba que coincida con la
     * del XML, así que perderlos rompe cualquier reenvío.
     */
    protected function fechaEmision(): Attribute
    {
        return Attribute::make(
            get: fn (?string $valor) => $valor ? Carbon::parse($valor, 'UTC') : null,
            set: fn ($valor) => $valor ? Carbon::parse($valor)->utc()->format('Y-m-d H:i:s.v') : null,
        );
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /** La factura que esta nota ajusta. */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SiatInvoice::class, 'siat_invoice_id');
    }

    public function cufdCode(): BelongsTo
    {
        return $this->belongsTo(SiatCufdCode::class, 'cufd_code_id');
    }

    /**
     * Siguiente correlativo para la tienda y el sector.
     *
     * A diferencia de la factura, que lo saca del consecutivo del CUFD y por
     * tanto lo reinicia cada día, la nota lleva su propia serie continua. Cada
     * sector tiene la suya: el CUF ya los distingue por `tipoDocumentoSector`.
     */
    public static function siguienteNumero(int $storeId, int $documentoSector): int
    {
        return (int) static::where('store_id', $storeId)
            ->where('documento_sector', $documentoSector)
            ->lockForUpdate()
            ->max('numero_nota') + 1;
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereIn('estado', ['pendiente', 'rechazada']);
    }

    public function getSectorLabelAttribute(): string
    {
        return config("siat.nota.documentos_sector.{$this->documento_sector}", 'NOTA');
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            'pendiente'  => 'Pendiente de envío',
            'enviada'    => 'Enviada al SIN',
            'validada'   => 'Validada',
            'rechazada'  => 'Rechazada por el SIN',
            'anulada'    => 'Anulada',
            default      => (string) $this->estado,
        };
    }
}
