<?php

use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [UserController::class, 'register'])->name('register');
Route::post('/login', [UserController::class, 'login'])->name('login');
Route::get('/invitations/{token}', [InvitationAcceptController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationAcceptController::class, 'store'])->name('invitations.accept');

// Everything else requires a Sanctum bearer token. Data access is scoped to
// the token owner's tenant by the models' global scope plus policies.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'getUser'])->name('user.show');
    Route::put('/user', [UserController::class, 'updateUser'])->name('user.update');
    Route::delete('/user', [UserController::class, 'deleteUser'])->name('user.destroy');
    Route::post('/logout', [UserController::class, 'logout'])->name('logout');

    Route::get('/tenant', [TenantController::class, 'show'])->name('tenant.show');
    Route::put('/tenant', [TenantController::class, 'update'])->name('tenant.update');
    Route::get('/tenant/members', [MemberController::class, 'index'])->name('members.index');
    Route::delete('/tenant/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
    Route::get('/tenant/invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('/tenant/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('/tenant/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

    Route::get('/tokens', [TokenController::class, 'index'])->name('tokens.index');
    Route::post('/tokens', [TokenController::class, 'store'])->name('tokens.store');
    Route::delete('/tokens/{token}', [TokenController::class, 'destroy'])->name('tokens.destroy');

    Route::apiResource('projects', ProjectController::class);

    Route::post('/tasks/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder');
    Route::apiResource('tasks', TaskController::class);
});

Route::fallback(function () {
    return response()->json(['status' => 'error', 'message' => 'Route not found'], 404);
});
