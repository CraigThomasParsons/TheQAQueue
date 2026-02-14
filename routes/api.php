<?php

use App\Http\Controllers\Api\TaskApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CCDF Agent API Routes
|--------------------------------------------------------------------------
|
| These routes are used by CCDF agents (Vera, Mason, Piper) to interact
| with the QAQueue. They are stateless and typically called by systemd
| services or scheduled jobs.
|
*/

// Queue statistics (Mason)
Route::get('/queue/stats', [TaskApiController::class, 'queueStats']);
Route::get('/queue/provider-stats', [TaskApiController::class, 'providerStats']);

// Task retrieval
Route::get('/tasks/next-for-qa', [TaskApiController::class, 'nextForQa']);
Route::get('/tasks/next-for-execution', [TaskApiController::class, 'nextForExecution']);
Route::get('/tasks/retry-queue', [TaskApiController::class, 'retryQueue']);

// Task ingestion (Mason/DevBacklog)
Route::post('/tasks', [TaskApiController::class, 'createTask']);
Route::post('/tasks/bulk', [TaskApiController::class, 'createBulkTasks']);

// Task operations
Route::post('/tasks/{task}/claim', [TaskApiController::class, 'claim']);
Route::post('/tasks/{task}/verdict', [TaskApiController::class, 'verdict']);
Route::post('/tasks/{task}/confirm', [TaskApiController::class, 'confirm']);
Route::post('/tasks/{task}/start-run', [TaskApiController::class, 'startRun']);
Route::post('/tasks/{task}/complete-run', [TaskApiController::class, 'completeRun']);
