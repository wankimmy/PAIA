<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MeetingController extends Controller
{
    public function index(Request $request)
    {
        $meetings = $request->user()->meetings()
            ->with('tag')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json($meetings);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'location' => 'nullable|string|max:255',
            'attendees' => 'nullable|string',
            'status' => 'nullable|in:scheduled,cancelled,completed',
            'created_via' => 'nullable|in:manual,voice,ai',
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

        $meeting = $request->user()->meetings()->create([
            'title' => $request->title,
            'description' => $request->description,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'location' => $request->location,
            'attendees' => $request->attendees,
            'status' => $request->status ?? 'scheduled',
            'created_via' => $request->created_via ?? 'manual',
            'tag_id' => $request->tag_id,
        ]);

        $meeting->load('tag');

        return response()->json($meeting, 201);
    }

    public function show(Request $request, $id)
    {
        $meeting = $request->user()->meetings()->with('reminders')->findOrFail($id);
        return response()->json($meeting);
    }

    public function update(Request $request, $id)
    {
        $meeting = $request->user()->meetings()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'sometimes|required|date',
            'end_time' => 'nullable|date|after:start_time',
            'location' => 'nullable|string|max:255',
            'attendees' => 'nullable|string',
            'status' => 'nullable|in:scheduled,cancelled,completed',
            'tag_id' => 'nullable|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify tag belongs to user if provided
        if ($request->has('tag_id') && $request->tag_id) {
            $tag = $request->user()->tags()->find($request->tag_id);
            if (!$tag) {
                return response()->json(['error' => 'Tag not found'], 404);
            }
        }

        $meeting->update($request->only([
            'title', 'description', 'start_time', 'end_time',
            'location', 'attendees', 'status', 'tag_id'
        ]));

        $meeting->load('tag');

        return response()->json($meeting);
    }

    public function destroy(Request $request, $id)
    {
        $meeting = $request->user()->meetings()->findOrFail($id);
        $meeting->delete();

        return response()->json(['message' => 'Meeting deleted']);
    }

    public function addReminder(Request $request, $id)
    {
        $meeting = $request->user()->meetings()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'remind_at' => 'required|date|before:start_time',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reminder = $meeting->reminders()->create([
            'remind_at' => $request->remind_at,
        ]);

        return response()->json($reminder, 201);
    }
}

