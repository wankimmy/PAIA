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
            'message' => 'required|string|max:10000', // Limit message length to prevent DoS
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Sanitize input
        $message = trim(strip_tags($request->message));
        if (empty($message)) {
            return response()->json(['errors' => ['message' => ['Message cannot be empty.']]], 422);
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

        // Get existing tags for context
        $tags = $user->tags()
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ];
            })
            ->toArray();

        // Get notes for context
        $notes = $user->notes()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($note) {
                $createdAt = $note->created_at ? \Carbon\Carbon::parse($note->created_at) : null;
                return [
                    'id' => $note->id,
                    'title' => $note->title,
                    'created_at' => $note->created_at?->toIso8601String(),
                    'created_at_formatted' => $createdAt ? $createdAt->format('M d, Y') : null,
                    'tag_id' => $note->tag_id,
                ];
            })
            ->toArray();

        // Get all tasks (including done) for better context
        $allTasks = $user->tasks()
            ->orderBy('due_at', 'asc')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'due_at' => $task->due_at?->toIso8601String(),
                    'description' => $task->description,
                    'tag_id' => $task->tag_id,
                ];
            })
            ->toArray();

        // Separate tasks by status and date
        $now = now();
        $pendingTasks = array_filter($allTasks, fn($t) => $t['status'] === 'pending');
        $todayTasks = array_filter($pendingTasks, function($t) use ($now) {
            if (!$t['due_at']) return false;
            $dueDate = \Carbon\Carbon::parse($t['due_at']);
            return $dueDate->isToday();
        });
        $overdueTasks = array_filter($pendingTasks, function($t) use ($now) {
            if (!$t['due_at']) return false;
            $dueDate = \Carbon\Carbon::parse($t['due_at']);
            return $dueDate->isPast() && !$dueDate->isToday();
        });
        $upcomingTasks = array_filter($pendingTasks, function($t) use ($now) {
            if (!$t['due_at']) return false;
            $dueDate = \Carbon\Carbon::parse($t['due_at']);
            return $dueDate->isFuture();
        });

        // Get all meetings with formatted dates
        $allMeetings = $user->meetings()
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_time', 'asc')
            ->limit(50)
            ->get()
            ->map(function ($meeting) {
                $startTime = $meeting->start_time ? \Carbon\Carbon::parse($meeting->start_time) : null;
                $endTime = $meeting->end_time ? \Carbon\Carbon::parse($meeting->end_time) : null;
                
                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'start_time' => $meeting->start_time?->toIso8601String(),
                    'end_time' => $meeting->end_time?->toIso8601String(),
                    'start_time_formatted' => $startTime ? $startTime->format('M d, Y g:i A') : null,
                    'start_time_time' => $startTime ? $startTime->format('g:i A') : null,
                    'end_time_formatted' => $endTime ? $endTime->format('g:i A') : null,
                    'is_today' => $startTime ? $startTime->isToday() : false,
                    'is_future' => $startTime ? ($startTime->isFuture() && !$startTime->isToday()) : false,
                    'location' => $meeting->location,
                    'description' => $meeting->description,
                ];
            })
            ->toArray();

        $todayMeetings = array_filter($allMeetings, fn($m) => $m['is_today'] ?? false);
        $futureMeetings = array_filter($allMeetings, fn($m) => $m['is_future'] ?? false);
        
        // Format task dates for better display
        $allTasks = array_map(function($task) {
            if ($task['due_at']) {
                $dueDate = \Carbon\Carbon::parse($task['due_at']);
                $task['due_at_formatted'] = $dueDate->format('M d, Y g:i A');
                $task['due_at_date'] = $dueDate->format('M d, Y');
                $task['due_at_time'] = $dueDate->format('g:i A');
                $task['is_today'] = $dueDate->isToday();
                $task['is_overdue'] = $dueDate->isPast() && !$dueDate->isToday();
                $task['is_future'] = $dueDate->isFuture();
            }
            return $task;
        }, $allTasks);

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
            'all_tasks' => $allTasks,
            'pending_tasks' => array_values($pendingTasks),
            'today_tasks' => array_values($todayTasks),
            'overdue_tasks' => array_values($overdueTasks),
            'upcoming_tasks' => array_values($upcomingTasks),
            'meetings' => $meetings,
            'all_meetings' => $allMeetings,
            'today_meetings' => array_values($todayMeetings),
            'future_meetings' => array_values($futureMeetings),
            'tags' => $tags,
            'notes' => $notes,
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
            'tags' => is_array($actions['tags'] ?? null) ? $actions['tags'] : [],
            'tag_updates' => is_array($actions['tag_updates'] ?? null) ? $actions['tag_updates'] : [],
            'tag_deletes' => is_array($actions['tag_deletes'] ?? null) ? $actions['tag_deletes'] : [],
            'task_updates' => is_array($actions['task_updates'] ?? null) ? $actions['task_updates'] : [],
            'task_deletes' => is_array($actions['task_deletes'] ?? null) ? $actions['task_deletes'] : [],
            'note_updates' => is_array($actions['note_updates'] ?? null) ? $actions['note_updates'] : [],
            'note_deletes' => is_array($actions['note_deletes'] ?? null) ? $actions['note_deletes'] : [],
            'password_updates' => is_array($actions['password_updates'] ?? null) ? $actions['password_updates'] : [],
            'password_deletes' => is_array($actions['password_deletes'] ?? null) ? $actions['password_deletes'] : [],
            'meeting_updates' => is_array($actions['meeting_updates'] ?? null) ? $actions['meeting_updates'] : [],
            'meeting_deletes' => is_array($actions['meeting_deletes'] ?? null) ? $actions['meeting_deletes'] : [],
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
                'tags' => [],
                'tag_updates' => [],
                'tag_deletes' => [],
                'task_updates' => [],
                'task_deletes' => [],
                'note_updates' => [],
                'note_deletes' => [],
                'password_updates' => [],
                'password_deletes' => [],
                'meeting_updates' => [],
                'meeting_deletes' => [],
            ];
        } else {
            // Execute actions if any were detected
            if (!empty($actions['tasks']) || !empty($actions['notes']) || !empty($actions['passwords']) || !empty($actions['meetings']) || !empty($actions['tags']) || !empty($actions['tag_updates']) || !empty($actions['tag_deletes']) || !empty($actions['task_updates']) || !empty($actions['task_deletes']) || !empty($actions['note_updates']) || !empty($actions['note_deletes']) || !empty($actions['password_updates']) || !empty($actions['password_deletes']) || !empty($actions['meeting_updates']) || !empty($actions['meeting_deletes'])) {
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
                if (!empty($actions['tags'])) {
                    $actionCount += count($actions['tags']);
                    $processes[] = ['step' => 'creating', 'message' => 'Creating ' . count($actions['tags']) . ' tag(s)...'];
                }
                if (!empty($actions['tag_updates'])) {
                    $actionCount += count($actions['tag_updates']);
                    $processes[] = ['step' => 'updating', 'message' => 'Updating ' . count($actions['tag_updates']) . ' tag(s)...'];
                }
                if (!empty($actions['tag_deletes'])) {
                    $actionCount += count($actions['tag_deletes']);
                    $processes[] = ['step' => 'deleting', 'message' => 'Deleting ' . count($actions['tag_deletes']) . ' tag(s)...'];
                }
                if (!empty($actions['task_updates'])) {
                    $actionCount += count($actions['task_updates']);
                    $processes[] = ['step' => 'updating', 'message' => 'Updating ' . count($actions['task_updates']) . ' task(s)...'];
                }
                if (!empty($actions['task_deletes'])) {
                    $actionCount += count($actions['task_deletes']);
                    $processes[] = ['step' => 'deleting', 'message' => 'Deleting ' . count($actions['task_deletes']) . ' task(s)...'];
                }
                if (!empty($actions['note_updates'])) {
                    $actionCount += count($actions['note_updates']);
                    $processes[] = ['step' => 'updating', 'message' => 'Updating ' . count($actions['note_updates']) . ' note(s)...'];
                }
                if (!empty($actions['note_deletes'])) {
                    $actionCount += count($actions['note_deletes']);
                    $processes[] = ['step' => 'deleting', 'message' => 'Deleting ' . count($actions['note_deletes']) . ' note(s)...'];
                }
                if (!empty($actions['password_updates'])) {
                    $actionCount += count($actions['password_updates']);
                    $processes[] = ['step' => 'updating', 'message' => 'Updating ' . count($actions['password_updates']) . ' password entry/entries...'];
                }
                if (!empty($actions['password_deletes'])) {
                    $actionCount += count($actions['password_deletes']);
                    $processes[] = ['step' => 'deleting', 'message' => 'Deleting ' . count($actions['password_deletes']) . ' password entry/entries...'];
                }
                if (!empty($actions['meeting_updates'])) {
                    $actionCount += count($actions['meeting_updates']);
                    $processes[] = ['step' => 'updating', 'message' => 'Updating ' . count($actions['meeting_updates']) . ' meeting(s)...'];
                }
                if (!empty($actions['meeting_deletes'])) {
                    $actionCount += count($actions['meeting_deletes']);
                    $processes[] = ['step' => 'deleting', 'message' => 'Deleting ' . count($actions['meeting_deletes']) . ' meeting(s)...'];
                }
                
                $executedActions = $this->executeActions($user, $actions);
                $processes[] = ['step' => 'saving', 'message' => 'Saving to database...'];
                
                // Add executed actions to context so AI can acknowledge them
                $context['recent_actions'] = [
                    'tasks_created' => count($executedActions['tasks'] ?? []),
                    'tasks_updated' => count($executedActions['tasks_updated'] ?? []),
                    'tasks_deleted' => count($executedActions['tasks_deleted'] ?? []),
                    'notes_created' => count($executedActions['notes'] ?? []),
                    'notes_updated' => count($executedActions['notes_updated'] ?? []),
                    'notes_deleted' => count($executedActions['notes_deleted'] ?? []),
                    'passwords_created' => count($executedActions['passwords'] ?? []),
                    'passwords_updated' => count($executedActions['passwords_updated'] ?? []),
                    'passwords_deleted' => count($executedActions['passwords_deleted'] ?? []),
                    'meetings_created' => count($executedActions['meetings'] ?? []),
                    'meetings_updated' => count($executedActions['meetings_updated'] ?? []),
                    'meetings_deleted' => count($executedActions['meetings_deleted'] ?? []),
                    'tags_created' => count($executedActions['tags'] ?? []),
                    'tags_updated' => count($executedActions['tags_updated'] ?? []),
                    'tags_deleted' => count($executedActions['tags_deleted'] ?? []),
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
        $response = $this->ollama->chat($message, $context);

        // Record interaction with full chat history
        $this->memoryService->recordInteraction($user, 'chat', [
            'user_message' => $message,
            'ai_response' => $response,
            'message_length' => strlen($message),
            'response_length' => strlen($response),
            'actions_executed' => !empty($executedActions),
            'actions' => $executedActions,
        ]);

        // Optional: Auto-update memories (if enabled)
        if (env('AI_AUTO_MEMORY_ENABLED', false)) {
            $this->updateMemoriesFromConversation($user, $message, $response, $memories);
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
        $executed = [
            'tasks' => [], 'tasks_updated' => [], 'tasks_deleted' => [],
            'notes' => [], 'notes_updated' => [], 'notes_deleted' => [],
            'passwords' => [], 'passwords_updated' => [], 'passwords_deleted' => [],
            'meetings' => [], 'meetings_updated' => [], 'meetings_deleted' => [],
            'tags' => [], 'tags_updated' => [], 'tags_deleted' => []
        ];

        // Normalize actions to ensure all are arrays
        $actions = [
            'tasks' => is_array($actions['tasks'] ?? null) ? $actions['tasks'] : [],
            'reminders' => is_array($actions['reminders'] ?? null) ? $actions['reminders'] : [],
            'notes' => is_array($actions['notes'] ?? null) ? $actions['notes'] : [],
            'passwords' => is_array($actions['passwords'] ?? null) ? $actions['passwords'] : [],
            'meetings' => is_array($actions['meetings'] ?? null) ? $actions['meetings'] : [],
            'meeting_reminders' => is_array($actions['meeting_reminders'] ?? null) ? $actions['meeting_reminders'] : [],
            'tags' => is_array($actions['tags'] ?? null) ? $actions['tags'] : [],
            'tag_updates' => is_array($actions['tag_updates'] ?? null) ? $actions['tag_updates'] : [],
            'tag_deletes' => is_array($actions['tag_deletes'] ?? null) ? $actions['tag_deletes'] : [],
            'task_updates' => is_array($actions['task_updates'] ?? null) ? $actions['task_updates'] : [],
            'task_deletes' => is_array($actions['task_deletes'] ?? null) ? $actions['task_deletes'] : [],
            'note_updates' => is_array($actions['note_updates'] ?? null) ? $actions['note_updates'] : [],
            'note_deletes' => is_array($actions['note_deletes'] ?? null) ? $actions['note_deletes'] : [],
            'password_updates' => is_array($actions['password_updates'] ?? null) ? $actions['password_updates'] : [],
            'password_deletes' => is_array($actions['password_deletes'] ?? null) ? $actions['password_deletes'] : [],
            'meeting_updates' => is_array($actions['meeting_updates'] ?? null) ? $actions['meeting_updates'] : [],
            'meeting_deletes' => is_array($actions['meeting_deletes'] ?? null) ? $actions['meeting_deletes'] : [],
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
            if (empty($meetingData['title']) || trim($meetingData['title']) === '') {
                continue;
            }

            // Parse start_time - handle both ISO8601 strings and natural language
            $startTime = null;
            if (!empty($meetingData['start_time'])) {
                try {
                    $startTime = \Carbon\Carbon::parse($meetingData['start_time'])->toIso8601String();
                } catch (\Exception $e) {
                    // If parsing fails, try to extract time from the string
                    // This is a fallback for when AI doesn't format dates correctly
                    \Log::warning('Failed to parse meeting start_time: ' . $meetingData['start_time'], ['error' => $e->getMessage()]);
                    continue; // Skip this meeting if we can't parse the time
                }
            } else {
                // If no start_time provided, skip this meeting
                continue;
            }

            // Parse end_time
            $endTime = null;
            if (!empty($meetingData['end_time'])) {
                try {
                    $endTime = \Carbon\Carbon::parse($meetingData['end_time'])->toIso8601String();
                } catch (\Exception $e) {
                    // If parsing fails, default to 1 hour after start_time
                    $endTime = (new \Carbon\Carbon($startTime))->addHour()->toIso8601String();
                }
            } else {
                // Default to 1 hour after start_time if not provided
                $endTime = (new \Carbon\Carbon($startTime))->addHour()->toIso8601String();
            }

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

        // Update tags
        foreach ($actions['tag_updates'] as $updateData) {
            if (empty($updateData['name_or_id'])) {
                continue;
            }

            // Find tag by ID or name
            $tag = null;
            if (is_numeric($updateData['name_or_id'])) {
                $tag = $user->tags()->find($updateData['name_or_id']);
            } else {
                $tag = $user->tags()->where('name', trim($updateData['name_or_id']))->first();
            }

            if (!$tag) {
                continue;
            }

            // Update only provided fields
            $updateFields = [];
            if (isset($updateData['name']) && !empty(trim($updateData['name']))) {
                $updateFields['name'] = trim($updateData['name']);
            }
            if (isset($updateData['color']) && !empty(trim($updateData['color']))) {
                $updateFields['color'] = trim($updateData['color']);
            }
            if (isset($updateData['description'])) {
                $updateFields['description'] = $updateData['description'] ? trim($updateData['description']) : null;
            }

            if (!empty($updateFields)) {
                $tag->update($updateFields);
                $executed['tags_updated'][] = $tag;
            }
        }

        // Delete tags
        foreach ($actions['tag_deletes'] as $deleteIdentifier) {
            if (empty($deleteIdentifier)) {
                continue;
            }

            // Find tag by ID or name
            $tag = null;
            if (is_numeric($deleteIdentifier)) {
                $tag = $user->tags()->find($deleteIdentifier);
            } else {
                $tag = $user->tags()->where('name', trim($deleteIdentifier))->first();
            }

            if ($tag) {
                $executed['tags_deleted'][] = $tag;
                $tag->delete();
            }
        }

        // Update tasks
        foreach ($actions['task_updates'] as $updateData) {
            if (empty($updateData['id_or_title'])) {
                continue;
            }

            // Find task by ID or title
            $task = null;
            if (is_numeric($updateData['id_or_title'])) {
                $task = $user->tasks()->find($updateData['id_or_title']);
            } else {
                $task = $user->tasks()->where('title', trim($updateData['id_or_title']))->first();
            }

            if (!$task) {
                continue;
            }

            // Update only provided fields
            $updateFields = [];
            if (isset($updateData['title']) && !empty(trim($updateData['title']))) {
                $updateFields['title'] = trim($updateData['title']);
            }
            if (isset($updateData['description'])) {
                $updateFields['description'] = $updateData['description'] ? trim($updateData['description']) : null;
            }
            if (isset($updateData['status']) && in_array($updateData['status'], ['pending', 'done', 'cancelled'])) {
                $updateFields['status'] = $updateData['status'];
            }
            if (isset($updateData['due_at'])) {
                $updateFields['due_at'] = $updateData['due_at'] ?: null;
            }
            
            // Handle tag_id - can be provided directly or as tag name to lookup
            $tagId = null;
            if (isset($updateData['tag_id'])) {
                $tagId = $updateData['tag_id'];
            } elseif (isset($updateData['tag_name'])) {
                // Try to find tag by name
                $tag = $user->tags()->where('name', $updateData['tag_name'])->first();
                if ($tag) {
                    $tagId = $tag->id;
                }
            }
            if ($tagId !== null) {
                $updateFields['tag_id'] = $tagId;
            }

            if (!empty($updateFields)) {
                $task->update($updateFields);
                $executed['tasks_updated'][] = $task;
            }
        }

        // Delete tasks
        foreach ($actions['task_deletes'] as $deleteIdentifier) {
            if (empty($deleteIdentifier)) {
                continue;
            }

            // Find task by ID or title
            $task = null;
            if (is_numeric($deleteIdentifier)) {
                $task = $user->tasks()->find($deleteIdentifier);
            } else {
                $task = $user->tasks()->where('title', trim($deleteIdentifier))->first();
            }

            if ($task) {
                $executed['tasks_deleted'][] = $task;
                $task->delete();
            }
        }

        // Update notes
        foreach ($actions['note_updates'] as $updateData) {
            if (empty($updateData['id_or_title'])) {
                continue;
            }

            // Find note by ID or title
            $note = null;
            if (is_numeric($updateData['id_or_title'])) {
                $note = $user->notes()->find($updateData['id_or_title']);
            } else {
                $note = $user->notes()->where('title', trim($updateData['id_or_title']))->first();
            }

            if (!$note) {
                continue;
            }

            // Update only provided fields
            $updateFields = [];
            if (isset($updateData['title']) && !empty(trim($updateData['title']))) {
                $updateFields['title'] = trim($updateData['title']);
            }
            if (isset($updateData['body']) && !empty(trim($updateData['body']))) {
                $updateFields['encrypted_body'] = $this->encryption->encrypt(trim($updateData['body']));
            }
            
            // Handle tag_id - can be provided directly or as tag name to lookup
            $tagId = null;
            if (isset($updateData['tag_id'])) {
                $tagId = $updateData['tag_id'];
            } elseif (isset($updateData['tag_name'])) {
                // Try to find tag by name
                $tag = $user->tags()->where('name', $updateData['tag_name'])->first();
                if ($tag) {
                    $tagId = $tag->id;
                }
            }
            if ($tagId !== null) {
                $updateFields['tag_id'] = $tagId;
            }

            if (!empty($updateFields)) {
                $note->update($updateFields);
                $executed['notes_updated'][] = $note;
            }
        }

        // Delete notes
        foreach ($actions['note_deletes'] as $deleteIdentifier) {
            if (empty($deleteIdentifier)) {
                continue;
            }

            // Find note by ID or title
            $note = null;
            if (is_numeric($deleteIdentifier)) {
                $note = $user->notes()->find($deleteIdentifier);
            } else {
                $note = $user->notes()->where('title', trim($deleteIdentifier))->first();
            }

            if ($note) {
                $executed['notes_deleted'][] = $note;
                $note->delete();
            }
        }

        // Update password entries
        foreach ($actions['password_updates'] as $updateData) {
            if (empty($updateData['id_or_label'])) {
                continue;
            }

            // Find password entry by ID or label
            $password = null;
            if (is_numeric($updateData['id_or_label'])) {
                $password = $user->passwordEntries()->find($updateData['id_or_label']);
            } else {
                $password = $user->passwordEntries()->where('label', trim($updateData['id_or_label']))->first();
            }

            if (!$password) {
                continue;
            }

            // Update only provided fields
            $updateFields = [];
            if (isset($updateData['label']) && !empty(trim($updateData['label']))) {
                $updateFields['label'] = trim($updateData['label']);
            }
            if (isset($updateData['username']) && !empty(trim($updateData['username']))) {
                $updateFields['username'] = trim($updateData['username']);
            }
            if (isset($updateData['password']) && !empty(trim($updateData['password']))) {
                $updateFields['encrypted_password'] = $this->encryption->encrypt(trim($updateData['password']));
            }
            if (isset($updateData['notes'])) {
                $updateFields['encrypted_notes'] = $updateData['notes'] ? $this->encryption->encrypt(trim($updateData['notes'])) : null;
            }

            if (!empty($updateFields)) {
                $password->update($updateFields);
                $executed['passwords_updated'][] = $password;
            }
        }

        // Delete password entries
        foreach ($actions['password_deletes'] as $deleteIdentifier) {
            if (empty($deleteIdentifier)) {
                continue;
            }

            // Find password entry by ID or label
            $password = null;
            if (is_numeric($deleteIdentifier)) {
                $password = $user->passwordEntries()->find($deleteIdentifier);
            } else {
                $password = $user->passwordEntries()->where('label', trim($deleteIdentifier))->first();
            }

            if ($password) {
                $executed['passwords_deleted'][] = $password;
                $password->delete();
            }
        }

        // Update meetings
        foreach ($actions['meeting_updates'] as $updateData) {
            if (empty($updateData['id_or_title'])) {
                continue;
            }

            // Find meeting by ID or title
            $meeting = null;
            if (is_numeric($updateData['id_or_title'])) {
                $meeting = $user->meetings()->find($updateData['id_or_title']);
            } else {
                $meeting = $user->meetings()->where('title', trim($updateData['id_or_title']))->first();
            }

            if (!$meeting) {
                continue;
            }

            // Update only provided fields
            $updateFields = [];
            if (isset($updateData['title']) && !empty(trim($updateData['title']))) {
                $updateFields['title'] = trim($updateData['title']);
            }
            if (isset($updateData['description'])) {
                $updateFields['description'] = $updateData['description'] ? trim($updateData['description']) : null;
            }
            if (isset($updateData['start_time'])) {
                $updateFields['start_time'] = $updateData['start_time'] ?: null;
            }
            if (isset($updateData['end_time'])) {
                $updateFields['end_time'] = $updateData['end_time'] ?: null;
            }
            if (isset($updateData['location'])) {
                $updateFields['location'] = $updateData['location'] ? trim($updateData['location']) : null;
            }
            if (isset($updateData['attendees'])) {
                $updateFields['attendees'] = $updateData['attendees'] ? trim($updateData['attendees']) : null;
            }
            if (isset($updateData['status']) && in_array($updateData['status'], ['scheduled', 'cancelled', 'completed'])) {
                $updateFields['status'] = $updateData['status'];
            }

            if (!empty($updateFields)) {
                $meeting->update($updateFields);
                $executed['meetings_updated'][] = $meeting;
            }
        }

        // Delete meetings
        foreach ($actions['meeting_deletes'] as $deleteIdentifier) {
            if (empty($deleteIdentifier)) {
                continue;
            }

            // Find meeting by ID or title
            $meeting = null;
            if (is_numeric($deleteIdentifier)) {
                $meeting = $user->meetings()->find($deleteIdentifier);
            } else {
                $meeting = $user->meetings()->where('title', trim($deleteIdentifier))->first();
            }

            if ($meeting) {
                $executed['meetings_deleted'][] = $meeting;
                $meeting->delete();
            }
        }

        return $executed;
    }
}

