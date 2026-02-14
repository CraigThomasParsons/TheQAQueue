<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskRun;
use App\Models\TaskVerdict;
use App\Models\TaskStatus;
use App\Models\Story;
use App\Models\StoryStatus;
use App\Models\Epic;
use App\Models\EpicStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * TaskApiController - REST API for CCDF agents.
 *
 * Provides endpoints for:
 * - Vera to fetch and evaluate tasks
 * - Mason to query queue state
 * - Piper to confirm completed tasks
 */
class TaskApiController extends Controller
{
    /**
     * POST /api/tasks
     *
     * Ingest a single task from Mason/DevBacklog.
     */
    public function createTask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'story' => 'required|array',
            'story.id' => 'required|integer',
            'story.title' => 'required|string|max:255',
            'story.narrative' => 'nullable|string',
            'story.acceptance_criteria' => 'nullable|string',
            'story.epic' => 'nullable|array',
            'story.epic.id' => 'nullable|integer',
            'story.epic.title' => 'nullable|string|max:255',
            'task' => 'required|array',
            'task.title' => 'required|string|max:255',
            'task.description' => 'required|string',
            'task.success_criteria' => 'required|array|min:1',
            'task.constraints' => 'nullable|array',
            'task.inputs' => 'nullable|array',
            'task.mode' => 'nullable|in:create_new,modify_existing,scaffold,analyze',
            'task.expected_outputs' => 'nullable|array',
            'task.priority' => 'nullable|integer|min:0|max:1000',
            'task.sort_order' => 'nullable|integer|min:0',
            'task.max_attempts' => 'nullable|integer|min:1|max:10',
            'task.external_ref' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:100',
        ]);

        $status = TaskStatus::byKey('queued') ?? TaskStatus::byKey('pending');
        if (! $status) {
            return response()->json([
                'error' => 'Task statuses not initialized',
            ], 500);
        }

        [$epic, $story] = $this->upsertStoryContext($validated['story']);

        $taskPayload = $validated['task'];

        $task = Task::create([
            'story_id' => $story->id,
            'epic_id' => $epic?->id,
            'title' => $taskPayload['title'],
            'description' => $taskPayload['description'],
            'success_criteria' => $taskPayload['success_criteria'],
            'constraints' => $taskPayload['constraints'] ?? null,
            'inputs' => $taskPayload['inputs'] ?? null,
            'mode' => $taskPayload['mode'] ?? 'modify_existing',
            'expected_outputs' => $taskPayload['expected_outputs'] ?? null,
            'task_status_id' => $status->id,
            'priority' => $taskPayload['priority'] ?? 50,
            'sort_order' => $taskPayload['sort_order'] ?? 0,
            'max_attempts' => $taskPayload['max_attempts'] ?? 3,
        ]);

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
            'task_uuid' => $task->task_uuid,
            'status' => $task->status?->key,
            'source' => $validated['source'] ?? 'devbacklog',
        ], 201);
    }

    /**
     * POST /api/tasks/bulk
     *
     * Ingest multiple tasks for a single story.
     */
    public function createBulkTasks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'story' => 'required|array',
            'story.id' => 'required|integer',
            'story.title' => 'required|string|max:255',
            'story.narrative' => 'nullable|string',
            'story.acceptance_criteria' => 'nullable|string',
            'story.epic' => 'nullable|array',
            'story.epic.id' => 'nullable|integer',
            'story.epic.title' => 'nullable|string|max:255',
            'tasks' => 'required|array|min:1',
            'tasks.*.title' => 'required|string|max:255',
            'tasks.*.description' => 'required|string',
            'tasks.*.success_criteria' => 'required|array|min:1',
            'tasks.*.constraints' => 'nullable|array',
            'tasks.*.inputs' => 'nullable|array',
            'tasks.*.mode' => 'nullable|in:create_new,modify_existing,scaffold,analyze',
            'tasks.*.expected_outputs' => 'nullable|array',
            'tasks.*.priority' => 'nullable|integer|min:0|max:1000',
            'tasks.*.sort_order' => 'nullable|integer|min:0',
            'tasks.*.max_attempts' => 'nullable|integer|min:1|max:10',
            'source' => 'nullable|string|max:100',
        ]);

        $status = TaskStatus::byKey('queued') ?? TaskStatus::byKey('pending');
        if (! $status) {
            return response()->json([
                'error' => 'Task statuses not initialized',
            ], 500);
        }

        [$epic, $story] = $this->upsertStoryContext($validated['story']);

        $created = [];
        foreach ($validated['tasks'] as $index => $taskPayload) {
            $task = Task::create([
                'story_id' => $story->id,
                'epic_id' => $epic?->id,
                'title' => $taskPayload['title'],
                'description' => $taskPayload['description'],
                'success_criteria' => $taskPayload['success_criteria'],
                'constraints' => $taskPayload['constraints'] ?? null,
                'inputs' => $taskPayload['inputs'] ?? null,
                'mode' => $taskPayload['mode'] ?? 'modify_existing',
                'expected_outputs' => $taskPayload['expected_outputs'] ?? null,
                'task_status_id' => $status->id,
                'priority' => $taskPayload['priority'] ?? 50,
                'sort_order' => $taskPayload['sort_order'] ?? ($index + 1),
                'max_attempts' => $taskPayload['max_attempts'] ?? 3,
            ]);

            $created[] = [
                'task_id' => $task->id,
                'task_uuid' => $task->task_uuid,
                'title' => $task->title,
                'status' => $task->status?->key,
            ];
        }

        return response()->json([
            'success' => true,
            'count' => count($created),
            'tasks' => $created,
            'source' => $validated['source'] ?? 'devbacklog',
        ], 201);
    }

    /**
     * Ensure local Epic/Story records exist in QAQueue for task lineage.
     *
     * @return array{0:?Epic,1:Story}
     */
    protected function upsertStoryContext(array $storyPayload): array
    {
        $activeEpicStatus = EpicStatus::byKey('active')
            ?? EpicStatus::firstOrCreate(['key' => 'active'], ['name' => 'Active']);

        $readyStoryStatus = StoryStatus::byKey('ready')
            ?? StoryStatus::firstOrCreate(['key' => 'ready'], ['name' => 'Ready']);

        $epic = null;
        if (! empty($storyPayload['epic']['id'])) {
            $epic = Epic::updateOrCreate(
                ['id' => $storyPayload['epic']['id']],
                [
                    'title' => $storyPayload['epic']['title'] ?? 'Imported Epic',
                    'summary' => $storyPayload['epic']['summary'] ?? null,
                    'epic_status_id' => $activeEpicStatus->id,
                ]
            );
        }

        $story = Story::updateOrCreate(
            ['id' => $storyPayload['id']],
            [
                'epic_id' => $epic?->id,
                'title' => $storyPayload['title'],
                'narrative' => $storyPayload['narrative'] ?? '',
                'acceptance_criteria' => $storyPayload['acceptance_criteria'] ?? null,
                'story_status_id' => $readyStoryStatus->id,
                'priority' => $storyPayload['priority'] ?? 0,
                'est_points' => $storyPayload['est_points'] ?? null,
            ]
        );

        return [$epic, $story];
    }

    // ─────────────────────────────────────────────────────────────
    // Vera Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/tasks/next-for-qa
     *
     * Fetch the next task ready for QA evaluation.
     * Returns 204 if no tasks available.
     */
    public function nextForQa(): JsonResponse
    {
        $task = Task::byStatus('awaiting_qa')
            ->unclaimed()
            ->with(['story', 'epic', 'latestRunRecord'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$task) {
            return response()->json(null, 204);
        }

        return response()->json($this->formatTaskForVera($task));
    }

    /**
     * POST /api/tasks/{task}/claim
     *
     * Claim a task for processing by an agent.
     */
    public function claim(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'agent' => 'required|string|max:50',
        ]);

        if ($task->claimed_by !== null) {
            return response()->json([
                'error' => 'Task already claimed',
                'claimed_by' => $task->claimed_by,
            ], 409);
        }

        $task->claim($validated['agent']);
        $task->transitionTo('in_qa');

        return response()->json([
            'success' => true,
            'task_id' => $task->task_uuid,
            'claimed_by' => $validated['agent'],
        ]);
    }

    /**
     * POST /api/tasks/{task}/verdict
     *
     * Submit QA verdict for a task.
     */
    public function verdict(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'verdict' => 'required|in:pass,fail,PASS,FAIL',
            'confidence' => 'required|numeric|min:0|max:1',
            'reasoning' => 'required|string',
            'observations' => 'nullable|array',
            'evidence_paths' => 'nullable|array',
            'agent' => 'required|string',
            'evaluator_model' => 'nullable|string',
        ]);

        $latestRun = $task->latestRun();

        // Create verdict
        $verdict = TaskVerdict::create([
            'task_run_id' => $latestRun?->id,
            'task_id' => $task->id,
            'verdict' => strtolower($validated['verdict']),
            'confidence' => $validated['confidence'],
            'reasoning' => $validated['reasoning'],
            'observations' => $validated['observations'] ?? [],
            'evidence_path' => $validated['evidence_paths'][0] ?? null,
            'screenshot_paths' => $validated['evidence_paths'] ?? [],
            'evaluated_by' => $validated['agent'],
            'evaluator_model' => $validated['evaluator_model'],
        ]);

        // Transition task based on verdict
        if ($verdict->isPassed()) {
            $task->transitionTo('passed');
            $task->claimed_by = null;
            $task->claimed_at = null;
            $task->save();
        } else {
            // Check if retries left
            if ($task->hasRetriesLeft()) {
                $task->transitionTo('retry');
            } else {
                $task->transitionTo('exhausted');
            }
            $task->claimed_by = null;
            $task->claimed_at = null;
            $task->save();
        }

        return response()->json([
            'success' => true,
            'verdict_id' => $verdict->verdict_uuid,
            'task_status' => $task->fresh()->status->key,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Mason Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/queue/stats
     *
     * Get queue statistics for Mason's scheduling decisions.
     */
    public function queueStats(): JsonResponse
    {
        $stats = [
            'pending' => Task::byStatus('pending')->count(),
            'queued' => Task::byStatus('queued')->count(),
            'running' => Task::byStatus('running')->count(),
            'awaiting_qa' => Task::byStatus('awaiting_qa')->count(),
            'in_qa' => Task::byStatus('in_qa')->count(),
            'passed' => Task::byStatus('passed')->count(),
            'failed' => Task::byStatus('failed')->count(),
            'retry' => Task::byStatus('retry')->count(),
            'exhausted' => Task::byStatus('exhausted')->count(),
            'escalated' => Task::byStatus('escalated')->count(),
        ];

        $stats['total_active'] = $stats['queued'] + $stats['running'] + $stats['awaiting_qa'] + $stats['in_qa'];
        $stats['total_completed'] = $stats['passed'];
        $stats['total_failed'] = $stats['failed'] + $stats['exhausted'] + $stats['escalated'];

        return response()->json($stats);
    }

    /**
     * GET /api/queue/provider-stats
     *
     * Get per-provider success/failure rates for Mason's routing decisions.
     */
    public function providerStats(): JsonResponse
    {
        $providers = TaskRun::selectRaw('
            provider_name,
            COUNT(*) as total_runs,
            SUM(CASE WHEN execution_status = "success" THEN 1 ELSE 0 END) as successes,
            SUM(CASE WHEN execution_status = "failure" THEN 1 ELSE 0 END) as failures,
            SUM(CASE WHEN execution_status = "provider_failure" THEN 1 ELSE 0 END) as provider_failures,
            AVG(duration_ms) as avg_duration_ms
        ')
            ->groupBy('provider_name')
            ->get()
            ->mapWithKeys(function ($row) {
                $successRate = $row->total_runs > 0
                    ? $row->successes / $row->total_runs
                    : 0;

                return [$row->provider_name => [
                    'total_runs' => $row->total_runs,
                    'successes' => $row->successes,
                    'failures' => $row->failures,
                    'provider_failures' => $row->provider_failures,
                    'success_rate' => round($successRate, 3),
                    'avg_duration_ms' => round($row->avg_duration_ms),
                ]];
            });

        return response()->json($providers);
    }

    /**
     * GET /api/tasks/retry-queue
     *
     * Get tasks queued for retry, with failure context for Mason.
     */
    public function retryQueue(): JsonResponse
    {
        $tasks = Task::byStatus('retry')
            ->with(['story', 'latestVerdictRecord', 'runs'])
            ->orderBy('priority', 'desc')
            ->get()
            ->map(fn ($task) => [
                'task_id' => $task->task_uuid,
                'title' => $task->title,
                'attempt' => $task->attempt,
                'max_attempts' => $task->max_attempts,
                'last_provider' => $task->last_provider,
                'last_failure_reason' => $task->latestVerdictRecord?->reasoning,
                'providers_tried' => $task->runs->pluck('provider_name')->unique()->values(),
            ]);

        return response()->json($tasks);
    }

    // ─────────────────────────────────────────────────────────────
    // Piper Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/tasks/{task}/confirm
     *
     * Piper confirms a passed task after Vera's verdict.
     */
    public function confirm(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'agent' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        // Only passed tasks can be confirmed
        if ($task->status->key !== 'passed') {
            return response()->json([
                'error' => 'Only passed tasks can be confirmed',
                'current_status' => $task->status->key,
            ], 400);
        }

        // Mark as fully complete (could add a 'confirmed' status later)
        // For now, just log the confirmation
        // In production, this would emit an event to WritersRoom

        return response()->json([
            'success' => true,
            'task_id' => $task->task_uuid,
            'confirmed_by' => $validated['agent'],
            'story_id' => $task->story_id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Execution Endpoints (for providers)
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/tasks/next-for-execution
     *
     * Fetch the next task ready for execution by a provider.
     */
    public function nextForExecution(): JsonResponse
    {
        // Priority: retry tasks first, then new tasks
        $task = Task::where(function ($q) {
                $q->byStatus('retry')
                  ->orWhere(fn ($q2) => $q2->byStatus('queued'));
            })
            ->unclaimed()
            ->orderByRaw("CASE WHEN task_status_id = ? THEN 0 ELSE 1 END", [
                TaskStatus::byKey('retry')?->id,
            ])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$task) {
            return response()->json(null, 204);
        }

        return response()->json($task->toTaskPacket());
    }

    /**
     * POST /api/tasks/{task}/start-run
     *
     * Start a new execution run for a task.
     */
    public function startRun(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'provider_name' => 'required|string',
            'confidence_weight' => 'nullable|numeric|min:0|max:1',
        ]);

        // Claim and transition
        $task->claim($validated['provider_name']);
        $task->transitionTo('running');
        $task->incrementAttempt();
        $task->last_provider = $validated['provider_name'];
        $task->save();

        // Create run record
        $run = TaskRun::create([
            'task_id' => $task->id,
            'attempt_number' => $task->attempt,
            'provider_name' => $validated['provider_name'],
            'confidence_weight' => $validated['confidence_weight'] ?? 1.0,
            'execution_status' => 'running',
        ]);

        return response()->json([
            'success' => true,
            'run_id' => $run->run_uuid,
            'attempt' => $task->attempt,
        ]);
    }

    /**
     * POST /api/tasks/{task}/complete-run
     *
     * Complete an execution run with artifact bundle.
     */
    public function completeRun(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'run_id' => 'required|uuid',
            'execution_status' => 'required|in:success,failure,provider_failure',
            'files_modified' => 'nullable|array',
            'diff_summary' => 'nullable|string',
            'logs' => 'nullable|string',
            'duration_ms' => 'nullable|integer',
            'artifacts_path' => 'nullable|string',
        ]);

        $run = TaskRun::where('run_uuid', $validated['run_id'])->firstOrFail();

        $run->update([
            'execution_status' => $validated['execution_status'],
            'files_modified' => $validated['files_modified'] ?? null,
            'diff_summary' => $validated['diff_summary'] ?? null,
            'logs' => $validated['logs'] ?? null,
            'duration_ms' => $validated['duration_ms'] ?? null,
            'artifacts_path' => $validated['artifacts_path'] ?? null,
        ]);

        // Handle status transition based on execution result
        if ($validated['execution_status'] === 'success') {
            $task->transitionTo('awaiting_qa');
            $task->claimed_by = null;
            $task->claimed_at = null;
            $task->save();
        } elseif ($validated['execution_status'] === 'provider_failure') {
            // Provider failure doesn't count as attempt
            $task->decrement('attempt');
            $task->transitionTo('queued');
            $task->claimed_by = null;
            $task->claimed_at = null;
            $task->save();
        } else {
            // Execution failure
            $task->transitionTo('failed');
            $task->claimed_by = null;
            $task->claimed_at = null;
            $task->save();
        }

        return response()->json([
            'success' => true,
            'run_id' => $run->run_uuid,
            'task_status' => $task->fresh()->status->key,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Format a task for Vera's consumption.
     */
    protected function formatTaskForVera(Task $task): array
    {
        $story = $task->story;
        $run = $task->latestRunRecord ?? $task->latestRun();

        return [
            'task_id' => $task->task_uuid,
            'correlation_id' => "task-{$task->id}-run-{$run?->id}",

            'story' => [
                'title' => $story?->title,
                'description' => $story?->narrative,
                'acceptance_criteria' => $this->parseAcceptanceCriteria($story?->acceptance_criteria),
            ],

            'test_instructions' => [
                'type' => 'code_review', // Default; could be extended
                'target_url' => null, // Tasks may not have UI tests
                'steps' => [], // Could be auto-generated from success criteria
            ],

            'expected_outcome' => [
                'success_criteria' => $task->success_criteria,
                'files_modified' => $run?->files_modified ?? [],
            ],

            'execution_context' => [
                'provider' => $run?->provider_name,
                'attempt' => $task->attempt,
                'artifacts_path' => $run?->artifacts_path,
                'diff_summary' => $run?->diff_summary,
            ],
        ];
    }

    /**
     * Parse acceptance criteria text into array.
     */
    protected function parseAcceptanceCriteria(?string $criteria): array
    {
        if (!$criteria) {
            return [];
        }

        // Split by newlines and clean up
        return collect(explode("\n", $criteria))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => !empty($line))
            ->values()
            ->toArray();
    }
}
