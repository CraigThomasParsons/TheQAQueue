<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * TaskVerdict - QA evaluation result for a TaskRun.
 *
 * Created by Vera after evaluating a task execution.
 * Contains PASS/FAIL verdict, confidence score, and evidence paths.
 */
class TaskVerdict extends Model
{
    use HasFactory;

    protected $fillable = [
        'verdict_uuid',
        'task_run_id',
        'task_id',
        'verdict',
        'confidence',
        'reasoning',
        'observations',
        'evidence_path',
        'screenshot_paths',
        'evaluated_by',
        'evaluator_model',
    ];

    protected $casts = [
        'observations' => 'array',
        'screenshot_paths' => 'array',
        'confidence' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($verdict) {
            if (empty($verdict->verdict_uuid)) {
                $verdict->verdict_uuid = (string) Str::uuid();
            }
        });
    }

    public function taskRun(): BelongsTo
    {
        return $this->belongsTo(TaskRun::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isPassed(): bool
    {
        return $this->verdict === 'pass';
    }

    public function isFailed(): bool
    {
        return $this->verdict === 'fail';
    }

    /**
     * Get confidence adjusted by provider weight.
     */
    public function adjustedConfidence(): float
    {
        $providerWeight = $this->taskRun?->confidence_weight ?? 1.0;
        return $this->confidence * $providerWeight;
    }
}
