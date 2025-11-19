<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Task;
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

    public function __construct(
        SttService $stt,
        OllamaService $ollama,
        EncryptionService $encryption
    ) {
        $this->stt = $stt;
        $this->ollama = $ollama;
        $this->encryption = $encryption;
    }

    public function command(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'audio' => 'required|file|mimes:mp3,wav,ogg,m4a|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Transcribe audio
        $transcribedText = $this->stt->transcribe($request->file('audio'));

        if (empty($transcribedText)) {
            return response()->json(['error' => 'Could not transcribe audio'], 400);
        }

        // Build context
        $tasks = $request->user()->tasks()
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

        $context = ['tasks' => $tasks];

        // Parse command with AI
        $actions = $this->ollama->parseVoiceCommand($transcribedText, $context);

        $created = [];
        $createdTasks = [];

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

        return response()->json([
            'transcribed_text' => $transcribedText,
            'created' => $created,
        ]);
    }
}

