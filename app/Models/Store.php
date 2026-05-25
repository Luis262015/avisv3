<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'rfc',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    public function storeStocks(): HasMany
    {
        return $this->hasMany(StoreStock::class);
    }

    public function stockTransfersOut(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_store_id');
    }

    public function stockTransfersIn(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_store_id');
    }
}
