<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'date'          => 'date',
        'invoice_date'  => 'date',
        'received_at'   => 'datetime',
        'subtotal'      => 'decimal:2',
        'tax'           => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
