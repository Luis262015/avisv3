<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Purchase extends Model
{
    protected $fillable = [
        'supplier_id',
        'store_id',
        'user_id',
        'purchase_order_id',
        'folio',
        'invoice_number',
        'invoice_date',
        'date',
        'subtotal',
        'tax',
        'total',
        'status',
        'received_at',
        'payment_status',
        'document_path',
        'notes',
        'audit_notes',
        // Registro de Compras del SIN
        'codigo_autorizacion',
        'numero_dui_dim',
        'nit_proveedor',
        'razon_social_proveedor',
        'tipo_compra',
        'codigo_control',
        'importe_ice',
        'importe_iehd',
        'importe_ipj',
        'tasas',
        'otro_no_sujeto_credito',
        'importes_exentos',
        'importe_tasa_cero',
        'monto_gift_card',
        'descuento_siat',
        'credito_fiscal',
        'paquete_id',
    ];

    protected $casts = [
        'date'          => 'date',
        'invoice_date'  => 'date',
        'received_at'   => 'datetime',
        'subtotal'      => 'decimal:2',
        'tax'           => 'decimal:2',
        'total'         => 'decimal:2',
        'tipo_compra'   => 'integer',
        'importe_ice'   => 'decimal:2',
        'importe_iehd'  => 'decimal:2',
        'importe_ipj'   => 'decimal:2',
        'tasas'         => 'decimal:2',
        'otro_no_sujeto_credito' => 'decimal:2',
        'importes_exentos'       => 'decimal:2',
        'importe_tasa_cero'      => 'decimal:2',
        'monto_gift_card'        => 'decimal:2',
        'descuento_siat'         => 'decimal:2',
        'credito_fiscal'         => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** El lote del Registro de Compras en el que se declaró. */
    public function paquete(): BelongsTo
    {
        return $this->belongsTo(SiatPaquete::class, 'paquete_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payable(): HasOne
    {
        return $this->hasOne(Payable::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PurchaseAuditLog::class)->latest();
    }

    public function inventoryMovements(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }

    public function isEditable(): bool
    {
        return $this->status === 'pending';
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, ['pending', 'partial'], true);
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query->whereIn('status', ['received', 'partial']);
    }

    public function syncPaymentStatus(): void
    {
        if ($this->payable) {
            $status = match ($this->payable->status) {
                'paid'    => 'paid',
                'partial' => 'partial',
                default   => 'unpaid',
            };
            $this->update(['payment_status' => $status]);
        }
    }

    public static function nextFolio(): string
    {
        $last = static::lockForUpdate()->max('id') ?? 0;
        return 'C-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }
}
