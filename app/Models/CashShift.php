<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashShift extends Model
{
    protected $fillable = [
        'cash_register_id',
        'user_id',
        'opening_amount',
        'closing_amount',
        'expected_amount',
        'difference',
        'opened_at',
        'closed_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'opening_amount'  => 'decimal:2',
        'closing_amount'  => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'difference'      => 'decimal:2',
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * El arqueo del turno: qué debería haber en el cajón y de dónde sale.
     *
     * **Solo cuenta lo que pasó por el cajón.** Una venta con tarjeta o
     * transferencia no deja efectivo, y sumarla al esperado le imputaba al cajero
     * un faltante por dinero que nunca tuvo en la mano. Los retiros sí van
     * enteros: siempre salen del cajón, por eso ni siquiera tienen método de pago.
     *
     * Las ventas **mixtas** quedan fuera del esperado a propósito: la venta no
     * guarda cómo se repartió el cobro, así que no hay forma de saber cuánto de
     * ella entró en efectivo. Se devuelven aparte para que quien cierra lo
     * resuelva a mano en vez de arrastrar un descuadre inexplicable.
     *
     * Vive en el modelo para que la pantalla del turno y el cierre usen el mismo
     * cálculo; tenerlo duplicado es justo lo que dejó pasar el fallo original.
     *
     * @return array{
     *     ventas_efectivo: float, ventas_otros: float, ventas_mixtas: float,
     *     ingresos_efectivo: float, gastos_efectivo: float, retiros: float,
     *     esperado: float
     * }
     */
    public function arqueo(): array
    {
        $ventasPorMetodo = $this->sales()
            ->where('status', 'completed')
            ->selectRaw('payment_method, COALESCE(SUM(total), 0) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $efectivo = (float) ($ventasPorMetodo['cash'] ?? 0);
        $mixtas   = (float) ($ventasPorMetodo['mixed'] ?? 0);
        $otros    = (float) $ventasPorMetodo->sum() - $efectivo - $mixtas;

        $ingresos = (float) $this->incomes()->where('payment_method', 'cash')->sum('amount');
        $gastos   = (float) $this->expenses()->where('payment_method', 'cash')->sum('amount');
        $retiros  = (float) $this->withdrawals()->sum('amount');

        return [
            'ventas_efectivo'   => $efectivo,
            'ventas_otros'      => $otros,
            'ventas_mixtas'     => $mixtas,
            'ingresos_efectivo' => $ingresos,
            'gastos_efectivo'   => $gastos,
            'retiros'           => $retiros,
            'esperado'          => round(
                (float) $this->opening_amount + $efectivo + $ingresos - $gastos - $retiros,
                2
            ),
        ];
    }
}
