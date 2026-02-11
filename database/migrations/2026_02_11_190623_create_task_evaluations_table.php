<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_run_id')->constrained()->cascadeOnDelete();
            $table->string('verdict'); // 'pass', 'fail', 'escalate'
            $table->decimal('confidence', 5, 4); // 0.0000 to 1.0000
            $table->decimal('confidence_delta', 6, 4)->nullable(); // change from previous
            $table->json('retry_guidance')->nullable();
            $table->boolean('should_escalate')->default(false);
            $table->text('escalation_reason')->nullable();
            $table->json('evaluation_metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_evaluations');
    }
};
