<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create task-related tables for CCDF.
 *
 * Tasks are granular execution units created by Mason from Stories.
 * They track execution attempts, provider routing, and verdicts.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Task statuses for workflow state machine
        Schema::create('task_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // Tasks - granular execution units decomposed from stories
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('task_uuid')->unique();
            
            // Lineage tracking
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('epic_id')->nullable()->constrained('epics')->nullOnDelete();
            
            // Task definition
            $table->string('title');
            $table->text('description');
            $table->json('success_criteria');
            $table->json('constraints')->nullable();
            $table->json('inputs')->nullable();
            
            // Execution mode
            $table->enum('mode', ['create_new', 'modify_existing', 'scaffold', 'analyze'])
                  ->default('modify_existing');
            $table->json('expected_outputs')->nullable();
            
            // Retry tracking
            $table->integer('attempt')->default(0);
            $table->integer('max_attempts')->default(3);
            
            // Provider tracking
            $table->string('last_provider')->nullable();
            $table->string('claimed_by')->nullable();
            $table->timestamp('claimed_at')->nullable();
            
            // Status
            $table->foreignId('task_status_id')->constrained('task_statuses');
            
            // Priority and ordering
            $table->integer('priority')->default(0);
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['task_status_id', 'priority']);
            $table->index(['story_id', 'sort_order']);
            $table->index('claimed_by');
        });

        // Task runs - each execution attempt
        Schema::create('task_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_uuid')->unique();
            
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->integer('attempt_number');
            
            // Provider info
            $table->string('provider_name');
            $table->float('confidence_weight')->default(1.0);
            
            // Execution results
            $table->enum('execution_status', ['pending', 'running', 'success', 'failure', 'provider_failure'])
                  ->default('pending');
            $table->json('files_modified')->nullable();
            $table->text('diff_summary')->nullable();
            $table->longText('logs')->nullable();
            $table->integer('duration_ms')->nullable();
            
            // Artifact paths
            $table->string('artifacts_path')->nullable();
            
            $table->timestamps();
            
            $table->index(['task_id', 'attempt_number']);
            $table->index('execution_status');
        });

        // Task verdicts - QA evaluation results
        Schema::create('task_verdicts', function (Blueprint $table) {
            $table->id();
            $table->uuid('verdict_uuid')->unique();
            
            $table->foreignId('task_run_id')->constrained('task_runs')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            
            // Verdict
            $table->enum('verdict', ['pass', 'fail'])->nullable();
            $table->float('confidence')->nullable();
            $table->text('reasoning')->nullable();
            $table->json('observations')->nullable();
            
            // Evidence
            $table->string('evidence_path')->nullable();
            $table->json('screenshot_paths')->nullable();
            
            // Evaluator info
            $table->string('evaluated_by')->default('vera');
            $table->string('evaluator_model')->nullable();
            
            $table->timestamps();
            
            $table->index(['task_id', 'verdict']);
        });

        // Seed task statuses
        DB::table('task_statuses')->insert([
            ['key' => 'pending', 'name' => 'Pending', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'queued', 'name' => 'Queued for Execution', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'claimed', 'name' => 'Claimed by Provider', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'running', 'name' => 'Running', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'awaiting_qa', 'name' => 'Awaiting QA', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'in_qa', 'name' => 'In QA', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'passed', 'name' => 'Passed', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'failed', 'name' => 'Failed', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'retry', 'name' => 'Queued for Retry', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'exhausted', 'name' => 'Retries Exhausted', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'escalated', 'name' => 'Escalated to Human', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('task_verdicts');
        Schema::dropIfExists('task_runs');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_statuses');
    }
};
