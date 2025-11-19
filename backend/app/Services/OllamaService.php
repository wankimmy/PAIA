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

    public function parseActionsFromMessage(string $message, array $context = []): array
    {
        // Only parse if message contains action keywords
        $actionKeywords = ['create', 'add', 'make', 'set', 'remind', 'task', 'note', 'password', 'save'];
        $hasActionIntent = false;
        
        foreach ($actionKeywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                $hasActionIntent = true;
                break;
            }
        }

        if (!$hasActionIntent) {
            return ['tasks' => [], 'reminders' => [], 'notes' => [], 'passwords' => []];
        }

        return $this->parseVoiceCommand($message, $context);
    }

    public function extractMemories(string $userMessage, string $aiResponse, array $existingMemories = []): array
    {
        $prompt = "You are analyzing a conversation to extract new facts or preferences about the user.
        
Current known memories:
" . json_encode($existingMemories, JSON_PRETTY_PRINT) . "

User message: {$userMessage}
AI response: {$aiResponse}

Analyze this conversation and return ONLY a JSON object with new or updated memories.
Return format:
{
  \"memories_to_add_or_update\": [
    {
      \"category\": \"preference|habit|personal_fact|goal|boundary\",
      \"key\": \"unique_key_identifier\",
      \"value\": \"Short sentence describing the fact (max 100 chars)\",
      \"importance\": 1-5
    }
  ]
}

Rules:
- Only extract clear, factual information
- Do NOT extract passwords, secrets, or sensitive credentials
- Do NOT extract temporary information
- Focus on preferences, habits, goals, and personal facts
- If no new memories, return empty array
- Return ONLY valid JSON, no other text";

        try {
            $response = Http::timeout(60)->post("{$this->baseUrl}/api/chat", [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $prompt,
                    ],
                ],
                'stream' => false,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $content = $response->json('message.content', '{}');
                $json = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $json;
                }
            }

            return ['memories_to_add_or_update' => []];
        } catch (\Exception $e) {
            Log::error('Memory extraction error', ['error' => $e->getMessage()]);
            return ['memories_to_add_or_update' => []];
        }
    }

    protected function buildSystemPrompt(array $context): string
    {
        $aiName = !empty($context['profile']['ai_name']) ? $context['profile']['ai_name'] : 'your assistant';
        
        $prompt = "You are a personal AI assistant and trusted friend. ";
        $prompt .= "Your name is {$aiName}. ";
        $prompt .= "Your goal is to make the user feel safe, respected, and understood. ";
        $prompt .= "Be empathetic, supportive, and non-judgmental. ";
        $prompt .= "Respect their preferences and boundaries. ";
        $prompt .= "If something touches a sensitive area, gently ask permission before going deeper.\n\n";

        // Add user profile context
        if (!empty($context['profile'])) {
            $profile = $context['profile'];
            $prompt .= "USER PROFILE:\n";
            
            if (!empty($profile['nickname'])) {
                $prompt .= "- Name/Nickname: {$profile['nickname']}\n";
            } elseif (!empty($profile['name'])) {
                $prompt .= "- Name: {$profile['name']}\n";
            }
            
            if (!empty($profile['pronouns'])) {
                $prompt .= "- Pronouns: {$profile['pronouns']}\n";
            }
            
            if (!empty($profile['bio'])) {
                $prompt .= "- Bio: {$profile['bio']}\n";
            }
            
            if (!empty($profile['timezone'])) {
                $prompt .= "- Timezone: {$profile['timezone']}\n";
            }
            
            if (!empty($profile['preferred_tone'])) {
                $prompt .= "- Preferred tone: {$profile['preferred_tone']}\n";
            }
            
            if (!empty($profile['preferred_answer_length'])) {
                $prompt .= "- Preferred answer length: {$profile['preferred_answer_length']}\n";
            }
            
            $prompt .= "\n";
        }

        // Add persistent memories
        if (!empty($context['memories']) && is_array($context['memories'])) {
            $prompt .= "PERSISTENT MEMORIES ABOUT THE USER:\n";
            foreach ($context['memories'] as $memory) {
                if (isset($memory['value'])) {
                    $prompt .= "- {$memory['value']}\n";
                }
            }
            $prompt .= "\n";
        }

        // Add user preferences context
        if (!empty($context['user_preferences'])) {
            $prompt .= "User preferences:\n";
            foreach ($context['user_preferences'] as $key => $value) {
                $prompt .= "- {$key}: {$value}\n";
            }
            $prompt .= "\n";
        }

        // Add AI context (learned behavior)
        if (!empty($context['ai_context'])) {
            $prompt .= "Additional context:\n";
            foreach ($context['ai_context'] as $key => $value) {
                $prompt .= "- {$key}: {$value}\n";
            }
            $prompt .= "\n";
        }

        if (!empty($context['tasks'])) {
            $prompt .= "CURRENT TASKS:\n";
            foreach ($context['tasks'] as $task) {
                $prompt .= "- {$task['title']} (Status: {$task['status']})\n";
            }
            $prompt .= "\n";
        }

        // Add recent actions context
        if (!empty($context['recent_actions'])) {
            $prompt .= "I just executed these actions:\n";
            if (!empty($context['recent_actions']['tasks_created'])) {
                $prompt .= "- Created {$context['recent_actions']['tasks_created']} task(s)\n";
            }
            if (!empty($context['recent_actions']['notes_created'])) {
                $prompt .= "- Created {$context['recent_actions']['notes_created']} note(s)\n";
            }
            if (!empty($context['recent_actions']['passwords_created'])) {
                $prompt .= "- Created {$context['recent_actions']['passwords_created']} password entry/entries\n";
            }
            $prompt .= "\nAcknowledge these actions in your response.\n\n";
        }

        $prompt .= "YOUR CAPABILITIES:\n";
        $prompt .= "- Creating tasks, notes, and password entries\n";
        $prompt .= "- Setting reminders\n";
        $prompt .= "- Answering questions and providing support\n";
        $prompt .= "- Learning about their preferences and behavior\n";
        $prompt .= "\n";
        $prompt .= "IMPORTANT GUIDELINES:\n";
        $prompt .= "- Use their name/nickname naturally when appropriate\n";
        $prompt .= "- Match their preferred tone and answer length\n";
        $prompt .= "- Respect boundaries and sensitive topics\n";
        $prompt .= "- Be honest about limitations\n";
        $prompt .= "- When you use their personal facts, do it gently and only when helpful\n";
        $prompt .= "- Provide helpful, supportive responses. When actions are executed automatically, acknowledge them naturally.";
        
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
