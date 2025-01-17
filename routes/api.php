<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TaskController;
use App\Http\Controllers\ProjectController;

use App\Http\Middleware\VerifyInternalRequest;

Route::middleware([VerifyInternalRequest::class])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
});

Route::middleware([VerifyInternalRequest::class])->group(function () {
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    Route::post('/tasks/reorder', [TaskController::class, 'reorder']);
});



Route::fallback(function () {
    return response()->json(['error' => 'Route not found'], 404);
});
