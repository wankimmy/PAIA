<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Models\Reminder;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
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

        $meetings = $user->meetings()
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '>=', now())
            ->orderBy('start_time', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($meeting) {
                return [
                    'title' => $meeting->title,
                    'start_time' => $meeting->start_time?->toIso8601String(),
                    'end_time' => $meeting->end_time?->toIso8601String(),
                    'location' => $meeting->location,
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
        $chatHistory = $this->memoryService->getChatHistory($user, 5); // Get last 5 conversations

        $context = [
            'tasks' => $tasks,
            'meetings' => $meetings,
            'user_preferences' => $userPreferences,
            'ai_context' => $aiContext,
            'profile' => $profileContext,
            'memories' => $memories,
            'chat_history' => $chatHistory,
        ];

        // Track sub-processes for UI feedback
        $processes = [];
        
        // Check if message contains action intent and parse actions first
        $processes[] = ['step' => 'parsing', 'message' => 'Analyzing your request...'];
        $actions = $this->ollama->parseActionsFromMessage($request->message, $context);
        
        // Normalize actions to ensure all action types are arrays
        $actions = [
            'tasks' => is_array($actions['tasks'] ?? null) ? $actions['tasks'] : [],
            'reminders' => is_array($actions['reminders'] ?? null) ? $actions['reminders'] : [],
            'notes' => is_array($actions['notes'] ?? null) ? $actions['notes'] : [],
            'passwords' => is_array($actions['passwords'] ?? null) ? $actions['passwords'] : [],
            'meetings' => is_array($actions['meetings'] ?? null) ? $actions['meetings'] : [],
            'meeting_reminders' => is_array($actions['meeting_reminders'] ?? null) ? $actions['meeting_reminders'] : [],
            'needs_clarification' => $actions['needs_clarification'] ?? false,
            'clarification_question' => $actions['clarification_question'] ?? null,
        ];
        
        $executedActions = [];
        $needsClarification = false;
        $clarificationQuestion = null;

        // Validation: If user explicitly mentioned "notes" or "note", remove any tasks
        $messageLower = strtolower($request->message);
        $notesKeywords = ['notes', 'note', 'simpan dalam notes', 'store in notes', 'save as note', 'save in notes', 'put in notes'];
        $hasNotesIntent = false;
        foreach ($notesKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                $hasNotesIntent = true;
                break;
            }
        }

        if ($hasNotesIntent && !empty($actions['tasks'])) {
            // User explicitly asked for notes, but AI created tasks - remove them
            $actions['tasks'] = [];
            $actions['reminders'] = []; // Also remove reminders since they're for tasks
        }

        // Check if AI needs clarification
        if (isset($actions['needs_clarification']) && $actions['needs_clarification'] === true) {
            $needsClarification = true;
            $clarificationQuestion = $actions['clarification_question'] ?? 'I need more information to complete your request. Could you please provide more details?';
            $processes[] = ['step' => 'clarification', 'message' => 'Need more information...'];
            
            // Don't execute any actions if clarification is needed
            $actions = [
                'tasks' => [],
                'reminders' => [],
                'notes' => [],
                'passwords' => [],
                'meetings' => [],
                'meeting_reminders' => [],
            ];
        } else {
            // Execute actions if any were detected
            if (!empty($actions['tasks']) || !empty($actions['notes']) || !empty($actions['passwords']) || !empty($actions['meetings'])) {
                $actionCount = 0;
                if (!empty($actions['tasks'])) {
                    $actionCount += count($actions['tasks']);
                    $processes[] = ['step' => 'creating', 'message' => 'Creating ' . count($actions['tasks']) . ' task(s)...'];
                }
                if (!empty($actions['notes'])) {
                    $actionCount += count($actions['notes']);
                    $processes[] = ['step' => 'creating', 'message' => 'Creating ' . count($actions['notes']) . ' note(s)...'];
                }
                if (!empty($actions['passwords'])) {
                    $actionCount += count($actions['passwords']);
                    $processes[] = ['step' => 'creating', 'message' => 'Creating ' . count($actions['passwords']) . ' password entry/entries...'];
                }
                if (!empty($actions['meetings'])) {
                    $actionCount += count($actions['meetings']);
                    $processes[] = ['step' => 'creating', 'message' => 'Creating ' . count($actions['meetings']) . ' meeting(s)...'];
                }
                
                $executedActions = $this->executeActions($user, $actions);
                $processes[] = ['step' => 'saving', 'message' => 'Saving to database...'];
                
                // Add executed actions to context so AI can acknowledge them
                $context['recent_actions'] = [
                    'tasks_created' => count($executedActions['tasks'] ?? []),
                    'notes_created' => count($executedActions['notes'] ?? []),
                    'passwords_created' => count($executedActions['passwords'] ?? []),
                    'meetings_created' => count($executedActions['meetings'] ?? []),
                ];
            }
        }

        // Get AI response
        $processes[] = ['step' => 'responding', 'message' => 'Generating response...'];

        // Add clarification context if needed
        if ($needsClarification) {
            $context['needs_clarification'] = true;
            $context['clarification_question'] = $clarificationQuestion;
        }

        // Get AI response (with context about executed actions)
        $response = $this->ollama->chat($request->message, $context);

        // Record interaction with full chat history
        $this->memoryService->recordInteraction($user, 'chat', [
            'user_message' => $request->message,
            'ai_response' => $response,
            'message_length' => strlen($request->message),
            'response_length' => strlen($response),
            'actions_executed' => !empty($executedActions),
            'actions' => $executedActions,
        ]);

        // Optional: Auto-update memories (if enabled)
        if (env('AI_AUTO_MEMORY_ENABLED', false)) {
            $this->updateMemoriesFromConversation($user, $request->message, $response, $memories);
        }

        return response()->json([
            'response' => $response,
            'actions_executed' => $executedActions,
            'processes' => $processes,
        ]);
    }

    public function getChatHistory(Request $request)
    {
        $user = $request->user();
        // Get all chat history, no limit
        $limit = $request->get('limit', PHP_INT_MAX); // Default to all, allow override if needed
        
        $chatHistory = $this->memoryService->getChatHistory($user, $limit);
        
        return response()->json($chatHistory);
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
        $executed = ['tasks' => [], 'notes' => [], 'passwords' => [], 'meetings' => [], 'tags' => []];

        // Normalize actions to ensure all are arrays
        $actions = [
            'tasks' => is_array($actions['tasks'] ?? null) ? $actions['tasks'] : [],
            'reminders' => is_array($actions['reminders'] ?? null) ? $actions['reminders'] : [],
            'notes' => is_array($actions['notes'] ?? null) ? $actions['notes'] : [],
            'passwords' => is_array($actions['passwords'] ?? null) ? $actions['passwords'] : [],
            'meetings' => is_array($actions['meetings'] ?? null) ? $actions['meetings'] : [],
            'meeting_reminders' => is_array($actions['meeting_reminders'] ?? null) ? $actions['meeting_reminders'] : [],
            'tags' => is_array($actions['tags'] ?? null) ? $actions['tags'] : [],
        ];

        // Create tasks
        foreach ($actions['tasks'] as $index => $taskData) {
            // Skip if required field is missing (should not happen if AI follows instructions, but safety check)
            if (empty($taskData['title']) || trim($taskData['title']) === '') {
                continue;
            }

            $task = $user->tasks()->create([
                'title' => trim($taskData['title']),
                'description' => $taskData['description'] ?? null,
                'status' => 'pending',
                'due_at' => isset($taskData['due_at']) ? $taskData['due_at'] : null,
                'tag' => $taskData['tag'] ?? null,
                'created_via' => 'ai',
            ]);

            $executed['tasks'][] = $task;

            // Create reminders if specified
            foreach ($actions['reminders'] as $reminderData) {
                if (isset($reminderData['task_index']) && $reminderData['task_index'] === $index && !empty($reminderData['remind_at'])) {
                    Reminder::create([
                        'task_id' => $task->id,
                        'remind_at' => $reminderData['remind_at'],
                    ]);
                }
            }
        }

        // Create meetings
        foreach ($actions['meetings'] as $index => $meetingData) {
            // Skip if required fields are missing
            if (empty($meetingData['title']) || trim($meetingData['title']) === '' || empty($meetingData['start_time'])) {
                continue;
            }

            $startTime = $meetingData['start_time'];
            $endTime = isset($meetingData['end_time']) 
                ? $meetingData['end_time'] 
                : (new \Carbon\Carbon($startTime))->addHour();

            $meeting = $user->meetings()->create([
                'title' => trim($meetingData['title']),
                'description' => $meetingData['description'] ?? null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'location' => $meetingData['location'] ?? null,
                'attendees' => $meetingData['attendees'] ?? null,
                'status' => 'scheduled',
                'created_via' => 'ai',
            ]);

            $executed['meetings'][] = $meeting;

            // Create meeting reminders if specified
            foreach ($actions['meeting_reminders'] as $reminderData) {
                if (isset($reminderData['meeting_index']) && $reminderData['meeting_index'] === $index && !empty($reminderData['remind_at'])) {
                    MeetingReminder::create([
                        'meeting_id' => $meeting->id,
                        'remind_at' => $reminderData['remind_at'],
                    ]);
                }
            }
        }

        // Create notes
        foreach ($actions['notes'] as $noteData) {
            // Skip if required fields are missing
            if (empty($noteData['title']) || trim($noteData['title']) === '' || empty($noteData['body']) || trim($noteData['body']) === '') {
                continue;
            }

            // Handle tag_id - can be provided directly or as tag name to lookup
            $tagId = null;
            if (isset($noteData['tag_id'])) {
                $tagId = $noteData['tag_id'];
            } elseif (isset($noteData['tag_name'])) {
                // Try to find tag by name
                $tag = $user->tags()->where('name', $noteData['tag_name'])->first();
                if ($tag) {
                    $tagId = $tag->id;
                }
            }

            $note = $user->notes()->create([
                'title' => trim($noteData['title']),
                'encrypted_body' => $this->encryption->encrypt(trim($noteData['body'])),
                'tag_id' => $tagId,
            ]);
            $executed['notes'][] = $note;
        }

        // Create password entries
        foreach ($actions['passwords'] as $passwordData) {
            // Skip if required fields are missing
            if (empty($passwordData['label']) || trim($passwordData['label']) === '' 
                || empty($passwordData['username']) || trim($passwordData['username']) === ''
                || empty($passwordData['password']) || trim($passwordData['password']) === '') {
                continue;
            }

            $entry = $user->passwordEntries()->create([
                'label' => trim($passwordData['label']),
                'username' => trim($passwordData['username']),
                'encrypted_password' => $this->encryption->encrypt(trim($passwordData['password'])),
                'encrypted_notes' => isset($passwordData['notes']) ? $this->encryption->encrypt($passwordData['notes']) : null,
            ]);
            $executed['passwords'][] = $entry;
        }

        // Create tags
        foreach ($actions['tags'] as $tagData) {
            // Skip if required field is missing
            if (empty($tagData['name']) || trim($tagData['name']) === '') {
                continue;
            }

            // Check if tag already exists
            $existingTag = $user->tags()->where('name', trim($tagData['name']))->first();
            if ($existingTag) {
                $executed['tags'][] = $existingTag;
                continue;
            }

            $tag = $user->tags()->create([
                'name' => trim($tagData['name']),
                'color' => $tagData['color'] ?? '#7367f0',
                'description' => $tagData['description'] ?? null,
            ]);
            $executed['tags'][] = $tag;
        }

        return $executed;
    }
}

