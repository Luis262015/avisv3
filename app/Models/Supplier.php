<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
        'rfc',
        'tax_id',
        'payment_terms',
        'lead_time_days',
        'website',
        'bank_account',
        'avg_rating',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'avg_rating'   => 'decimal:2',
        'lead_time_days' => 'integer',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function recalculateRating(): void
    {
        $avg = $this->evaluations()->avg('overall_score');
        $this->update(['avg_rating' => $avg ? round($avg, 2) : null]);
    }
}
