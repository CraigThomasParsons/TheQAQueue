<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\TaskQueueController;

/**
 * Home route - redirects to the testing queue dashboard
 */
Route::get('/', function () {
    return redirect()->route('queue.index');
});

/**
 * Testing Queue Routes
 *
 * These routes handle the QA testing workflow:
 * - View the queue dashboard
 * - Start testing a story
 * - Mark stories as passed or failed
 * - Return failed stories to development
 */
Route::get('/queue', [QueueController::class, 'index'])->name('queue.index');
Route::get('/queue/kanban', [QueueController::class, 'kanban'])->name('queue.kanban');
Route::get('/queue/tasks', [TaskQueueController::class, 'index'])->name('queue.tasks.index');
Route::get('/queue/{story}', [QueueController::class, 'show'])->name('queue.show');
Route::post('/queue/{story}/start-testing', [QueueController::class, 'startTesting'])->name('queue.start-testing');
Route::post('/queue/{story}/pass', [QueueController::class, 'markPassed'])->name('queue.mark-passed');
Route::post('/queue/{story}/fail', [QueueController::class, 'markFailed'])->name('queue.mark-failed');
Route::post('/queue/{story}/return', [QueueController::class, 'returnToQueue'])->name('queue.return-to-queue');
