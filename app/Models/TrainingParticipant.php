<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TrainingParticipant extends Pivot
{
    protected $table = 'training_employee';

    public $incrementing = true;

    protected $fillable = [
        'training_id',
        'employee_id',
        'status',
        'score',
        'completed_at',
        'certificate_path',
        'notes',
    ];

    protected $casts = [
        'score'        => 'decimal:2',
        'completed_at' => 'date',
    ];
}
