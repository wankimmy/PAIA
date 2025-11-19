<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $baseUrl;
    protected string $model;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.base_url', env('OLLAMA_BASE_URL', 'http://host.docker.internal:11434'));
        $this->model = config('services.ollama.model', env('OLLAMA_MODEL', 'llama3.2'));
    }

    public function chat(string $message, array $context = []): string
    {
        $systemPrompt = $this->buildSystemPrompt($context);

        try {
            $response = Http::timeout(120)->post("{$this->baseUrl}/api/chat", [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $message,
                    ],
                ],
                'stream' => false,
            ]);

            if ($response->successful()) {
                return $response->json('message.content', '');
            }

            Log::error('Ollama API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return 'Sorry, I encountered an error processing your request.';
        } catch (\Exception $e) {
            Log::error('Ollama service exception', ['error' => $e->getMessage()]);
            return 'Sorry, I could not connect to the AI service.';
        }
    }

    public function parseVoiceCommand(string $transcribedText, array $context = []): array
    {
        $systemPrompt = $this->buildActionParsingPrompt($context);

        try {
            $response = Http::timeout(120)->post("{$this->baseUrl}/api/chat", [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $transcribedText,
                    ],
                ],
                'stream' => false,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $content = $response->json('message.content', '{}');
                $json = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->normalizeActionResponse($json);
                }
            }

            Log::error('Ollama action parsing error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['tasks' => [], 'reminders' => [], 'notes' => [], 'passwords' => []];
        } catch (\Exception $e) {
            Log::error('Ollama action parsing exception', ['error' => $e->getMessage()]);
            return ['tasks' => [], 'reminders' => [], 'notes' => [], 'passwords' => []];
        }
    }

    protected function buildSystemPrompt(array $context): string
    {
        $prompt = "You are a helpful personal AI assistant. ";
        
        if (!empty($context['tasks'])) {
            $prompt .= "\n\nCurrent tasks:\n";
            foreach ($context['tasks'] as $task) {
                $prompt .= "- {$task['title']} (Status: {$task['status']})\n";
            }
        }

        $prompt .= "\n\nProvide helpful, concise responses to the user's questions and requests.";
        
        return $prompt;
    }

    protected function buildActionParsingPrompt(array $context): string
    {
        return "You are a personal AI assistant that converts natural language commands into structured JSON actions.

When the user speaks a command, parse it and return ONLY valid JSON in this exact format:
{
  \"tasks\": [
    {
      \"title\": \"string\",
      \"description\": \"string or null\",
      \"due_at\": \"ISO8601 timestamp or null\"
    }
  ],
  \"reminders\": [
    {
      \"task_index\": 0,
      \"remind_at\": \"ISO8601 timestamp\"
    }
  ],
  \"notes\": [
    {
      \"title\": \"string\",
      \"body\": \"string\"
    }
  ],
  \"passwords\": [
    {
      \"label\": \"string\",
      \"username\": \"string\",
      \"password\": \"string\",
      \"notes\": \"string or null\"
    }
  ]
}

Rules:
- Return ONLY the JSON object, no other text
- If a field is not mentioned, use null or empty array
- Parse dates/times from natural language (e.g., \"tomorrow at 9 PM\" -> ISO8601)
- task_index in reminders refers to the index in the tasks array
- If no actions are needed, return empty arrays

Current time: " . now()->toIso8601String();
    }

    protected function normalizeActionResponse(array $data): array
    {
        return [
            'tasks' => $data['tasks'] ?? [],
            'reminders' => $data['reminders'] ?? [],
            'notes' => $data['notes'] ?? [],
            'passwords' => $data['passwords'] ?? [],
        ];
    }
}

