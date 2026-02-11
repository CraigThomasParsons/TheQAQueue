<?php

namespace Tests\Unit;

use App\Models\TaskRun;
use App\Services\ConfidenceCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfidenceCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConfidenceCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConfidenceCalculationService();
    }

    public function test_initial_confidence_is_0_5(): void
    {
        $taskRun = TaskRun::create([
            'task_name' => 'test_task',
            'success' => true,
        ]);

        $result = $this->service->calculateConfidence($taskRun);

        $this->assertEquals(0.5, $result['previous_confidence']);
    }

    public function test_confidence_increases_on_success(): void
    {
        $taskRun = TaskRun::create([
            'task_name' => 'test_task',
            'success' => true,
        ]);

        $result = $this->service->calculateConfidence($taskRun);

        $this->assertGreaterThan(0.5, $result['confidence']);
        $this->assertGreaterThan(0, $result['confidence_delta']);
    }

    public function test_confidence_decreases_on_failure(): void
    {
        $taskRun = TaskRun::create([
            'task_name' => 'test_task',
            'success' => false,
            'failure_type' => 'logic_error',
        ]);

        $result = $this->service->calculateConfidence($taskRun);

        $this->assertLessThan(0.5, $result['confidence']);
        $this->assertLessThan(0, $result['confidence_delta']);
    }

    public function test_multiplicative_decay_on_failure(): void
    {
        $taskRun = TaskRun::create([
            'task_name' => 'test_task',
            'success' => false,
            'failure_type' => 'timeout',
        ]);

        $result = $this->service->calculateConfidence($taskRun);

        // Starting at 0.5, applying 0.8 decay should give 0.4
        $this->assertEquals(0.4, $result['confidence']);
    }

    public function test_diminishing_success_gain(): void
    {
        $taskName = 'consistent_task';

        // First success
        $run1 = TaskRun::create([
            'task_name' => $taskName,
            'success' => true,
        ]);
        $result1 = $this->service->calculateConfidence($run1);
        $gain1 = $result1['confidence_delta'];

        // Store first evaluation
        \App\Models\TaskEvaluation::create([
            'task_run_id' => $run1->id,
            'verdict' => 'pass',
            'confidence' => $result1['confidence'],
            'confidence_delta' => $result1['confidence_delta'],
        ]);

        // Second success
        $run2 = TaskRun::create([
            'task_name' => $taskName,
            'success' => true,
        ]);
        $result2 = $this->service->calculateConfidence($run2);
        $gain2 = $result2['confidence_delta'];

        // Second gain should be smaller due to diminishing returns
        $this->assertLessThan($gain1, $gain2);
    }

    public function test_confidence_clamped_between_0_and_1(): void
    {
        // Test upper bound
        $successTask = TaskRun::create([
            'task_name' => 'success_task',
            'success' => true,
        ]);
        
        // Create many successes to push confidence towards 1
        for ($i = 0; $i < 10; $i++) {
            $result = $this->service->calculateConfidence($successTask);
            
            if ($result['confidence'] > 0.99) {
                \App\Models\TaskEvaluation::create([
                    'task_run_id' => $successTask->id,
                    'verdict' => 'pass',
                    'confidence' => $result['confidence'],
                    'confidence_delta' => $result['confidence_delta'],
                ]);
                
                $successTask = TaskRun::create([
                    'task_name' => 'success_task',
                    'success' => true,
                ]);
            }
        }

        $finalResult = $this->service->calculateConfidence($successTask);
        $this->assertLessThanOrEqual(1.0, $finalResult['confidence']);

        // Test lower bound
        $failTask = TaskRun::create([
            'task_name' => 'fail_task',
            'success' => false,
            'failure_type' => 'logic_error',
        ]);

        // Create many failures to push confidence towards 0
        for ($i = 0; $i < 20; $i++) {
            $result = $this->service->calculateConfidence($failTask);
            
            \App\Models\TaskEvaluation::create([
                'task_run_id' => $failTask->id,
                'verdict' => 'fail',
                'confidence' => $result['confidence'],
                'confidence_delta' => $result['confidence_delta'],
            ]);
            
            $failTask = TaskRun::create([
                'task_name' => 'fail_task',
                'success' => false,
                'failure_type' => 'logic_error',
            ]);
        }

        $finalResult = $this->service->calculateConfidence($failTask);
        $this->assertGreaterThanOrEqual(0.0, $finalResult['confidence']);
    }
}

