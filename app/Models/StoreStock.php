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

    protected $fillable = ['store_id', 'product_id', 'stock'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
