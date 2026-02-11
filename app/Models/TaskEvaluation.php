<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskEvaluation extends Model
{
    protected $fillable = [
        'task_run_id',
        'verdict',
        'confidence',
        'confidence_delta',
        'retry_guidance',
        'should_escalate',
        'escalation_reason',
        'evaluation_metadata',
    ];

    protected $casts = [
        'confidence' => 'decimal:4',
        'confidence_delta' => 'decimal:4',
        'retry_guidance' => 'array',
        'should_escalate' => 'boolean',
        'evaluation_metadata' => 'array',
    ];

    public function taskRun(): BelongsTo
    {
        return $this->belongsTo(TaskRun::class);
    }
}
