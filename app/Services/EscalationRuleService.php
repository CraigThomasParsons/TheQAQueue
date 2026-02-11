<?php

namespace App\Services;

use App\Models\TaskRun;

class EscalationRuleService
{
    private const CONFIDENCE_THRESHOLD = 0.3;
    private const CONSECUTIVE_FAILURES_THRESHOLD = 3;

    public function shouldEscalate(TaskRun $taskRun, float $confidence): array
    {
        $reasons = [];
        $shouldEscalate = false;

        // Rule 1: Low confidence threshold
        if ($confidence < self::CONFIDENCE_THRESHOLD) {
            $reasons[] = sprintf(
                'Confidence %.4f is below threshold %.2f',
                $confidence,
                self::CONFIDENCE_THRESHOLD
            );
            $shouldEscalate = true;
        }

        // Rule 2: Consecutive failures
        $consecutiveFailures = $this->getConsecutiveFailures($taskRun);
        if ($consecutiveFailures >= self::CONSECUTIVE_FAILURES_THRESHOLD) {
            $reasons[] = sprintf(
                '%d consecutive failures detected (threshold: %d)',
                $consecutiveFailures,
                self::CONSECUTIVE_FAILURES_THRESHOLD
            );
            $shouldEscalate = true;
        }

        // Rule 3: Critical failure types
        if ($taskRun->failure_type && $this->isCriticalFailure($taskRun)) {
            $reasons[] = sprintf(
                'Critical failure type detected: %s',
                $taskRun->failure_type->value
            );
            $shouldEscalate = true;
        }

        return [
            'should_escalate' => $shouldEscalate,
            'reasons' => $reasons,
        ];
    }

    private function getConsecutiveFailures(TaskRun $taskRun): int
    {
        $runs = TaskRun::where('task_name', $taskRun->task_name)
            ->where('id', '<=', $taskRun->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $count = 0;
        foreach ($runs as $run) {
            if (!$run->success) {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }

    private function isCriticalFailure(TaskRun $taskRun): bool
    {
        $criticalTypes = ['resource_error', 'timeout'];
        return in_array($taskRun->failure_type->value, $criticalTypes);
    }
}
