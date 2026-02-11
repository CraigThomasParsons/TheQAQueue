<?php

namespace App\Models;

use App\FailureType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskRun extends Model
{
    protected $fillable = [
        'task_name',
        'success',
        'failure_type',
        'error_message',
        'execution_time_ms',
        'metadata',
    ];

    protected $casts = [
        'success' => 'boolean',
        'failure_type' => FailureType::class,
        'metadata' => 'array',
        'execution_time_ms' => 'integer',
    ];

    public function evaluations(): HasMany
    {
        return $this->hasMany(TaskEvaluation::class);
    }
}
