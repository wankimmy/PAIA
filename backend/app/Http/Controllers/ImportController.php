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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportController extends Controller
{
    protected EncryptionService $encryption;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    public function json(Request $request)
    {
        $maxSize = config('security.file_upload.max_size', 10485760); // 10MB default
        
        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'mimes:json,application/json',
                'max:' . ($maxSize / 1024), // Convert to KB
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        
        // Additional security checks
        $allowedMimes = config('security.file_upload.allowed_mime_types', ['application/json', 'text/json']);
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            \Log::warning('Invalid file type upload attempt', [
                'user_id' => $request->user()->id,
                'mime_type' => $file->getMimeType(),
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid file type.'], 422);
        }
        
        // Check file size
        if ($file->getSize() > $maxSize) {
            return response()->json(['error' => 'File too large.'], 422);
        }
        
        $content = file_get_contents($file->getRealPath());
        
        // Validate JSON structure before decoding
        if (empty($content)) {
            return response()->json(['error' => 'File is empty.'], 422);
        }
        
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'error' => 'Invalid JSON file',
                'message' => json_last_error_msg()
            ], 422);
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            return response()->json([
                'error' => 'Invalid export format',
                'message' => 'Missing or invalid data section'
            ], 422);
        }

        $user = $request->user();
        $imported = [
            'tags' => 0,
            'tasks' => 0,
            'notes' => 0,
            'passwords' => 0,
            'meetings' => 0,
            'ai_memories' => 0,
            'chat_history' => 0,
            'profile' => false,
            'preferences' => false,
        ];

        DB::beginTransaction();
        try {
            $tagNameToId = [];

            // Import Tags first
            if (isset($data['data']['tags']) && is_array($data['data']['tags'])) {
                foreach ($data['data']['tags'] as $tagData) {
                    // Check if tag already exists by name
                    $existingTag = Tag::where('user_id', $user->id)
                        ->where('name', $tagData['name'])
                        ->first();
                    
                    if (!$existingTag) {
                        $tag = Tag::create([
                            'user_id' => $user->id,
                            'name' => $tagData['name'],
                            'color' => $tagData['color'] ?? '#7367f0',
                            'description' => $tagData['description'] ?? null,
                        ]);
                        $tagNameToId[$tagData['name']] = $tag->id;
                        $imported['tags']++;
                    } else {
                        $tagNameToId[$tagData['name']] = $existingTag->id;
                    }
                }
            }

            // Import Tasks
            if (isset($data['data']['tasks']) && is_array($data['data']['tasks'])) {
                foreach ($data['data']['tasks'] as $taskData) {
                    $tagId = null;
                    if (isset($taskData['tag_name']) && isset($tagNameToId[$taskData['tag_name']])) {
                        $tagId = $tagNameToId[$taskData['tag_name']];
                    }

                    $task = Task::create([
                        'user_id' => $user->id,
                        'title' => $taskData['title'],
                        'description' => $taskData['description'] ?? null,
                        'status' => $taskData['status'] ?? 'pending',
                        'due_at' => isset($taskData['due_at']) ? $taskData['due_at'] : null,
                        'tag_id' => $tagId,
                        'created_via' => $taskData['created_via'] ?? 'manual',
                    ]);

                    // Import task reminders
                    if (isset($taskData['reminders']) && is_array($taskData['reminders'])) {
                        foreach ($taskData['reminders'] as $reminderData) {
                            Reminder::create([
                                'task_id' => $task->id,
                                'remind_at' => $reminderData['remind_at'],
                            ]);
                        }
                    }

                    $imported['tasks']++;
                }
            }

            // Import Notes
            if (isset($data['data']['notes']) && is_array($data['data']['notes'])) {
                foreach ($data['data']['notes'] as $noteData) {
                    $tagId = null;
                    if (isset($noteData['tag_name']) && isset($tagNameToId[$noteData['tag_name']])) {
                        $tagId = $tagNameToId[$noteData['tag_name']];
                    }

                    Note::create([
                        'user_id' => $user->id,
                        'title' => $noteData['title'],
                        'encrypted_body' => $this->encryption->encrypt($noteData['body']),
                        'tag_id' => $tagId,
                    ]);

                    $imported['notes']++;
                }
            }

            // Import Passwords
            if (isset($data['data']['passwords']) && is_array($data['data']['passwords'])) {
                foreach ($data['data']['passwords'] as $passwordData) {
                    PasswordEntry::create([
                        'user_id' => $user->id,
                        'label' => $passwordData['label'],
                        'username' => $passwordData['username'],
                        'encrypted_password' => $this->encryption->encrypt($passwordData['password']),
                        'encrypted_notes' => isset($passwordData['notes']) ? $this->encryption->encrypt($passwordData['notes']) : null,
                    ]);

                    $imported['passwords']++;
                }
            }

            // Import Meetings
            if (isset($data['data']['meetings']) && is_array($data['data']['meetings'])) {
                foreach ($data['data']['meetings'] as $meetingData) {
                    $tagId = null;
                    if (isset($meetingData['tag_name']) && isset($tagNameToId[$meetingData['tag_name']])) {
                        $tagId = $tagNameToId[$meetingData['tag_name']];
                    }

                    $meeting = Meeting::create([
                        'user_id' => $user->id,
                        'title' => $meetingData['title'],
                        'description' => $meetingData['description'] ?? null,
                        'start_time' => $meetingData['start_time'],
                        'end_time' => $meetingData['end_time'] ?? null,
                        'location' => $meetingData['location'] ?? null,
                        'attendees' => $meetingData['attendees'] ?? null,
                        'status' => $meetingData['status'] ?? 'scheduled',
                        'tag_id' => $tagId,
                        'created_via' => $meetingData['created_via'] ?? 'manual',
                    ]);

                    // Import meeting reminders
                    if (isset($meetingData['reminders']) && is_array($meetingData['reminders'])) {
                        foreach ($meetingData['reminders'] as $reminderData) {
                            MeetingReminder::create([
                                'meeting_id' => $meeting->id,
                                'remind_at' => $reminderData['remind_at'],
                            ]);
                        }
                    }

                    $imported['meetings']++;
                }
            }

            // Import AI Memories
            if (isset($data['data']['ai_memories']) && is_array($data['data']['ai_memories'])) {
                foreach ($data['data']['ai_memories'] as $memoryData) {
                    // Check if memory already exists by key
                    $existing = AiMemory::where('user_id', $user->id)
                        ->where('key', $memoryData['key'])
                        ->first();
                    
                    if (!$existing) {
                        AiMemory::create([
                            'user_id' => $user->id,
                            'category' => $memoryData['category'],
                            'key' => $memoryData['key'],
                            'value' => $memoryData['value'],
                            'importance' => $memoryData['importance'] ?? 3,
                            'source' => $memoryData['source'] ?? 'user_input',
                        ]);
                        $imported['ai_memories']++;
                    }
                }
            }

            // Import Chat History
            if (isset($data['data']['chat_history']) && is_array($data['data']['chat_history'])) {
                foreach ($data['data']['chat_history'] as $chatData) {
                    AiInteraction::create([
                        'user_id' => $user->id,
                        'interaction_type' => 'chat',
                        'metadata' => [
                            'user_message' => $chatData['user_message'] ?? '',
                            'ai_response' => $chatData['ai_response'] ?? '',
                            'actions' => $chatData['actions'] ?? [],
                        ],
                        'occurred_at' => $chatData['occurred_at'],
                    ]);
                    $imported['chat_history']++;
                }
            }

            // Import Profile (update if exists, create if not)
            if (isset($data['data']['profile']) && is_array($data['data']['profile'])) {
                $profile = $user->profile;
                if ($profile) {
                    $profile->update($data['data']['profile']);
                } else {
                    $user->profile()->create($data['data']['profile']);
                }
                $imported['profile'] = true;
            }

            // Import Preferences (update if exists, create if not)
            if (isset($data['data']['preferences']) && is_array($data['data']['preferences'])) {
                $preferences = $user->preferences;
                if ($preferences) {
                    $preferences->update($data['data']['preferences']);
                } else {
                    $user->preferences()->create($data['data']['preferences']);
                }
                $imported['preferences'] = true;
            }

            DB::commit();

            return response()->json([
                'message' => 'Data imported successfully',
                'imported' => $imported
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log error but don't expose details to client
            \Log::error('Import failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Import failed',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred during import. Please try again.'
            ], 500);
        }
    }
}

