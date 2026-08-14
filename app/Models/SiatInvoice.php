<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiatInvoice extends Model
{
    protected $fillable = [
        'sale_id',
        'store_id',
        'cufd_code_id',
        'evento_id',
        'paquete_id',
        'numero_factura',
        'fecha_emision',
        'cuf',
        'cufd',
        'cafc',
        'nit_ci',
        'tipo_doc_identidad',
        'codigo_excepcion',
        'nombre_razon_social',
        'complemento',
        'importe_total',
        'importe_base_cf',
        'descuento',
        'tipo_factura',
        'tipo_emision',
        'metodo_pago',
        'numero_tarjeta',
        'estado',
        'codigo_recepcion',
        'anexos_estado',
        'anexos_codigo_recepcion',
        'anexos_mensaje_error',
        'anexos_enviado_at',
        'codigo_qr',
        'mensaje_error',
        'enviado_at',
        'anulado_at',
        'motivo_anulacion',
    ];

    /** Dato de tarjeta: no tiene por qué llegar al navegador. */
    protected $hidden = ['numero_tarjeta'];

    protected $casts = [
        'importe_total'  => 'decimal:2',
        'importe_base_cf'=> 'decimal:2',
        'descuento'      => 'decimal:2',
        // `fecha_emision` no va aquí: necesita milisegundos y el cast datetime los
        // pierde al escribir. Se maneja en su propio accessor más abajo.
        'enviado_at'     => 'datetime',
        'anulado_at'     => 'datetime',
        'anexos_enviado_at' => 'datetime',
        'tipo_factura'   => 'integer',
        'tipo_emision'   => 'integer',
        'tipo_doc_identidad' => 'integer',
        'codigo_excepcion'   => 'integer',
        'metodo_pago'    => 'integer',
        // Dato de tarjeta: nunca en claro en la base.
        'numero_tarjeta' => 'encrypted',
    ];

    /**
     * Fecha de emisión con milisegundos.
     *
     * El CUF codifica la fecha como `yyyyMMddHHmmssSSS` y el SIN comprueba que
     * coincida con la del XML, así que perder los milisegundos rompe cualquier
     * reenvío. El cast `datetime` los descarta al escribir —usa el formato de la
     * conexión—, de ahí el mutator explícito. Se guarda en UTC como el resto de
     * fechas; la conversión a hora de Bolivia ocurre al construir el XML.
     */
    protected function fechaEmision(): Attribute
    {
        return Attribute::make(
            get: fn(?string $valor) => $valor ? Carbon::parse($valor, 'UTC') : null,
            set: fn($valor) => $valor ? Carbon::parse($valor)->utc()->format('Y-m-d H:i:s.v') : null,
        );
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cufdCode(): BelongsTo
    {
        return $this->belongsTo(SiatCufdCode::class, 'cufd_code_id');
    }

    /** El corte durante el que se emitió, si fue fuera de línea. */
    public function evento(): BelongsTo
    {
        return $this->belongsTo(SiatEvento::class, 'evento_id');
    }

    public function paquete(): BelongsTo
    {
        return $this->belongsTo(SiatPaquete::class, 'paquete_id');
    }

    /** Números de serie e IMEI declarados al SIN por esta factura. */
    public function anexos(): HasMany
    {
        return $this->hasMany(SiatAnexo::class, 'siat_invoice_id');
    }

    /**
     * Cuántos códigos exige el SIN para esta factura.
     *
     * Uno por unidad física vendida de un producto marcado con anexo: tres
     * portátiles en una línea son tres números de serie, no uno.
     */
    public function anexosRequeridos(): int
    {
        $this->loadMissing('sale.items.product');

        return (int) ($this->sale?->items
            ->filter(fn (SaleItem $item): bool => (bool) $item->product?->requiereAnexo())
            ->sum(fn (SaleItem $item): float => (float) $item->quantity) ?? 0);
    }

    public function getAnexosEstadoLabelAttribute(): string
    {
        return match ($this->anexos_estado) {
            'pendiente' => 'Pendiente de envío',
            'enviado'   => 'Enviados al SIN',
            'error'     => 'Rechazados por el SIN',
            default     => 'No aplica',
        };
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            'pendiente'   => 'Pendiente de envío',
            'enviada'     => 'Enviada a SIN',
            'anulada'     => 'Anulada',
            'contingencia'=> 'En contingencia',
            default       => $this->estado,
        };
    }

    public function getTipoDocIdentidadLabelAttribute(): string
    {
        return match ($this->tipo_doc_identidad) {
            1 => 'CI Bolivia',
            2 => 'Pasaporte',
            3 => 'Carnet Extranjería',
            4 => 'Otro documento',
            5 => 'NIT',
            default => 'Desconocido',
        };
    }

    public function getTipoEmisionLabelAttribute(): string
    {
        return match ($this->tipo_emision) {
            1 => 'En línea',
            2 => 'Fuera de línea (contingencia)',
            3 => 'Masiva (por lotes)',
            4 => 'Contingencia',
            default => 'Desconocido',
        };
    }

    public function getTipoFacturaLabelAttribute(): string
    {
        return match ($this->tipo_factura) {
            1 => 'Con derecho a crédito fiscal',
            2 => 'Sin derecho a crédito fiscal',
            default => 'Desconocido',
        };
    }

    public function getMetodoPagoLabelAttribute(): string
    {
        return match ($this->metodo_pago) {
            1  => 'Efectivo',
            2  => 'Tarjeta',
            3  => 'Transferencia bancaria',
            7  => 'Código QR',
            default => 'Efectivo',
        };
    }
}
