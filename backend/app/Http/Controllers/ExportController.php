<?php

namespace App\Http\Controllers;

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
}

