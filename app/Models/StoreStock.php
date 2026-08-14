<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreStock extends Model
{
    /**
     * La convención de Eloquent resolvería 'store_stocks', que no existe:
     * la migración crea 'store_product_stocks'.
     */
    protected $table = 'store_product_stocks';

    protected $fillable = ['store_id', 'product_id', 'stock', 'min_stock'];

    protected $casts = ['min_stock' => 'integer'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Expresión SQL del mínimo que rige a esta tienda.
     *
     * Vive aquí para que las tres consultas que buscan stock bajo —inventario,
     * panel y reposición— no escriban cada una su propio COALESCE y acaben
     * discrepando sobre qué se considera bajo.
     *
     * Requiere que la consulta tenga unida la tabla `products`.
     */
    public static function minimoEfectivoSql(): string
    {
        return 'COALESCE(store_product_stocks.min_stock, products.min_stock)';
    }

    /** El mínimo que rige de verdad: el propio si lo hay, el del producto si no. */
    public function minimoEfectivo(): int
    {
        return $this->min_stock ?? (int) ($this->product?->min_stock ?? 0);
    }

    public function estaBajoMinimo(): bool
    {
        $minimo = $this->minimoEfectivo();

        return $minimo > 0 && $this->stock <= $minimo;
    }
}
