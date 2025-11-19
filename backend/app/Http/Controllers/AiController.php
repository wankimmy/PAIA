<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Task;
use App\Services\AiMemoryService;
use App\Services\EncryptionService;
use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AiController extends Controller
{
    protected OllamaService $ollama;
    protected EncryptionService $encryption;
    protected AiMemoryService $memoryService;

    public function __construct(
        OllamaService $ollama,
        EncryptionService $encryption,
        AiMemoryService $memoryService
    ) {
        $this->ollama = $ollama;
        $this->encryption = $encryption;
        $this->memoryService = $memoryService;
    }

    public function chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Build context from user's data
        $tasks = $user->tasks()
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

        // Get user preferences and AI context
        $preferences = $user->preferences;
        $aiContext = $preferences?->ai_context ?? [];
        $userPreferences = $preferences?->preferences ?? [];

        // Get user profile and memories
        $profileContext = $this->memoryService->getUserProfileContext($user);
        $memories = $this->memoryService->getUserMemoryContext($user);

        $context = [
            'tasks' => $tasks,
            'user_preferences' => $userPreferences,
            'ai_context' => $aiContext,
            'profile' => $profileContext,
            'memories' => $memories,
        ];

        // Check if message contains action intent and parse actions first
        $actions = $this->ollama->parseActionsFromMessage($request->message, $context);
        $executedActions = [];

        // Execute actions if any were detected
        if (!empty($actions['tasks']) || !empty($actions['notes']) || !empty($actions['passwords'])) {
            $executedActions = $this->executeActions($user, $actions);
            
            // Add executed actions to context so AI can acknowledge them
            $context['recent_actions'] = [
                'tasks_created' => count($executedActions['tasks'] ?? []),
                'notes_created' => count($executedActions['notes'] ?? []),
                'passwords_created' => count($executedActions['passwords'] ?? []),
            ];
        }

        // Get AI response (with context about executed actions)
        $response = $this->ollama->chat($request->message, $context);

        // Record interaction
        $this->memoryService->recordInteraction($user, 'chat', [
            'message_length' => strlen($request->message),
            'response_length' => strlen($response),
            'actions_executed' => !empty($executedActions),
        ]);

        // Optional: Auto-update memories (if enabled)
        if (env('AI_AUTO_MEMORY_ENABLED', false)) {
            $this->updateMemoriesFromConversation($user, $request->message, $response, $memories);
        }

        return response()->json([
            'response' => $response,
            'actions_executed' => $executedActions,
        ]);
    }

    protected function updateMemoriesFromConversation(User $user, string $userMessage, string $aiResponse, array $existingMemories): void
    {
        try {
            $newMemories = $this->ollama->extractMemories($userMessage, $aiResponse, $existingMemories);
            
            foreach ($newMemories['memories_to_add_or_update'] ?? [] as $memoryData) {
                try {
                    $this->memoryService->updateOrCreateMemory(
                        $user,
                        $memoryData['category'] ?? 'preference',
                        $memoryData['key'] ?? uniqid('memory_'),
                        $memoryData['value'] ?? '',
                        $memoryData['importance'] ?? 3,
                        'ai_inferred'
                    );
                } catch (\Exception $e) {
                    // Skip if contains sensitive data
                    continue;
                }
            }
        } catch (\Exception $e) {
            // Silently fail - memory extraction is optional
        }
    }

    protected function executeActions($user, array $actions): array
    {
        $executed = ['tasks' => [], 'notes' => [], 'passwords' => []];

        // Create tasks
        foreach ($actions['tasks'] ?? [] as $index => $taskData) {
            $task = $user->tasks()->create([
                'title' => $taskData['title'] ?? 'Untitled Task',
                'description' => $taskData['description'] ?? null,
                'status' => 'pending',
                'due_at' => isset($taskData['due_at']) ? $taskData['due_at'] : null,
                'tag' => $taskData['tag'] ?? null,
                'created_via' => 'ai',
            ]);

            $executed['tasks'][] = $task;

            // Create reminders if specified
            foreach ($actions['reminders'] ?? [] as $reminderData) {
                if (isset($reminderData['task_index']) && $reminderData['task_index'] === $index) {
                    Reminder::create([
                        'task_id' => $task->id,
                        'remind_at' => $reminderData['remind_at'],
                    ]);
                }
            }
        }

        // Create notes
        foreach ($actions['notes'] ?? [] as $noteData) {
            $note = $user->notes()->create([
                'title' => $noteData['title'] ?? 'Untitled Note',
                'encrypted_body' => $this->encryption->encrypt($noteData['body'] ?? ''),
            ]);
            $executed['notes'][] = $note;
        }

        // Create password entries
        foreach ($actions['passwords'] ?? [] as $passwordData) {
            $entry = $user->passwordEntries()->create([
                'label' => $passwordData['label'] ?? 'Untitled',
                'username' => $passwordData['username'] ?? '',
                'encrypted_password' => $this->encryption->encrypt($passwordData['password'] ?? ''),
                'encrypted_notes' => isset($passwordData['notes']) ? $this->encryption->encrypt($passwordData['notes']) : null,
            ]);
            $executed['passwords'][] = $entry;
        }

        return $executed;
    }
}

