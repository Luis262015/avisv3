<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierEvaluation extends Model
{
    protected $fillable = [
        'supplier_id',
        'user_id',
        'purchase_id',
        'overall_score',
        'delivery_score',
        'quality_score',
        'price_score',
        'comments',
        'evaluated_at',
    ];

    protected $casts = [
        'overall_score'  => 'decimal:2',
        'delivery_score' => 'decimal:2',
        'quality_score'  => 'decimal:2',
        'price_score'    => 'decimal:2',
        'evaluated_at'   => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
