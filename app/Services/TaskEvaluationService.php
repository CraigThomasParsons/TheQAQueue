<?php

namespace App\Services;

use App\Models\TaskEvaluation;
use App\Models\TaskRun;

class TaskEvaluationService
{
    public function __construct(
        private ConfidenceCalculationService $confidenceService,
        private EscalationRuleService $escalationService,
        private RetryGuidanceService $retryGuidanceService
    ) {}

    public function evaluateRun(TaskRun $taskRun): TaskEvaluation
    {
        // Calculate confidence
        $confidenceData = $this->confidenceService->calculateConfidence($taskRun);
        
        // Determine verdict
        $verdict = $this->determineVerdict($taskRun, $confidenceData['confidence']);
        
        // Check escalation rules
        $escalationData = $this->escalationService->shouldEscalate($taskRun, $confidenceData['confidence']);
        
        // Generate retry guidance
        $retryGuidance = $this->retryGuidanceService->generateGuidance($taskRun, $confidenceData['confidence']);

        // Create evaluation
        $evaluation = TaskEvaluation::create([
            'task_run_id' => $taskRun->id,
            'verdict' => $verdict,
            'confidence' => $confidenceData['confidence'],
            'confidence_delta' => $confidenceData['confidence_delta'],
            'retry_guidance' => $retryGuidance,
            'should_escalate' => $escalationData['should_escalate'],
            'escalation_reason' => $escalationData['should_escalate'] 
                ? implode('; ', $escalationData['reasons']) 
                : null,
            'evaluation_metadata' => [
                'previous_confidence' => $confidenceData['previous_confidence'],
                'evaluation_timestamp' => now()->toIso8601String(),
            ],
        ]);

        return $evaluation;
    }

    private function determineVerdict(TaskRun $taskRun, float $confidence): string
    {
        if ($taskRun->success) {
            return 'pass';
        }

        // If confidence is too low or escalation needed, escalate
        if ($confidence < 0.3) {
            return 'escalate';
        }

        return 'fail';
    }
}
