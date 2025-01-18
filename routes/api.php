<?php

use App\Http\Controllers\TenantControllerWeb;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ProjectController;

use App\Http\Middleware\VerifyInternalRequest;

// Public routes
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'getUser']);
    Route::put('/user', [UserController::class, 'updateUser']);
    Route::delete('/user', [UserController::class, 'deleteUser']);
    Route::post('/logout', [UserController::class, 'logout']);
});



Route::middleware([VerifyInternalRequest::class])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
});

Route::middleware([VerifyInternalRequest::class])->group(function () {
    Route::apiResource('tenants', TenantControllerWeb::class);
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
