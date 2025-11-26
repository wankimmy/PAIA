<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Models\Reminder;
use App\Models\Task;
use App\Services\AiMemoryService;
use App\Services\EncryptionService;
use App\Services\OllamaService;
use App\Services\SttService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VoiceController extends Controller
{
    protected SttService $stt;
    protected OllamaService $ollama;
    protected EncryptionService $encryption;
    protected AiMemoryService $memoryService;

    public function __construct(
        SttService $stt,
        OllamaService $ollama,
        EncryptionService $encryption,
        AiMemoryService $memoryService
    ) {
        $this->stt = $stt;
        $this->ollama = $ollama;
        $this->encryption = $encryption;
        $this->memoryService = $memoryService;
    }

    public function command(Request $request)
    {
        $maxSize = config('security.file_upload.max_size', 10485760); // 10MB default
        
        $validator = Validator::make($request->all(), [
            'audio' => [
                'required',
                'file',
                'mimes:mp3,wav,ogg,m4a,webm',
                'max:' . ($maxSize / 1024), // Convert to KB
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Transcribe audio
        $transcribedText = $this->stt->transcribe($request->file('audio'));

        if (empty($transcribedText)) {
            return response()->json(['error' => 'Could not transcribe audio'], 400);
        }

        $user = $request->user();

        // Build context with memory
        $tasks = $user->tasks()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($task) {
                return [
                    'title' => $task->title,
                    'status' => $task->status,
                ];
            })
            ->toArray();

        $meetings = $user->meetings()
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '>=', now())
            ->orderBy('start_time', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($meeting) {
                return [
                    'title' => $meeting->title,
                    'start_time' => $meeting->start_time?->toIso8601String(),
                ];
            })
            ->toArray();

        $profileContext = $this->memoryService->getUserProfileContext($user);
        $memories = $this->memoryService->getUserMemoryContext($user);

        $context = [
            'tasks' => $tasks,
            'meetings' => $meetings,
            'profile' => $profileContext,
            'memories' => $memories,
        ];

        // Parse command with AI
        $actions = $this->ollama->parseVoiceCommand($transcribedText, $context);

        $created = [];
        $createdTasks = [];
        $createdMeetings = [];

        // Create tasks
        foreach ($actions['tasks'] ?? [] as $index => $taskData) {
            $task = $request->user()->tasks()->create([
                'title' => $taskData['title'] ?? 'Untitled Task',
                'description' => $taskData['description'] ?? null,
                'status' => 'pending',
                'due_at' => isset($taskData['due_at']) ? $taskData['due_at'] : null,
                'created_via' => 'voice',
            ]);

            $createdTasks[$index] = $task;
            $created['tasks'][] = $task;
        }

        // Create reminders for tasks
        foreach ($actions['reminders'] ?? [] as $reminderData) {
            if (isset($reminderData['task_index']) && isset($createdTasks[$reminderData['task_index']])) {
                Reminder::create([
                    'task_id' => $createdTasks[$reminderData['task_index']]->id,
                    'remind_at' => $reminderData['remind_at'],
                ]);
            }
        }

        // Create meetings
        foreach ($actions['meetings'] ?? [] as $index => $meetingData) {
            $startTime = isset($meetingData['start_time']) ? $meetingData['start_time'] : now()->addHour();
            $endTime = isset($meetingData['end_time']) 
                ? $meetingData['end_time'] 
                : (new \Carbon\Carbon($startTime))->addHour();

            $meeting = $request->user()->meetings()->create([
                'title' => $meetingData['title'] ?? 'Untitled Meeting',
                'description' => $meetingData['description'] ?? null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'location' => $meetingData['location'] ?? null,
                'attendees' => $meetingData['attendees'] ?? null,
                'status' => 'scheduled',
                'created_via' => 'voice',
            ]);

            $createdMeetings[$index] = $meeting;
            $created['meetings'][] = $meeting;
        }

        // Create meeting reminders
        foreach ($actions['meeting_reminders'] ?? [] as $reminderData) {
            if (isset($reminderData['meeting_index']) && isset($createdMeetings[$reminderData['meeting_index']])) {
                MeetingReminder::create([
                    'meeting_id' => $createdMeetings[$reminderData['meeting_index']]->id,
                    'remind_at' => $reminderData['remind_at'],
                ]);
            }
        }

        // Create notes
        foreach ($actions['notes'] ?? [] as $noteData) {
            $note = $request->user()->notes()->create([
                'title' => $noteData['title'] ?? 'Untitled Note',
                'encrypted_body' => $this->encryption->encrypt($noteData['body'] ?? ''),
            ]);
            $created['notes'][] = $note;
        }

        // Create password entries
        foreach ($actions['passwords'] ?? [] as $passwordData) {
            $entry = $request->user()->passwordEntries()->create([
                'label' => $passwordData['label'] ?? 'Untitled',
                'username' => $passwordData['username'] ?? '',
                'encrypted_password' => $this->encryption->encrypt($passwordData['password'] ?? ''),
                'encrypted_notes' => isset($passwordData['notes']) ? $this->encryption->encrypt($passwordData['notes']) : null,
            ]);
            $created['passwords'][] = $entry;
        }

        // Record interaction
        $this->memoryService->recordInteraction($user, 'voice_command', [
            'transcribed_length' => strlen($transcribedText),
            'actions_created' => !empty($created),
        ]);

        return response()->json([
            'transcribed_text' => $transcribedText,
            'created' => $created,
        ]);
    }
}

