<?php

namespace App\Services;

use App\Models\TaskRun;

class ConfidenceCalculationService
{
    private const INITIAL_CONFIDENCE = 0.5;
    private const FAILURE_DECAY_RATE = 0.8;
    private const SUCCESS_GAIN_BASE = 0.1;
    private const SUCCESS_DIMINISHING_FACTOR = 0.9;

    public function calculateConfidence(TaskRun $taskRun): array
    {
        $taskName = $taskRun->task_name;
        
        // Get historical runs for this task
        $historicalRuns = TaskRun::where('task_name', $taskName)
            ->where('id', '<', $taskRun->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $previousConfidence = $this->getPreviousConfidence($taskName, $historicalRuns);
        
        if ($taskRun->success) {
            $newConfidence = $this->applySuccessGain($previousConfidence, $historicalRuns);
        } else {
            $newConfidence = $this->applyFailureDecay($previousConfidence);
        }

        // Clamp between 0 and 1
        $newConfidence = max(0, min(1, $newConfidence));
        $confidenceDelta = $newConfidence - $previousConfidence;

        return [
            'confidence' => round($newConfidence, 4),
            'confidence_delta' => round($confidenceDelta, 4),
            'previous_confidence' => round($previousConfidence, 4),
        ];
    }

    private function getPreviousConfidence(string $taskName, $historicalRuns): float
    {
        if ($historicalRuns->isEmpty()) {
            return self::INITIAL_CONFIDENCE;
        }

        // Get the most recent evaluation
        $lastRun = $historicalRuns->first();
        $lastEvaluation = $lastRun->evaluations()->latest()->first();

        return $lastEvaluation ? (float) $lastEvaluation->confidence : self::INITIAL_CONFIDENCE;
    }

    private function applyFailureDecay(float $currentConfidence): float
    {
        // Multiplicative decay: confidence * decay_rate
        return $currentConfidence * self::FAILURE_DECAY_RATE;
    }

    private function applySuccessGain(float $currentConfidence, $historicalRuns): float
    {
        // Count consecutive successes
        $consecutiveSuccesses = 0;
        foreach ($historicalRuns as $run) {
            if ($run->success) {
                $consecutiveSuccesses++;
            } else {
                break;
            }
        }

        // Diminishing returns: gain * (diminishing_factor ^ consecutive_successes)
        $gain = self::SUCCESS_GAIN_BASE * pow(self::SUCCESS_DIMINISHING_FACTOR, $consecutiveSuccesses);
        
        return $currentConfidence + $gain;
    }
}
