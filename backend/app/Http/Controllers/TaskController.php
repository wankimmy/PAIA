<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\AiMemoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    protected AiMemoryService $memoryService;

    public function __construct(AiMemoryService $memoryService)
    {
        $this->memoryService = $memoryService;
    }

    public function index(Request $request)
    {
        $tasks = $request->user()->tasks()
            ->with('tag')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:' . config('security.max_lengths.title', 500),
            'description' => 'nullable|string|max:' . config('security.max_lengths.description', 10000),
            'status' => 'nullable|in:pending,done,cancelled',
            'due_at' => 'nullable|date',
            'tag' => 'nullable|string|max:255', // Keep for backward compatibility
            'tag_id' => 'nullable|exists:tags,id',
            'created_via' => 'nullable|in:manual,voice,ai',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify tag belongs to user if provided
        if ($request->tag_id) {
            $tag = $request->user()->tags()->find($request->tag_id);
            if (!$tag) {
                return response()->json(['error' => 'Tag not found'], 404);
            }
        }

        $task = $request->user()->tasks()->create([
            'title' => $request->title,
            'description' => $request->description,
            'status' => $request->status ?? 'pending',
            'due_at' => $request->due_at,
            'tag' => $request->tag, // Keep for backward compatibility
            'tag_id' => $request->tag_id,
            'created_via' => $request->created_via ?? 'manual',
        ]);

        $task->load('tag');

        return response()->json($task, 201);
    }

    public function show(Request $request, $id)
    {
        $task = $request->user()->tasks()->findOrFail($id);
        return response()->json($task);
    }

    public function update(Request $request, $id)
    {
        $task = $request->user()->tasks()->findOrFail($id);
        $wasDone = $task->status === 'done';
        $newStatus = $request->input('status');

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,done,cancelled',
            'due_at' => 'nullable|date',
            'tag' => 'nullable|string|max:255', // Keep for backward compatibility
            'tag_id' => 'nullable|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify tag belongs to user if provided
        if ($request->tag_id) {
            $tag = $request->user()->tags()->find($request->tag_id);
            if (!$tag) {
                return response()->json(['error' => 'Tag not found'], 404);
            }
        }

        $task->update($request->only(['title', 'description', 'status', 'due_at', 'tag', 'tag_id']));
        $task->load('tag');

        // Track task completion behavior
        if (!$wasDone && $newStatus === 'done') {
            $wasOverdue = false;
            if ($task->due_at) {
                $wasOverdue = \Carbon\Carbon::parse($task->due_at)->isPast();
            }
            
            $tagName = $task->tag ? $task->tag->name : ($task->tag ?? null);
            
            $this->memoryService->recordInteraction($request->user(), 'task_complete', [
                'task_id' => $task->id,
                'completed_at_hour' => now()->hour,
                'tag' => $tagName,
                'was_overdue' => $wasOverdue,
            ]);
        }

        return response()->json($task);
    }

    public function destroy(Request $request, $id)
    {
        $task = $request->user()->tasks()->findOrFail($id);
        $task->delete();

        return response()->json(['message' => 'Task deleted']);
    }
}

