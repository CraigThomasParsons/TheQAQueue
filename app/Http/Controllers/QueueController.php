<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Http\Request;

/**
 * QueueController manages the testing queue.
 *
 * Displays stories that are completed and ready for QA testing.
 * Stories move through: Ready → In Testing → Passed/Failed
 */
class QueueController extends Controller
{
    /**
     * Display the main testing queue dashboard.
     *
     * Shows stories grouped by their testing status.
     */
    public function index()
    {
        // Get stories that are ready for testing (completed development)
        $readyForTestingStories = Story::where('story_status_id', 3) // completed
            ->with(['epic', 'persona'])
            ->orderBy('priority', 'desc')
            ->get();

        // Get stories currently being tested
        $inTestingStories = Story::where('story_status_id', 4) // in_testing
            ->with(['epic', 'persona'])
            ->orderBy('updated_at', 'asc')
            ->get();

        // Get recently passed stories (last 10)
        $passedStories = Story::where('story_status_id', 5) // passed
            ->with(['epic', 'persona'])
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get();

        // Get failed stories that need rework
        $failedStories = Story::where('story_status_id', 6) // failed
            ->with(['epic', 'persona'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('queue.index', compact(
            'readyForTestingStories',
            'inTestingStories',
            'passedStories',
            'failedStories'
        ));
    }

    /**
     * Display a 4-column Kanban board for QA workflow.
     *
     * Columns:
     * - Ready to test (Queued)
     * - In Testing
     * - Sent Back
     * - Done
     */
    public function kanban()
    {
        // Ready to test (Queued): development completed and waiting for QA pickup.
        $readyQueuedStories = Story::where('story_status_id', 3)
            ->with(['epic', 'persona'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at')
            ->get();

        // In Testing: currently in QA execution.
        $inTestingStories = Story::where('story_status_id', 4)
            ->with(['epic', 'persona'])
            ->orderBy('updated_at', 'asc')
            ->get();

        // Sent Back: failed QA and sent back for rework.
        $sentBackStories = Story::where('story_status_id', 6)
            ->with(['epic', 'persona'])
            ->orderBy('updated_at', 'desc')
            ->get();

        // Done: QA passed.
        $doneStories = Story::where('story_status_id', 5)
            ->with(['epic', 'persona'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('queue.kanban', compact(
            'readyQueuedStories',
            'inTestingStories',
            'sentBackStories',
            'doneStories'
        ));
    }

    /**
     * Start testing a story.
     *
     * Moves story from ready queue to in-testing status.
     */
    public function startTesting(Story $story)
    {
        // Guard: only completed stories can start testing
        if ($story->story_status_id !== 3) {
            return back()->with('error', 'Only completed stories can be tested.');
        }

        // Update status to in_testing (status id 4)
        $story->update(['story_status_id' => 4]);

        return back()->with('success', "Started testing: {$story->title}");
    }

    /**
     * Mark a story as passed.
     *
     * Moves story from in-testing to passed status.
     */
    public function markPassed(Story $story)
    {
        // Guard: only in-testing stories can pass
        if ($story->story_status_id !== 4) {
            return back()->with('error', 'Only stories in testing can be marked as passed.');
        }

        // Update status to passed (status id 5)
        $story->update(['story_status_id' => 5]);

        return back()->with('success', "✅ Passed: {$story->title}");
    }

    /**
     * Mark a story as failed.
     *
     * Moves story from in-testing to failed status.
     */
    public function markFailed(Request $request, Story $story)
    {
        // Guard: only in-testing stories can fail
        if ($story->story_status_id !== 4) {
            return back()->with('error', 'Only stories in testing can be marked as failed.');
        }

        // Update status to failed (status id 6) and store failure notes
        $story->update([
            'story_status_id' => 6,
        ]);

        return back()->with('success', "❌ Failed: {$story->title}");
    }

    /**
     * Return a failed story to the development queue.
     *
     * Resets story to in-progress status for rework.
     */
    public function returnToQueue(Story $story)
    {
        // Guard: only failed stories can be returned
        if ($story->story_status_id !== 6) {
            return back()->with('error', 'Only failed stories can be returned to queue.');
        }

        // Reset to draft status (status id 1) for rework
        $story->update(['story_status_id' => 1]);

        return back()->with('success', "Returned to development: {$story->title}");
    }

    /**
     * Show details of a specific story in the queue.
     *
     * Displays full story details including acceptance criteria.
     */
    public function show(Story $story)
    {
        $story->load(['epic', 'persona', 'status']);

        return view('queue.show', compact('story'));
    }
}
