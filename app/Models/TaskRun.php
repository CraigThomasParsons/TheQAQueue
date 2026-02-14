<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * TaskRun - A single execution attempt of a Task.
 *
 * Each time a provider attempts to execute a task, a TaskRun is created.
 * Tracks provider, duration, files modified, and execution status.
 */
class TaskRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_uuid',
        'task_id',
        'attempt_number',
        'provider_name',
        'confidence_weight',
        'execution_status',
        'files_modified',
        'diff_summary',
        'logs',
        'duration_ms',
        'artifacts_path',
    ];

    protected $casts = [
        'files_modified' => 'array',
        'confidence_weight' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($run) {
            if (empty($run->run_uuid)) {
                $run->run_uuid = (string) Str::uuid();
            }
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function verdict()
    {
        return $this->hasOne(TaskVerdict::class);
    }

    public function isSuccess(): bool
    {
        return $this->execution_status === 'success';
    }

    public function isProviderFailure(): bool
    {
        return $this->execution_status === 'provider_failure';
    }

    /**
     * Parse an ArtifactBundle from a provider response.
     */
    public static function fromArtifactBundle(Task $task, array $bundle): self
    {
        return static::create([
            'task_id' => $task->id,
            'attempt_number' => $task->attempt,
            'provider_name' => $bundle['provider'] ?? 'unknown',
            'confidence_weight' => $bundle['confidence_weight'] ?? 1.0,
            'execution_status' => $bundle['execution_status'] ?? 'failure',
            'files_modified' => $bundle['files_modified'] ?? [],
            'diff_summary' => $bundle['diff_summary'] ?? null,
            'logs' => $bundle['logs'] ?? null,
            'duration_ms' => $bundle['duration_ms'] ?? null,
            'artifacts_path' => $bundle['artifacts_path'] ?? null,
        ]);
    }
}
