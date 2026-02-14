<?php

namespace App\Http\Controllers;

use App\Models\Task;

/**
 * TaskQueueController manages the automated task queue dashboard.
 *
 * Displays CCDF task execution pipeline states backed by tasks/task_runs/task_verdicts.
 */
class TaskQueueController extends Controller
{
    /**
     * Display task queue grouped by pipeline status.
     */
    public function index()
    {
        $baseQuery = Task::with(['story', 'epic', 'status', 'latestRunRecord']);

        $queuedTasks = (clone $baseQuery)
            ->byStatus('queued')
            ->orderByDesc('priority')
            ->orderBy('created_at')
            ->get();

        $runningTasks = (clone $baseQuery)
            ->byStatus('running')
            ->orderByDesc('priority')
            ->orderBy('updated_at')
            ->get();

        $awaitingQaTasks = (clone $baseQuery)
            ->byStatus('awaiting_qa')
            ->orderByDesc('priority')
            ->orderBy('updated_at')
            ->get();

        $retryTasks = (clone $baseQuery)
            ->byStatus('retry')
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->get();

        $passedTasks = (clone $baseQuery)
            ->byStatus('passed')
            ->orderByDesc('updated_at')
            ->take(20)
            ->get();

        $escalatedTasks = (clone $baseQuery)
            ->where(function ($q) {
                $q->byStatus('exhausted')
                  ->orWhere(fn ($sub) => $sub->byStatus('escalated'));
            })
            ->orderByDesc('updated_at')
            ->take(20)
            ->get();

        return view('queue.tasks', compact(
            'queuedTasks',
            'runningTasks',
            'awaitingQaTasks',
            'retryTasks',
            'passedTasks',
            'escalatedTasks'
        ));
    }
}
