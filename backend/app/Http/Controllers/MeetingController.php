<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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
            'title' => 'required|string|max:' . config('security.max_lengths.title', 500),
            'description' => 'nullable|string|max:' . config('security.max_lengths.description', 10000),
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'location' => 'nullable|string|max:500',
            'attendees' => 'nullable|string|max:1000',
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

        // Convert datetime-local input (local timezone) to UTC for storage
        $user = $request->user();
        $timezone = $this->getUserTimezone($user);
        
        $startTime = $this->convertToUtc($request->start_time, $timezone);
        $endTime = $this->convertToUtc($request->end_time, $timezone);

        $meeting = $request->user()->meetings()->create([
            'title' => $request->title,
            'description' => $request->description,
            'start_time' => $startTime,
            'end_time' => $endTime,
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
            'title' => 'sometimes|required|string|max:' . config('security.max_lengths.title', 500),
            'description' => 'nullable|string|max:' . config('security.max_lengths.description', 10000),
            'start_time' => 'sometimes|required|date',
            'end_time' => 'nullable|date|after:start_time',
            'location' => 'nullable|string|max:500',
            'attendees' => 'nullable|string|max:1000',
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

        // Convert datetime-local input (local timezone) to UTC for storage
        $user = $request->user();
        $timezone = $this->getUserTimezone($user);
        
        $updateData = $request->only([
            'title', 'description', 'location', 'attendees', 'status', 'tag_id'
        ]);
        
        if ($request->has('start_time')) {
            $updateData['start_time'] = $this->convertToUtc($request->start_time, $timezone);
        }
        
        if ($request->has('end_time') && $request->end_time) {
            $updateData['end_time'] = $this->convertToUtc($request->end_time, $timezone);
        }

        $meeting->update($updateData);

        $meeting->load('tag');

        return response()->json($meeting);
    }

    public function destroy(Request $request, $id)
    {
        $meeting = $request->user()->meetings()->findOrFail($id);
        $meeting->delete();

        return response()->json(['message' => 'Meeting deleted']);
    }

    /**
     * Get the user's timezone, falling back to the app timezone or UTC.
     *
     * @param \App\Models\User $user
     * @return string
     */
    private function getUserTimezone($user)
    {
        return ($user->profile && $user->profile->timezone) 
            ? $user->profile->timezone 
            : config('app.timezone', 'UTC');
    }

    /**
     * Convert an optional datetime string from the given timezone to UTC.
     * The datetime string is expected to be in format "YYYY-MM-DDTHH:mm" (from datetime-local input).
     *
     * @param string|null $datetimeString
     * @param string $timezone
     * @return \Carbon\Carbon|null
     */
    private function convertToUtc($datetimeString, $timezone)
    {
        if (!$datetimeString) {
            return null;
        }
        
        // datetime-local input gives us "YYYY-MM-DDTHH:mm" without timezone info
        // Parse it in the user's timezone, then convert to UTC
        try {
            // Create Carbon instance in the specified timezone
            $carbon = Carbon::createFromFormat('Y-m-d\TH:i', $datetimeString, $timezone);
            // Convert to UTC for storage
            return $carbon->utc();
        } catch (\Exception $e) {
            // Fallback: try parsing with Carbon's flexible parser
            return Carbon::parse($datetimeString, $timezone)->utc();
        }
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

        // Convert datetime-local input (local timezone) to UTC for storage
        $user = $request->user();
        $timezone = $this->getUserTimezone($user);
        
        $remindAt = $this->convertToUtc($request->remind_at, $timezone);

        $reminder = $meeting->reminders()->create([
            'remind_at' => $remindAt,
        ]);

        return response()->json($reminder, 201);
    }
}

