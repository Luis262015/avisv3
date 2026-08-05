<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    protected $fillable = [
        'cash_shift_id',
        'user_id',
        'customer_id',
        'promotion_id',
        'folio',
        'subtotal',
        'tax',
        'discount',
        'total',
        'amount_paid',
        'change_amount',
        'payment_method',
        'status',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by_user_id',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'tax'           => 'decimal:2',
        'discount'      => 'decimal:2',
        'total'         => 'decimal:2',
        'amount_paid'   => 'decimal:2',
        'change_amount' => 'decimal:2',
        'cancelled_at'  => 'datetime',
    ];

    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function inventoryMovements(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }

    public function siatInvoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SiatInvoice::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public static function nextFolio(): string
    {
        $last = static::lockForUpdate()->max('id') ?? 0;

        return 'V-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Unidades ya comprometidas en devoluciones, indexadas por sale_item_id.
     *
     * Cuentan las devoluciones pendientes y aprobadas además de las completadas:
     * aunque todavía no hayan repuesto stock, esas unidades ya están reclamadas y
     * no pueden devolverse de nuevo.
     *
     * @return \Illuminate\Support\Collection<int, float>
     */
    public function returnedQuantitiesByItem(): \Illuminate\Support\Collection
    {
        return SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_returns.sale_id', $this->id)
            ->whereIn('sale_returns.status', ['pending', 'approved', 'completed'])
            ->whereNotNull('sale_return_items.sale_item_id')
            ->groupBy('sale_return_items.sale_item_id')
            ->select('sale_return_items.sale_item_id', DB::raw('SUM(sale_return_items.quantity) as qty'))
            ->get()
            ->pluck('qty', 'sale_item_id')
            ->map(fn($q) => (float) $q);
    }

    /** Unidades ya repuestas al inventario por devoluciones completadas. */
    public function restockedQuantitiesByItem(): \Illuminate\Support\Collection
    {
        return SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_returns.sale_id', $this->id)
            ->where('sale_returns.status', 'completed')
            ->where('sale_returns.restock', true)
            ->whereNotNull('sale_return_items.sale_item_id')
            ->groupBy('sale_return_items.sale_item_id')
            ->select('sale_return_items.sale_item_id', DB::raw('SUM(sale_return_items.quantity) as qty'))
            ->get()
            ->pluck('qty', 'sale_item_id')
            ->map(fn($q) => (float) $q);
    }
}
