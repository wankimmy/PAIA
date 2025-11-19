<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks = $request->user()->tasks()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,done,cancelled',
            'due_at' => 'nullable|date',
            'tag' => 'nullable|string|max:255',
            'created_via' => 'nullable|in:manual,voice,ai',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $task = $request->user()->tasks()->create([
            'title' => $request->title,
            'description' => $request->description,
            'status' => $request->status ?? 'pending',
            'due_at' => $request->due_at,
            'tag' => $request->tag,
            'created_via' => $request->created_via ?? 'manual',
        ]);

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

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,done,cancelled',
            'due_at' => 'nullable|date',
            'tag' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $task->update($request->only(['title', 'description', 'status', 'due_at', 'tag']));

        return response()->json($task);
    }

    public function destroy(Request $request, $id)
    {
        $task = $request->user()->tasks()->findOrFail($id);
        $task->delete();

        return response()->json(['message' => 'Task deleted']);
    }
}

