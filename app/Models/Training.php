<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Training extends Model
{
    protected $fillable = [
        'title',
        'description',
        'provider',
        'modality',
        'start_date',
        'end_date',
        'hours',
        'cost',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'hours'      => 'decimal:2',
        'cost'       => 'decimal:2',
    ];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class)
            ->using(TrainingParticipant::class)
            ->withPivot(['status', 'score', 'completed_at', 'certificate_path', 'notes'])
            ->withTimestamps();
    }
}
