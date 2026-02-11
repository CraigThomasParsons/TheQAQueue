<?php

namespace App\Services;

use App\FailureType;
use App\Models\TaskRun;

class RetryGuidanceService
{
    public function generateGuidance(TaskRun $taskRun, float $confidence): ?array
    {
        if ($taskRun->success) {
            return null;
        }

        $guidance = [
            'should_retry' => $this->shouldRetry($confidence),
            'suggested_delay_seconds' => $this->calculateDelay($taskRun),
            'max_retry_attempts' => $this->getMaxRetries($taskRun),
            'suggested_actions' => $this->getSuggestedActions($taskRun),
        ];

        return $guidance;
    }

    private function shouldRetry(float $confidence): bool
    {
        // Retry if confidence is above a minimum threshold
        return $confidence > 0.1;
    }

    private function calculateDelay(TaskRun $taskRun): int
    {
        // Exponential backoff based on previous failures
        $failures = TaskRun::where('task_name', $taskRun->task_name)
            ->where('success', false)
            ->where('id', '<', $taskRun->id)
            ->count();

        // Base delay of 5 seconds, doubled for each failure, max 300 seconds
        return min(5 * pow(2, $failures), 300);
    }

    private function getMaxRetries(TaskRun $taskRun): int
    {
        return match($taskRun->failure_type) {
            FailureType::TIMEOUT => 2,
            FailureType::RESOURCE_ERROR => 3,
            FailureType::VALIDATION_ERROR => 1,
            FailureType::SYNTAX_ERROR => 0,
            default => 3,
        };
    }

    private function getSuggestedActions(TaskRun $taskRun): array
    {
        return match($taskRun->failure_type) {
            FailureType::SYNTAX_ERROR => [
                'Review code syntax',
                'Check for typos or missing semicolons',
            ],
            FailureType::LOGIC_ERROR => [
                'Review business logic',
                'Check input validation',
            ],
            FailureType::TIMEOUT => [
                'Increase timeout limit',
                'Optimize query performance',
                'Check for deadlocks',
            ],
            FailureType::RESOURCE_ERROR => [
                'Check system resources (CPU, memory, disk)',
                'Verify external dependencies are available',
            ],
            FailureType::VALIDATION_ERROR => [
                'Review input data format',
                'Check validation rules',
            ],
            FailureType::ASSERTION_FAILURE => [
                'Review test assertions',
                'Verify expected vs actual output',
            ],
            default => [
                'Review error logs',
                'Check system status',
            ],
        };
    }
}
