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
            // First, check if Ollama is reachable
            $healthCheck = Http::timeout(5)->get("{$this->baseUrl}/api/tags");
            
            if (!$healthCheck->successful()) {
                Log::error('Ollama health check failed', [
                    'url' => $this->baseUrl,
                    'status' => $healthCheck->status(),
                    'body' => $healthCheck->body(),
                ]);
                
                return 'Ollama service is not reachable. Please ensure Ollama is running at ' . $this->baseUrl . ' and the model "' . $this->model . '" is installed.';
            }

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
                $content = $response->json('message.content', '');
                if (empty($content)) {
                    Log::warning('Ollama returned empty response', [
                        'response' => $response->json(),
                    ]);
                    return 'I received an empty response from the AI service. Please try again.';
                }
                return $content;
            }

            Log::error('Ollama API error', [
                'url' => "{$this->baseUrl}/api/chat",
                'model' => $this->model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $errorMessage = 'Sorry, I encountered an error processing your request.';
            if ($response->status() === 404) {
                $errorMessage = 'The AI model "' . $this->model . '" was not found. Please ensure it is installed in Ollama.';
            }
            
            return $errorMessage;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Ollama connection exception', [
                'url' => $this->baseUrl,
                'error' => $e->getMessage(),
            ]);
            
            $errorMsg = 'Could not connect to Ollama at ' . $this->baseUrl . '. ';
            $errorMsg .= 'Please ensure Ollama is running on your host machine. ';
            $errorMsg .= 'Check the /api/health endpoint for detailed diagnostics.';
            
            return $errorMsg;
        } catch (\Exception $e) {
            Log::error('Ollama service exception', [
                'url' => $this->baseUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $errorMsg = 'Sorry, I could not connect to the AI service. ';
            if (config('app.debug')) {
                $errorMsg .= 'Error: ' . $e->getMessage();
            } else {
                $errorMsg .= 'Please check if Ollama is running. Visit /api/health for diagnostics.';
            }
            
            return $errorMsg;
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
                'url' => "{$this->baseUrl}/api/chat",
                'model' => $this->model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['tasks' => [], 'reminders' => [], 'notes' => [], 'passwords' => [], 'meetings' => []];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Ollama connection exception in parseVoiceCommand', [
                'url' => $this->baseUrl,
                'error' => $e->getMessage(),
            ]);
            return ['tasks' => [], 'reminders' => [], 'notes' => [], 'passwords' => [], 'meetings' => []];
        } catch (\Exception $e) {
            Log::error('Ollama action parsing exception', [
                'url' => $this->baseUrl,
                'error' => $e->getMessage(),
            ]);
            return ['tasks' => [], 'reminders' => [], 'notes' => [], 'passwords' => [], 'meetings' => []];
        }
    }

    public function parseActionsFromMessage(string $message, array $context = []): array
    {
        // Only parse if message contains action keywords
        $actionKeywords = ['create', 'add', 'make', 'set', 'remind', 'task', 'note', 'password', 'save', 'meeting', 'schedule', 'calendar'];
        $hasActionIntent = false;
        
        foreach ($actionKeywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                $hasActionIntent = true;
                break;
            }
        }

        if (!$hasActionIntent) {
            return ['tasks' => [], 'reminders' => [], 'notes' => [], 'passwords' => [], 'meetings' => []];
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

        // Add recent chat history for context
        if (!empty($context['chat_history']) && is_array($context['chat_history'])) {
            $prompt .= "RECENT CONVERSATION HISTORY:\n";
            foreach ($context['chat_history'] as $chat) {
                if (!empty($chat['user_message']) && !empty($chat['ai_response'])) {
                    $prompt .= "User: {$chat['user_message']}\n";
                    $prompt .= "Assistant: {$chat['ai_response']}\n";
                    $prompt .= "---\n";
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

        if (!empty($context['meetings'])) {
            $prompt .= "UPCOMING MEETINGS:\n";
            foreach ($context['meetings'] as $meeting) {
                $prompt .= "- {$meeting['title']}";
                if (!empty($meeting['start_time'])) {
                    $prompt .= " at " . $meeting['start_time'];
                }
                if (!empty($meeting['location'])) {
                    $prompt .= " ({$meeting['location']})";
                }
                $prompt .= "\n";
            }
            $prompt .= "\n";
        }

        // Add clarification context if needed
        if (!empty($context['needs_clarification']) && !empty($context['clarification_question'])) {
            $prompt .= "CLARIFICATION NEEDED:\n";
            $prompt .= "The user's instruction was unclear or missing required information. ";
            $prompt .= "You MUST ask this specific question in your response: \"{$context['clarification_question']}\"\n";
            $prompt .= "Be friendly and helpful. Do NOT create any records until the user provides the missing information.\n\n";
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
            if (!empty($context['recent_actions']['meetings_created'])) {
                $prompt .= "- Created {$context['recent_actions']['meetings_created']} meeting(s)\n";
            }
            $prompt .= "\nAcknowledge these actions in your response.\n\n";
        }

        $prompt .= "DATABASE STRUCTURE - KNOW WHERE TO STORE DATA:\n";
        $prompt .= "You have access to the following database tables. Use this knowledge to understand what data goes where:\n\n";
        
        $prompt .= "1. TASKS TABLE (tasks):\n";
        $prompt .= "   - id (auto)\n";
        $prompt .= "   - user_id (auto, foreign key to users)\n";
        $prompt .= "   - title (REQUIRED, string)\n";
        $prompt .= "   - description (optional, text)\n";
        $prompt .= "   - status (default: 'pending', values: 'pending', 'done', 'cancelled')\n";
        $prompt .= "   - due_at (optional, timestamp)\n";
        $prompt .= "   - tag (optional, string - legacy field)\n";
        $prompt .= "   - tag_id (optional, foreign key to tags table)\n";
        $prompt .= "   - created_via (default: 'manual', values: 'manual', 'voice', 'ai')\n";
        $prompt .= "   - timestamps (created_at, updated_at)\n\n";
        
        $prompt .= "2. NOTES TABLE (notes):\n";
        $prompt .= "   - id (auto)\n";
        $prompt .= "   - user_id (auto, foreign key to users)\n";
        $prompt .= "   - title (REQUIRED, string)\n";
        $prompt .= "   - encrypted_body (REQUIRED, text - body content is encrypted)\n";
        $prompt .= "   - tag_id (optional, foreign key to tags table)\n";
        $prompt .= "   - timestamps (created_at, updated_at)\n\n";
        
        $prompt .= "3. PASSWORD_ENTRIES TABLE (password_entries):\n";
        $prompt .= "   - id (auto)\n";
        $prompt .= "   - user_id (auto, foreign key to users)\n";
        $prompt .= "   - label (REQUIRED, string - name/identifier for the password)\n";
        $prompt .= "   - username (REQUIRED, string)\n";
        $prompt .= "   - encrypted_password (REQUIRED, text - password is encrypted)\n";
        $prompt .= "   - encrypted_notes (optional, text - additional notes, encrypted)\n";
        $prompt .= "   - timestamps (created_at, updated_at)\n\n";
        
        $prompt .= "4. MEETINGS TABLE (meetings):\n";
        $prompt .= "   - id (auto)\n";
        $prompt .= "   - user_id (auto, foreign key to users)\n";
        $prompt .= "   - title (REQUIRED, string)\n";
        $prompt .= "   - description (optional, text)\n";
        $prompt .= "   - start_time (REQUIRED, timestamp)\n";
        $prompt .= "   - end_time (optional, timestamp - defaults to 1 hour after start_time if not provided)\n";
        $prompt .= "   - location (optional, string)\n";
        $prompt .= "   - attendees (optional, string - JSON array or comma-separated)\n";
        $prompt .= "   - status (default: 'scheduled', values: 'scheduled', 'cancelled', 'completed')\n";
        $prompt .= "   - created_via (default: 'manual', values: 'manual', 'voice', 'ai')\n";
        $prompt .= "   - tag_id (optional, foreign key to tags table)\n";
        $prompt .= "   - timestamps (created_at, updated_at)\n\n";
        
        $prompt .= "5. TAGS TABLE (tags):\n";
        $prompt .= "   - id (auto)\n";
        $prompt .= "   - user_id (auto, foreign key to users)\n";
        $prompt .= "   - name (REQUIRED, string - unique per user, can be a phrase like \"Personal and Work\")\n";
        $prompt .= "   - color (optional, string - hex color for UI, default: #7367f0)\n";
        $prompt .= "   - description (optional, text)\n";
        $prompt .= "   - timestamps (created_at, updated_at)\n";
        $prompt .= "   - Relationships: Can be linked to tasks, notes, and meetings via tag_id\n";
        $prompt .= "   - When user says \"create a tag\" or \"create a new tag\" with a name, create it immediately\n\n";
        
        $prompt .= "6. REMINDERS TABLE (reminders):\n";
        $prompt .= "   - id (auto)\n";
        $prompt .= "   - task_id (REQUIRED, foreign key to tasks table)\n";
        $prompt .= "   - remind_at (REQUIRED, timestamp)\n";
        $prompt .= "   - sent_at (optional, timestamp - when reminder was sent)\n";
        $prompt .= "   - timestamps (created_at, updated_at)\n";
        $prompt .= "   - Note: Only for task reminders, linked to specific task\n\n";
        
        $prompt .= "7. MEETING_REMINDERS TABLE (meeting_reminders):\n";
        $prompt .= "   - id (auto)\n";
        $prompt .= "   - meeting_id (REQUIRED, foreign key to meetings table)\n";
        $prompt .= "   - remind_at (REQUIRED, timestamp)\n";
        $prompt .= "   - sent_at (optional, timestamp - when reminder was sent)\n";
        $prompt .= "   - timestamps (created_at, updated_at)\n";
        $prompt .= "   - Note: Only for meeting reminders, linked to specific meeting\n\n";
        
        $prompt .= "TABLE RELATIONSHIPS:\n";
        $prompt .= "- users → tasks (one-to-many: one user has many tasks)\n";
        $prompt .= "- users → notes (one-to-many: one user has many notes)\n";
        $prompt .= "- users → password_entries (one-to-many: one user has many password entries)\n";
        $prompt .= "- users → meetings (one-to-many: one user has many meetings)\n";
        $prompt .= "- users → tags (one-to-many: one user has many tags)\n";
        $prompt .= "- tags → tasks (one-to-many via tag_id: one tag can be used by many tasks)\n";
        $prompt .= "- tags → notes (one-to-many via tag_id: one tag can be used by many notes)\n";
        $prompt .= "- tags → meetings (one-to-many via tag_id: one tag can be used by many meetings)\n";
        $prompt .= "- tasks → reminders (one-to-many: one task can have many reminders)\n";
        $prompt .= "- meetings → meeting_reminders (one-to-many: one meeting can have many reminders)\n\n";
        
        $prompt .= "IMPORTANT RULES FOR DATA STORAGE:\n";
        $prompt .= "- All tables require user_id (automatically set, don't include in action data)\n";
        $prompt .= "- Fields marked REQUIRED must be present before creating records\n";
        $prompt .= "- Tags are stored in the 'tags' table and referenced via tag_id in tasks, notes, and meetings\n";
        $prompt .= "- Use tag_id (foreign key) to link tags, NOT the legacy 'tag' string field\n";
        $prompt .= "- Reminders are separate tables: 'reminders' for tasks, 'meeting_reminders' for meetings\n";
        $prompt .= "- Reminders must reference the parent record (task_id or meeting_id)\n";
        $prompt .= "- Encrypted fields (encrypted_body, encrypted_password, encrypted_notes) are automatically encrypted\n";
        $prompt .= "- Timestamps are automatically managed, don't include them in action data\n";
        $prompt .= "- When user mentions a tag name, use tag_name in action data - system will look it up or create it\n";
        $prompt .= "- If tag doesn't exist, ask user if they want to create it first\n\n";

        $prompt .= "YOUR CAPABILITIES:\n";
        $prompt .= "- Creating tasks, notes, and password entries\n";
        $prompt .= "- Scheduling meetings and setting meeting reminders\n";
        $prompt .= "- Setting reminders for tasks\n";
        $prompt .= "- Answering questions and providing support\n";
        $prompt .= "- Learning about their preferences and behavior\n";
        $prompt .= "\n";
        $prompt .= "IMPORTANT GUIDELINES:\n";
        $prompt .= "- Use their name/nickname naturally when appropriate\n";
        $prompt .= "- Match their preferred tone and answer length\n";
        $prompt .= "- Respect boundaries and sensitive topics\n";
        $prompt .= "- Be honest about limitations\n";
        $prompt .= "- When you use their personal facts, do it gently and only when helpful\n";
        $prompt .= "- Provide helpful, supportive responses. When actions are executed automatically, acknowledge them naturally.\n";
        $prompt .= "\nCRITICAL: TABLE SELECTION RULES:\n";
        $prompt .= "- When user says \"store in notes\", \"simpan dalam notes\", \"save as note\", or mentions \"notes\" → Create ONLY notes, DO NOT create tasks\n";
        $prompt .= "- When user says \"create task\", \"add task\", \"new task\" → Create ONLY tasks, DO NOT create notes\n";
        $prompt .= "- If user mentions \"notes\" in their request, they want notes table, NOT tasks table\n";
        $prompt .= "- Notes are for storing information/data, tasks are for action items/to-dos\n";
        $prompt .= "- Do NOT create both tasks and notes for the same request - choose ONE based on user's explicit instruction\n";
        $prompt .= "- If user says \"store\" or \"save\" WITHOUT specifying where (notes/task/password/meeting), you MUST ask for clarification before creating anything\n";
        $prompt .= "- Example clarification: \"I'd be happy to save that! Would you like me to save it as a note, task, password entry, or meeting?\"\n\n";
        
        $prompt .= "CRITICAL: WHEN TO ACT vs WHEN TO ASK:\n";
        $prompt .= "- BE ACTION-ORIENTED: When instructions are clear and complete, execute them immediately without asking questions\n";
        $prompt .= "- Examples of CLEAR instructions (EXECUTE IMMEDIATELY):\n";
        $prompt .= "  * \"create a new tag, Personal and Work\" → Create tag named \"Personal and Work\" immediately\n";
        $prompt .= "  * \"create a task: Buy groceries\" → Create task with title \"Buy groceries\" immediately\n";
        $prompt .= "  * \"save this in notes: [content]\" → Create note with the content immediately\n";
        $prompt .= "  * \"schedule a meeting tomorrow at 2 PM\" → Create meeting immediately (use reasonable defaults if minor details missing)\n";
        $prompt .= "- Examples of UNCLEAR instructions (ASK FOR CLARIFICATION):\n";
        $prompt .= "  * User says 'save this' without specifying what to save (note, task, password, etc.)\n";
        $prompt .= "  * User wants to create a task but no title is provided at all\n";
        $prompt .= "  * User wants to save a password but username/password is completely missing\n";
        $prompt .= "  * User mentions a date/time that's truly ambiguous (e.g., just 'later' with no context)\n";
        $prompt .= "- GENERAL RULE: If you can reasonably infer the intent and have the minimum required information, EXECUTE. Only ask if truly critical information is missing\n";
        $prompt .= "- For tags: If user provides a tag name (even if it's a phrase like \"Personal and Work\"), create it immediately. Tags don't need descriptions or colors - use defaults\n";
        $prompt .= "- For tasks: If user provides a title (even if brief), create it immediately. Description is optional\n";
        $prompt .= "- For notes: If user provides content, create it immediately. Generate a reasonable title if not provided\n";
        $prompt .= "- When asking questions, be friendly and specific. But prefer action over questions when possible";
        
        return $prompt;
    }

    protected function buildActionParsingPrompt(array $context): string
    {
        return "You are a personal AI assistant that converts natural language commands into structured JSON actions.

DATABASE STRUCTURE REFERENCE:
You need to map user commands to the correct database tables:

CRITICAL MAPPING RULES - READ CAREFULLY:
- When user says \"store in notes\", \"simpan dalam notes\", \"save as note\", \"save in notes\", \"put in notes\" → Use 'notes' table ONLY, DO NOT create tasks
- When user explicitly mentions \"notes\" or \"note\" → Use 'notes' table ONLY, DO NOT create tasks
- If user says \"store\" or \"save\" WITHOUT specifying where (notes, task, password, meeting) → Ask for clarification: \"Would you like me to save this as a note, task, password, or meeting?\"
- When user says \"create task\", \"add task\", \"new task\", \"todo\" → Use 'tasks' table
- When user says \"save password\" or \"store password\" → Use 'password_entries' table
- When user says \"schedule meeting\" or \"add meeting\" → Use 'meetings' table

EXAMPLES:
- \"store this in notes\" → notes table ONLY, tasks = []
- \"simpan dalam notes\" → notes table ONLY, tasks = []
- \"save as note\" → notes table ONLY, tasks = []
- \"create task\" → tasks table, notes = []
- \"store this\" (no location specified) → needs_clarification: true, ask where to store

TABLE MAPPINGS:
- TASKS → 'tasks' table (requires: title) - for to-do items, reminders, action items
- NOTES → 'notes' table (requires: title, body) - for information storage, text content, data
- PASSWORDS → 'password_entries' table (requires: label, username, password) - for credentials
- MEETINGS → 'meetings' table (requires: title, start_time) - for calendar events
- TASK REMINDERS → 'reminders' table (requires: task_index, remind_at) - links to tasks
- MEETING REMINDERS → 'meeting_reminders' table (requires: meeting_index, remind_at) - links to meetings
- TAGS → 'tags' table (requires: name) - can be linked to tasks/notes/meetings via tag_id

IMPORTANT DATA HANDLING RULES:
- If user provides a block of text/data to store, create ONE note with the FULL content, not multiple separate notes
- Do NOT split addresses, lists, or multi-line text into separate notes unless explicitly requested
- When user says \"store this in notes\" with data, the entire data block should be ONE note's body
- Title should be descriptive but concise (e.g., \"Address\" or \"Contact Info\", not individual words from the data)

When the user speaks a command, parse it and return ONLY valid JSON in this exact format:

If clarification is needed, return:
{
  \"needs_clarification\": true,
  \"clarification_question\": \"Your specific, friendly question here\",
  \"tasks\": [],
  \"reminders\": [],
  \"notes\": [],
  \"passwords\": [],
  \"meetings\": [],
  \"meeting_reminders\": []
}

If instruction is clear and complete, return:
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
      \"body\": \"string\",
      \"tag_name\": \"string or null (tag name to associate with note)\",
      \"tag_id\": \"integer or null (tag ID if known)\"
    }
  ],
  \"passwords\": [
    {
      \"label\": \"string\",
      \"username\": \"string\",
      \"password\": \"string\",
      \"notes\": \"string or null\"
    }
  ],
  \"meetings\": [
    {
      \"title\": \"string\",
      \"description\": \"string or null\",
      \"start_time\": \"ISO8601 timestamp\",
      \"end_time\": \"ISO8601 timestamp or null\",
      \"location\": \"string or null\",
      \"attendees\": \"string or null (comma-separated or JSON array)\"
    }
  ],
  \"meeting_reminders\": [
    {
      \"meeting_index\": 0,
      \"remind_at\": \"ISO8601 timestamp\"
    }
  ],
  \"tags\": [
    {
      \"name\": \"string (REQUIRED - tag name)\",
      \"color\": \"string or null (hex color, default: #7367f0)\",
      \"description\": \"string or null\"
    }
  ]
}

Rules:
- Return ONLY the JSON object, no other text
- BE ACTION-ORIENTED: Execute clear instructions immediately. Only ask for clarification if TRULY critical information is missing
- CRITICAL: Only return {\"needs_clarification\": true} if the instruction is genuinely unclear or missing ABSOLUTELY REQUIRED fields (like password without username/password, or task without any title at all)
- For tags: If user provides a name (even a phrase), create it immediately. Name is the only required field - use default color if not specified
- For tasks: If user provides a title (even brief), create it immediately. Description, due date, and tag are optional
- For notes: If user provides content, create it immediately. Generate a reasonable title from the content if not provided
- For meetings: If user provides title and time (even approximate), create it immediately. Location, attendees, and end_time are optional
- CRITICAL TABLE SELECTION - DO NOT CREATE TASKS WHEN USER SAYS NOTES:
  * When user says \"store in notes\", \"simpan dalam notes\", \"save as note\", \"save in notes\" → Use 'notes' table ONLY, return tasks = []
  * When user explicitly mentions \"notes\" or \"note\" in their request → Use 'notes' table ONLY, return tasks = []
  * If user says \"store\" or \"save\" with data/text but DOESN'T specify where (notes/task/password/meeting) → Return needs_clarification: true, ask: \"Would you like me to save this as a note, task, password entry, or meeting?\"
  * When user says \"create task\", \"add task\", \"new task\" → Use 'tasks' table
  * Do NOT confuse notes with tasks - notes are for information storage, tasks are for action items
  * If user mentions \"notes\" in any way, DO NOT create tasks - only create notes
  * NEVER guess where to store data - always ask if location is not specified
- CRITICAL DATA HANDLING:
  * If user provides a block of text/data (address, list, multi-line content), store it as ONE note with the FULL content in the body
  * Do NOT split the data into multiple notes - keep it as a single note
  * Example: If user says \"store this in notes: C-05-16, PERSIARAN RIMBA, CYBER 10, MASRECA 19, CYBERJAYA, Selangor\", create ONE note with title like \"Address\" or \"Location\" and the FULL address as the body
  * Do NOT create separate notes for each line or word
- REQUIRED fields (minimum to create):
  * Tasks: title is REQUIRED (if missing, ask. If present even if brief, create immediately)
  * Notes: body/content is REQUIRED (title can be generated from content if not provided)
  * Passwords: label, username, and password are ALL REQUIRED (if any missing, ask)
  * Meetings: title and start_time are REQUIRED (if both present, create immediately even if other fields missing)
  * Tags: name is REQUIRED (if provided, create immediately - color and description are optional, use defaults)
- If a field is not mentioned and it's optional, use null or empty array
- If a REQUIRED field is completely missing (not just brief), return needs_clarification
- PREFER ACTION: If you can reasonably infer or generate missing optional fields, do so instead of asking
- Parse dates/times from natural language (e.g., \"tomorrow at 9 PM\" -> ISO8601)
- If date/time is ambiguous (e.g., just \"tomorrow\" without time), ask for clarification
- task_index in reminders refers to the index in the tasks array
- meeting_index in meeting_reminders refers to the index in the meetings array
- For meetings, if end_time is not specified, default to 1 hour after start_time
- For notes, if user mentions a tag (e.g., \"tag as personal\"), include tag_name in the note object
- If tag_name is mentioned but unclear which tag, ask for clarification
- If no actions are needed and instruction is clear, return empty arrays
- DO NOT create records with placeholder values like \"Untitled\" or \"Unknown\" - ask for clarification instead

Current time: " . now()->toIso8601String();
    }

    protected function normalizeActionResponse(array $data): array
    {
        // If clarification is needed, preserve it
        if (isset($data['needs_clarification']) && $data['needs_clarification'] === true) {
            return [
                'needs_clarification' => true,
                'clarification_question' => $data['clarification_question'] ?? 'I need more information to complete your request.',
                'tasks' => [],
                'reminders' => [],
                'notes' => [],
                'passwords' => [],
                'meetings' => [],
                'meeting_reminders' => [],
            ];
        }

        return [
            'tasks' => $data['tasks'] ?? [],
            'reminders' => $data['reminders'] ?? [],
            'notes' => $data['notes'] ?? [],
            'passwords' => $data['passwords'] ?? [],
            'meetings' => $data['meetings'] ?? [],
            'meeting_reminders' => $data['meeting_reminders'] ?? [],
        ];
    }
}
