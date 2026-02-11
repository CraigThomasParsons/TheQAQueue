<?php

namespace Tests\Feature;

use App\Models\TaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskEvaluationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_evaluate_successful_task_run(): void
    {
        $response = $this->postJson('/api/v1/evaluate', [
            'task_name' => 'test_task',
            'success' => true,
            'execution_time_ms' => 100,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'task_run_id',
                'verdict',
                'confidence',
                'confidence_delta',
                'should_escalate',
                'evaluation_id',
            ])
            ->assertJson([
                'verdict' => 'pass',
                'should_escalate' => false,
            ]);

        $this->assertDatabaseHas('task_runs', [
            'task_name' => 'test_task',
            'success' => true,
        ]);
    }

    public function test_can_evaluate_failed_task_run(): void
    {
        $response = $this->postJson('/api/v1/evaluate', [
            'task_name' => 'test_task',
            'success' => false,
            'failure_type' => 'logic_error',
            'error_message' => 'Something went wrong',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'verdict' => 'fail',
            ]);

        $this->assertDatabaseHas('task_runs', [
            'task_name' => 'test_task',
            'success' => false,
            'failure_type' => 'logic_error',
        ]);
    }

    public function test_confidence_decreases_on_failure(): void
    {
        // First successful run
        $response1 = $this->postJson('/api/v1/evaluate', [
            'task_name' => 'test_task',
            'success' => true,
        ]);
        
        $confidence1 = $response1->json('confidence');

        // Second failed run - confidence should decrease
        $response2 = $this->postJson('/api/v1/evaluate', [
            'task_name' => 'test_task',
            'success' => false,
            'failure_type' => 'timeout',
        ]);

        $confidence2 = $response2->json('confidence');
        $delta = $response2->json('confidence_delta');

        $this->assertLessThan($confidence1, $confidence2);
        $this->assertLessThan(0, $delta);
    }

    public function test_escalation_on_multiple_failures(): void
    {
        // Create multiple failures
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/evaluate', [
                'task_name' => 'failing_task',
                'success' => false,
                'failure_type' => 'logic_error',
            ]);
        }

        // Fourth failure should trigger escalation
        $response = $this->postJson('/api/v1/evaluate', [
            'task_name' => 'failing_task',
            'success' => false,
            'failure_type' => 'logic_error',
        ]);

        $response->assertJson([
            'should_escalate' => true,
        ]);
    }

    public function test_can_retrieve_evaluation_by_id(): void
    {
        $createResponse = $this->postJson('/api/v1/evaluate', [
            'task_name' => 'test_task',
            'success' => true,
        ]);

        $evaluationId = $createResponse->json('evaluation_id');

        $response = $this->getJson("/api/v1/evaluations/{$evaluationId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'evaluation' => ['id', 'verdict', 'confidence'],
                'task_run' => ['id', 'task_name', 'success'],
            ]);
    }

    public function test_can_list_evaluations(): void
    {
        // Create some evaluations
        $this->postJson('/api/v1/evaluate', [
            'task_name' => 'task1',
            'success' => true,
        ]);

        $this->postJson('/api/v1/evaluate', [
            'task_name' => 'task2',
            'success' => false,
            'failure_type' => 'timeout',
        ]);

        $response = $this->getJson('/api/v1/evaluations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'verdict', 'confidence', 'task_run'],
                ],
            ]);
    }

    public function test_validation_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/evaluate', [
            'task_name' => 'test_task',
            // missing 'success' field
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'messages',
            ]);
    }

    public function test_retry_guidance_provided_for_failures(): void
    {
        $response = $this->postJson('/api/v1/evaluate', [
            'task_name' => 'test_task',
            'success' => false,
            'failure_type' => 'timeout',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'retry_guidance' => [
                    'should_retry',
                    'suggested_delay_seconds',
                    'max_retry_attempts',
                    'suggested_actions',
                ],
            ]);

        $retryGuidance = $response->json('retry_guidance');
        $this->assertTrue($retryGuidance['should_retry']);
        $this->assertIsInt($retryGuidance['suggested_delay_seconds']);
        $this->assertIsArray($retryGuidance['suggested_actions']);
    }
}

