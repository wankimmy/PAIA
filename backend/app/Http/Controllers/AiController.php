<?php

namespace App\Http\Controllers;

use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AiController extends Controller
{
    protected OllamaService $ollama;

    public function __construct(OllamaService $ollama)
    {
        $this->ollama = $ollama;
    }

    public function chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Build context from user's tasks
        $tasks = $request->user()->tasks()
            ->where('status', '!=', 'done')
            ->orderBy('due_at', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($task) {
                return [
                    'title' => $task->title,
                    'status' => $task->status,
                    'due_at' => $task->due_at?->toIso8601String(),
                ];
            })
            ->toArray();

        $context = ['tasks' => $tasks];
        $response = $this->ollama->chat($request->message, $context);

        return response()->json([
            'response' => $response,
        ]);
    }
}

