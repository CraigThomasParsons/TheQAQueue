<?php

use App\Http\Controllers\Api\TaskEvaluationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/evaluate', [TaskEvaluationController::class, 'evaluate']);
    Route::get('/evaluations', [TaskEvaluationController::class, 'index']);
    Route::get('/evaluations/{id}', [TaskEvaluationController::class, 'show']);
});
