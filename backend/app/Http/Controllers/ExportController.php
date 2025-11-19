<?php

namespace App\Http\Controllers;

use App\Models\AiInteraction;
use App\Models\AiMemory;
use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Models\Note;
use App\Models\PasswordEntry;
use App\Models\Reminder;
use App\Models\Tag;
use App\Models\Task;
use App\Models\UserPreference;
use App\Models\UserProfile;
use App\Services\EncryptionService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    protected EncryptionService $encryption;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    public function txt(Request $request)
    {
        $user = $request->user();
        $content = [];

        $content[] = "Personal AI Assistant - Data Export";
        $content[] = "Generated: " . now()->toDateTimeString();
        $content[] = "User: {$user->email}";
        $content[] = str_repeat("=", 60);
        $content[] = "";

        // Tasks
        $content[] = "TASKS";
        $content[] = str_repeat("-", 60);
        $tasks = $user->tasks()->orderBy('created_at', 'desc')->get();
        if ($tasks->isEmpty()) {
            $content[] = "No tasks found.";
        } else {
            foreach ($tasks as $task) {
                $content[] = "Title: {$task->title}";
                if ($task->description) {
                    $content[] = "Description: {$task->description}";
                }
                $content[] = "Status: {$task->status}";
                if ($task->due_at) {
                    $content[] = "Due: {$task->due_at->toDateTimeString()}";
                }
                if ($task->tag) {
                    $content[] = "Tag: {$task->tag}";
                }
                $content[] = "Created: {$task->created_at->toDateTimeString()}";
                $content[] = "";
            }
        }
        $content[] = "";

        // Notes
        $content[] = "NOTES";
        $content[] = str_repeat("-", 60);
        $notes = $user->notes()->orderBy('created_at', 'desc')->get();
        if ($notes->isEmpty()) {
            $content[] = "No notes found.";
        } else {
            foreach ($notes as $note) {
                $content[] = "Title: {$note->title}";
                $content[] = "Body:";
                $content[] = $this->encryption->decrypt($note->encrypted_body);
                $content[] = "Created: {$note->created_at->toDateTimeString()}";
                $content[] = "";
            }
        }
        $content[] = "";

        // Passwords
        $content[] = "PASSWORD ENTRIES";
        $content[] = str_repeat("-", 60);
        $passwords = $user->passwordEntries()->orderBy('created_at', 'desc')->get();
        if ($passwords->isEmpty()) {
            $content[] = "No password entries found.";
        } else {
            foreach ($passwords as $entry) {
                $content[] = "Label: {$entry->label}";
                $content[] = "Username: {$entry->username}";
                $content[] = "Password: " . $this->encryption->decrypt($entry->encrypted_password);
                if ($entry->encrypted_notes) {
                    $content[] = "Notes: " . $this->encryption->decrypt($entry->encrypted_notes);
                }
                $content[] = "Created: {$entry->created_at->toDateTimeString()}";
                $content[] = "";
            }
        }

        $text = implode("\n", $content);

        return response($text, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="paia-export-' . now()->format('Y-m-d-His') . '.txt"');
    }

    public function json(Request $request)
    {
        $user = $request->user();
        
        $data = [
            'export_version' => '1.0',
            'export_date' => now()->toIso8601String(),
            'user_email' => $user->email,
            'data' => []
        ];

        // Tags (export first as other entities reference them)
        $tags = $user->tags()->orderBy('created_at')->get();
        $data['data']['tags'] = $tags->map(function ($tag) {
            return [
                'name' => $tag->name,
                'color' => $tag->color,
                'description' => $tag->description,
                'created_at' => $tag->created_at->toIso8601String(),
            ];
        })->toArray();

        // Create tag name to ID mapping for reference
        $tagMap = [];
        foreach ($tags as $tag) {
            $tagMap[$tag->name] = $tag->id;
        }

        // Tasks with reminders
        $tasks = $user->tasks()->with('reminders', 'tag')->orderBy('created_at')->get();
        $data['data']['tasks'] = $tasks->map(function ($task) use ($tagMap) {
            $taskData = [
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'due_at' => $task->due_at ? $task->due_at->toIso8601String() : null,
                'tag_name' => $task->tag ? $task->tag->name : null,
                'created_via' => $task->created_via,
                'created_at' => $task->created_at->toIso8601String(),
            ];
            
            if ($task->reminders->isNotEmpty()) {
                $taskData['reminders'] = $task->reminders->map(function ($reminder) {
                    return [
                        'remind_at' => $reminder->remind_at->toIso8601String(),
                        'created_at' => $reminder->created_at->toIso8601String(),
                    ];
                })->toArray();
            }
            
            return $taskData;
        })->toArray();

        // Notes
        $notes = $user->notes()->with('tag')->orderBy('created_at')->get();
        $data['data']['notes'] = $notes->map(function ($note) {
            return [
                'title' => $note->title,
                'body' => $this->encryption->decrypt($note->encrypted_body),
                'tag_name' => $note->tag ? $note->tag->name : null,
                'created_at' => $note->created_at->toIso8601String(),
            ];
        })->toArray();

        // Password Entries
        $passwords = $user->passwordEntries()->orderBy('created_at')->get();
        $data['data']['passwords'] = $passwords->map(function ($entry) {
            return [
                'label' => $entry->label,
                'username' => $entry->username,
                'password' => $this->encryption->decrypt($entry->encrypted_password),
                'notes' => $entry->encrypted_notes ? $this->encryption->decrypt($entry->encrypted_notes) : null,
                'created_at' => $entry->created_at->toIso8601String(),
            ];
        })->toArray();

        // Meetings with reminders
        $meetings = $user->meetings()->with('reminders', 'tag')->orderBy('created_at')->get();
        $data['data']['meetings'] = $meetings->map(function ($meeting) {
            $meetingData = [
                'title' => $meeting->title,
                'description' => $meeting->description,
                'start_time' => $meeting->start_time->toIso8601String(),
                'end_time' => $meeting->end_time ? $meeting->end_time->toIso8601String() : null,
                'location' => $meeting->location,
                'attendees' => $meeting->attendees,
                'status' => $meeting->status,
                'tag_name' => $meeting->tag ? $meeting->tag->name : null,
                'created_via' => $meeting->created_via,
                'created_at' => $meeting->created_at->toIso8601String(),
            ];
            
            if ($meeting->reminders->isNotEmpty()) {
                $meetingData['reminders'] = $meeting->reminders->map(function ($reminder) {
                    return [
                        'remind_at' => $reminder->remind_at->toIso8601String(),
                        'created_at' => $reminder->created_at->toIso8601String(),
                    ];
                })->toArray();
            }
            
            return $meetingData;
        })->toArray();

        // AI Memories
        $memories = AiMemory::where('user_id', $user->id)->orderBy('created_at')->get();
        $data['data']['ai_memories'] = $memories->map(function ($memory) {
            return [
                'category' => $memory->category,
                'key' => $memory->key,
                'value' => $memory->value,
                'importance' => $memory->importance,
                'source' => $memory->source,
                'created_at' => $memory->created_at->toIso8601String(),
            ];
        })->toArray();

        // AI Interactions (Chat History)
        $interactions = AiInteraction::where('user_id', $user->id)
            ->where('interaction_type', 'chat')
            ->orderBy('occurred_at')
            ->get();
        $data['data']['chat_history'] = $interactions->map(function ($interaction) {
            $meta = $interaction->metadata ?? [];
            return [
                'user_message' => $meta['user_message'] ?? '',
                'ai_response' => $meta['ai_response'] ?? '',
                'actions' => $meta['actions'] ?? [],
                'occurred_at' => $interaction->occurred_at->toIso8601String(),
            ];
        })->toArray();

        // User Profile
        $profile = $user->profile;
        if ($profile) {
            $data['data']['profile'] = [
                'full_name' => $profile->full_name,
                'nickname' => $profile->nickname,
                'ai_name' => $profile->ai_name,
                'pronouns' => $profile->pronouns,
                'bio' => $profile->bio,
                'timezone' => $profile->timezone,
                'primary_language' => $profile->primary_language,
                'preferred_tone' => $profile->preferred_tone,
                'preferred_answer_length' => $profile->preferred_answer_length,
            ];
        }

        // User Preferences
        $preferences = $user->preferences;
        if ($preferences) {
            $data['data']['preferences'] = $preferences->toArray();
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response($json, 200)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="paia-export-' . now()->format('Y-m-d-His') . '.json"');
    }
}

