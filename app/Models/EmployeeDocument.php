<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'name',
        'file_path',
        'issued_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'issued_at'  => 'date',
        'expires_at' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeExpiringBefore(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', $date);
    }
}
