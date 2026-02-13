<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create all WritersRoom v1 tables.
 *
 * This migration handles the full schema including lookup tables,
 * personas, epics, stories, sprints and their relationships.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates lookup tables first, then core entities,
     * and finally the pivot table for sprint-story relationships.
     */
    public function up(): void
    {
        // Disable foreign key checks to allow dropping old tables
        Schema::disableForeignKeyConstraints();

        // Drop old tables that conflict with new schema
        Schema::dropIfExists('sprint_stories');
        Schema::dropIfExists('sprints');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('epics');

        // Re-enable foreign key checks before creating new tables
        Schema::enableForeignKeyConstraints();

        // Create status lookup tables for epic workflow states
        if (! Schema::hasTable('epic_statuses')) {
            Schema::create('epic_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->timestamps();
            });
        }

        // Create status lookup tables for story workflow states
        if (! Schema::hasTable('story_statuses')) {
            Schema::create('story_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->timestamps();
            });
        }

        // Create status lookup tables for sprint workflow states
        if (! Schema::hasTable('sprint_statuses')) {
            Schema::create('sprint_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->timestamps();
            });
        }

        // Create personas table for user story actors
        if (! Schema::hasTable('personas')) {
            Schema::create('personas', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('summary')->nullable();
                $table->text('details')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Create epics table for grouping related stories
        Schema::create('epics', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->foreignId('epic_status_id')->constrained('epic_statuses');
            $table->timestamps();
        });

        // Create stories table for individual work items
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('epic_id')->nullable()->constrained('epics')->nullOnDelete();
            $table->foreignId('persona_id')->nullable()->constrained('personas')->nullOnDelete();

            $table->string('title');
            $table->text('narrative');
            $table->longText('acceptance_criteria')->nullable();

            $table->foreignId('story_status_id')->constrained('story_statuses');

            $table->integer('priority')->default(0);
            $table->integer('est_points')->nullable();
            $table->timestamps();

            // Index for efficient filtering by status and priority
            $table->index(['story_status_id', 'priority']);
        });

        // Create sprints table for time-boxed iterations
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('goal');
            $table->longText('success_criteria')->nullable();

            $table->foreignId('sprint_status_id')->constrained('sprint_statuses');

            $table->boolean('is_frozen')->default(false);
            $table->timestamp('frozen_at')->nullable();

            $table->timestamps();
        });

        // Create pivot table for sprint-story many-to-many relationship
        Schema::create('sprint_stories', function (Blueprint $table) {
            $table->foreignId('sprint_id')->constrained('sprints')->cascadeOnDelete();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);

            $table->primary(['sprint_id', 'story_id']);

            // Index for ordering stories within a sprint
            $table->index(['sprint_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops tables in reverse order to respect foreign key constraints.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprint_stories');
        Schema::dropIfExists('sprints');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('epics');
        Schema::dropIfExists('personas');
        Schema::dropIfExists('sprint_statuses');
        Schema::dropIfExists('story_statuses');
        Schema::dropIfExists('epic_statuses');
    }
};
