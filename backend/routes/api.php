<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AiMemoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BehaviorInsightsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\VoiceController;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('/health', function () {
    $health = [
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ];
    
    // Check database
    try {
        \DB::connection()->getPdo();
        $health['database'] = 'connected';
    } catch (\Exception $e) {
        $health['status'] = 'error';
        $health['database'] = 'disconnected';
        $health['database_error'] = config('app.debug') ? $e->getMessage() : 'Database connection failed';
    }
    
    // Check Ollama
    try {
        $ollamaUrl = config('services.ollama.base_url', env('OLLAMA_BASE_URL', 'http://host.docker.internal:11434'));
        $model = config('services.ollama.model', env('OLLAMA_MODEL', 'llama3.2'));
        
        $response = \Illuminate\Support\Facades\Http::timeout(5)->get("{$ollamaUrl}/api/tags");
        
        if ($response->successful()) {
            $health['ollama'] = 'connected';
            $health['ollama_url'] = $ollamaUrl;
            $models = $response->json('models', []);
            $health['ollama_models'] = array_map(function($m) {
                return $m['name'] ?? $m;
            }, $models);
            $health['ollama_model_installed'] = collect($models)->contains(function($m) use ($model) {
                $name = is_array($m) ? ($m['name'] ?? '') : $m;
                return str_contains($name, $model);
            });
            $health['ollama_configured_model'] = $model;
        } else {
            $health['ollama'] = 'error';
            $health['ollama_error'] = 'Ollama returned status ' . $response->status();
            $health['ollama_url'] = $ollamaUrl;
        }
    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        $health['ollama'] = 'disconnected';
        $health['ollama_error'] = 'Connection failed: ' . $e->getMessage();
        $health['ollama_url'] = config('services.ollama.base_url', env('OLLAMA_BASE_URL', 'http://host.docker.internal:11434'));
        $health['troubleshooting'] = [
            '1. Ensure Ollama is running on your host machine',
            '2. Check if Ollama is accessible: curl http://localhost:11434/api/tags',
            '3. On Windows, verify host.docker.internal resolves correctly',
            '4. Check Windows Firewall allows connections on port 11434',
            '5. Try using your machine IP instead: OLLAMA_BASE_URL=http://192.168.x.x:11434'
        ];
    } catch (\Exception $e) {
        $health['ollama'] = 'disconnected';
        $health['ollama_error'] = config('app.debug') ? $e->getMessage() : 'Could not connect to Ollama';
        $health['ollama_url'] = config('services.ollama.base_url', env('OLLAMA_BASE_URL', 'http://host.docker.internal:11434'));
    }
    
    $statusCode = $health['status'] === 'ok' && ($health['ollama'] ?? '') === 'connected' ? 200 : 500;
    return response()->json($health, $statusCode);
});

// Public routes
Route::post('/auth/request-otp', [AuthController::class, 'requestOtp']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    // Tasks
    Route::apiResource('tasks', TaskController::class);

    // Meetings
    Route::apiResource('meetings', MeetingController::class);
    Route::post('/meetings/{id}/reminders', [MeetingController::class, 'addReminder']);

    // Tags
    Route::apiResource('tags', TagController::class);

    // Notes
    Route::apiResource('notes', NoteController::class);

    // Passwords
    Route::apiResource('passwords', PasswordController::class);

    // Push subscriptions
    Route::post('/push/subscribe', [PushController::class, 'subscribe']);
    Route::delete('/push/unsubscribe', [PushController::class, 'unsubscribe']);

    // AI
    Route::post('/ai/chat', [AiController::class, 'chat']);
    Route::get('/ai/chat/history', [AiController::class, 'getChatHistory']);
    Route::post('/voice/command', [VoiceController::class, 'command']);

    // User Preferences
    Route::get('/preferences', [UserPreferenceController::class, 'show']);
    Route::put('/preferences', [UserPreferenceController::class, 'update']);

    // User Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // AI Memories
    Route::get('/ai/memories', [AiMemoryController::class, 'index']);
    Route::post('/ai/memories', [AiMemoryController::class, 'store']);
    Route::put('/ai/memories/{id}', [AiMemoryController::class, 'update']);
    Route::delete('/ai/memories/{id}', [AiMemoryController::class, 'destroy']);

    // Behavior Insights
    Route::get('/behavior/insights', [BehaviorInsightsController::class, 'insights']);

    // Export
    Route::get('/export/txt', [ExportController::class, 'txt']);
    Route::get('/export/json', [ExportController::class, 'json']);
    
    // Import
    Route::post('/import/json', [ImportController::class, 'json']);
});

