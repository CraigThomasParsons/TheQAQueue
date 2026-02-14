<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Task - A granular execution unit decomposed from a Story.
 *
 * Tasks are created by Mason and executed by providers (Goose, Claude, etc).
 * Each task goes through: pending → queued → claimed → running → awaiting_qa → passed/failed
 */
class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_uuid',
        'story_id',
        'epic_id',
        'title',
        'description',
        'success_criteria',
        'constraints',
        'inputs',
        'mode',
        'expected_outputs',
        'attempt',
        'max_attempts',
        'last_provider',
        'claimed_by',
        'claimed_at',
        'task_status_id',
        'priority',
        'sort_order',
    ];

    protected $casts = [
        'success_criteria' => 'array',
        'constraints' => 'array',
        'inputs' => 'array',
        'expected_outputs' => 'array',
        'claimed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($task) {
            if (empty($task->task_uuid)) {
                $task->task_uuid = (string) Str::uuid();
            }
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'task_status_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TaskRun::class);
    }

    public function verdicts(): HasMany
    {
        return $this->hasMany(TaskVerdict::class);
    }

    public function latestRunRecord(): HasOne
    {
        return $this->hasOne(TaskRun::class)->latestOfMany();
    }

    public function latestVerdictRecord(): HasOne
    {
        return $this->hasOne(TaskVerdict::class)->latestOfMany();
    }

    public function latestRun(): ?TaskRun
    {
        return $this->runs()->latest()->first();
    }

    public function latestVerdict(): ?TaskVerdict
    {
        return $this->verdicts()->latest()->first();
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeByStatus(Builder $query, string $statusKey): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('key', $statusKey));
    }

    public function scopeQueued(Builder $query): Builder
    {
        return $query->byStatus('queued');
    }

    public function scopeAwaitingQa(Builder $query): Builder
    {
        return $query->byStatus('awaiting_qa');
    }

    public function scopeUnclaimed(Builder $query): Builder
    {
        return $query->whereNull('claimed_by');
    }

    public function scopeClaimedBy(Builder $query, string $agent): Builder
    {
        return $query->where('claimed_by', $agent);
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->whereColumn('attempt', '<', 'max_attempts');
    }

    // ─────────────────────────────────────────────────────────────
    // State Machine
    // ─────────────────────────────────────────────────────────────

    public function transitionTo(string $statusKey): bool
    {
        $status = TaskStatus::byKey($statusKey);
        if (!$status) {
            return false;
        }

        $this->task_status_id = $status->id;
        return $this->save();
    }

    public function claim(string $agent): bool
    {
        if ($this->claimed_by !== null) {
            return false;
        }

        $this->claimed_by = $agent;
        $this->claimed_at = now();
        $this->transitionTo('claimed');
        return $this->save();
    }

    public function release(): bool
    {
        $this->claimed_by = null;
        $this->claimed_at = null;
        $this->transitionTo('queued');
        return $this->save();
    }

    public function incrementAttempt(): void
    {
        $this->increment('attempt');
    }

    public function hasRetriesLeft(): bool
    {
        return $this->attempt < $this->max_attempts;
    }

    public function isExhausted(): bool
    {
        return $this->attempt >= $this->max_attempts;
    }

    // ─────────────────────────────────────────────────────────────
    // TaskPacket Generation
    // ─────────────────────────────────────────────────────────────

    /**
     * Generate TaskPacket v1 JSON for provider consumption.
     */
    public function toTaskPacket(): array
    {
        $story = $this->story;

        return [
            'version' => '1.0',
            'identity' => [
                'task_id' => $this->task_uuid,
                'epic_id' => $this->epic_id,
                'story_id' => $this->story_id,
                'attempt' => $this->attempt,
                'max_attempts' => $this->max_attempts,
            ],
            'goal' => [
                'title' => $this->title,
                'description' => $this->description,
                'success_criteria' => $this->success_criteria ?? [],
            ],
            'constraints' => $this->constraints ?? [
                'allowed_paths' => [],
                'forbidden_paths' => ['vendor/', 'node_modules/'],
                'dependencies' => ['allow_new' => false, 'allowed_list' => []],
                'style_rules' => [],
                'runtime_constraints' => [],
            ],
            'inputs' => $this->inputs ?? [
                'files' => [],
                'artifacts_from_previous_run' => $this->getRetryArtifacts(),
                'retry_guidance' => $this->getRetryGuidance(),
            ],
            'execution' => [
                'mode' => $this->mode,
                'expected_outputs' => $this->expected_outputs ?? [],
                'idempotent' => true,
                'timeout_seconds' => 300,
            ],
            'provider_context' => [
                'provider_name' => null,
                'provider_priority' => null,
                'confidence_weight' => 1.0,
            ],
            'story_context' => [
                'title' => $story?->title,
                'narrative' => $story?->narrative,
                'acceptance_criteria' => $story?->acceptance_criteria,
            ],
            'metadata' => [
                'created_at' => $this->created_at?->toIso8601String(),
                'created_by' => 'mason',
                'source' => 'qaqueue',
            ],
        ];
    }

    protected function getRetryArtifacts(): array
    {
        if ($this->attempt === 0) {
            return [];
        }

        return $this->runs()
            ->where('attempt_number', '<', $this->attempt)
            ->pluck('artifacts_path')
            ->filter()
            ->toArray();
    }

    protected function getRetryGuidance(): array
    {
        if ($this->attempt === 0) {
            return [];
        }

        $lastVerdict = $this->verdicts()
            ->latest()
            ->first();

        if (!$lastVerdict) {
            return [];
        }

        $guidance = [];

        if ($lastVerdict->reasoning) {
            $guidance[] = "Previous failure reason: {$lastVerdict->reasoning}";
        }

        if ($lastVerdict->observations) {
            foreach ($lastVerdict->observations as $obs) {
                $guidance[] = $obs;
            }
        }

        return $guidance;
    }
}
