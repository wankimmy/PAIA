<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\VoiceController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/request-otp', [AuthController::class, 'requestOtp']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    // Tasks
    Route::apiResource('tasks', TaskController::class);

    // Notes
    Route::apiResource('notes', NoteController::class);

    // Passwords
    Route::apiResource('passwords', PasswordController::class);

    // Push subscriptions
    Route::post('/push/subscribe', [PushController::class, 'subscribe']);
    Route::delete('/push/unsubscribe', [PushController::class, 'unsubscribe']);

    // AI
    Route::post('/ai/chat', [AiController::class, 'chat']);
    Route::post('/voice/command', [VoiceController::class, 'command']);

    // Export
    Route::get('/export/txt', [ExportController::class, 'txt']);
});

