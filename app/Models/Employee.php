<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'employee_code',
        'first_name',
        'last_name',
        'document_type',
        'document_number',
        'birth_date',
        'gender',
        'marital_status',
        'nationality',
        'phone',
        'email',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'photo_path',
        'position',
        'hire_date',
        'termination_date',
        'contract_type',
        'status',
        'base_salary',
        'bank_name',
        'bank_account',
        'afp_name',
        'afp_number',
        'cuns',
        'notes',
    ];

    protected $casts = [
        'birth_date'       => 'date',
        'hire_date'        => 'date',
        'termination_date' => 'date',
        'base_salary'      => 'decimal:2',
    ];

    protected $appends = ['full_name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class)
            ->using(TrainingParticipant::class)
            ->withPivot(['status', 'score', 'completed_at', 'certificate_path', 'notes'])
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(EmployeeIncident::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Años completos de antigüedad a la fecha dada (por defecto, hoy).
     */
    public function yearsOfService(?\DateTimeInterface $asOf = null): int
    {
        if (! $this->hire_date) {
            return 0;
        }

        return $this->hire_date->diffInYears($asOf ?? now());
    }
}
