<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskRun;
use App\Services\TaskEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskEvaluationController extends Controller
{
    public function __construct(
        private TaskEvaluationService $evaluationService
    ) {}

    /**
     * Evaluate a task run and return verdict, confidence, and delta.
     */
    public function evaluate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_name' => 'required|string|max:255',
            'success' => 'required|boolean',
            'failure_type' => 'nullable|string|in:syntax_error,logic_error,timeout,resource_error,validation_error,assertion_failure,unknown',
            'error_message' => 'nullable|string',
            'execution_time_ms' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        // Create task run
        $taskRun = TaskRun::create($request->all());

        // Evaluate the run
        $evaluation = $this->evaluationService->evaluateRun($taskRun);

        return response()->json([
            'task_run_id' => $taskRun->id,
            'verdict' => $evaluation->verdict,
            'confidence' => (float) $evaluation->confidence,
            'confidence_delta' => (float) $evaluation->confidence_delta,
            'should_escalate' => $evaluation->should_escalate,
            'escalation_reason' => $evaluation->escalation_reason,
            'retry_guidance' => $evaluation->retry_guidance,
            'evaluation_id' => $evaluation->id,
        ], 201);
    }

    /**
     * Get evaluation by ID.
     */
    public function show(string $id): JsonResponse
    {
        $evaluation = \App\Models\TaskEvaluation::with('taskRun')->findOrFail($id);

        return response()->json([
            'evaluation' => $evaluation,
            'task_run' => $evaluation->taskRun,
        ]);
    }

    /**
     * Get all evaluations for a task.
     */
    public function index(Request $request): JsonResponse
    {
        $query = \App\Models\TaskEvaluation::with('taskRun');

        if ($request->has('task_name')) {
            $query->whereHas('taskRun', function ($q) use ($request) {
                $q->where('task_name', $request->task_name);
            });
        }

        $evaluations = $query->latest()->paginate(20);

        return response()->json($evaluations);
    }
}

