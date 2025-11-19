<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TagController extends Controller
{
    public function index(Request $request)
    {
        $tags = $request->user()->tags()->orderBy('name')->get();
        return response()->json($tags);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if tag with same name already exists for this user
        $existingTag = $request->user()->tags()
            ->where('name', $request->name)
            ->first();

        if ($existingTag) {
            return response()->json(['error' => 'Tag with this name already exists'], 409);
        }

        $tag = $request->user()->tags()->create($request->only(['name', 'color', 'description']));

        return response()->json($tag, 201);
    }

    public function update(Request $request, $id)
    {
        $tag = $request->user()->tags()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if another tag with same name exists
        if ($request->has('name')) {
            $existingTag = $request->user()->tags()
                ->where('name', $request->name)
                ->where('id', '!=', $id)
                ->first();

            if ($existingTag) {
                return response()->json(['error' => 'Tag with this name already exists'], 409);
            }
        }

        $tag->update($request->only(['name', 'color', 'description']));

        return response()->json($tag);
    }

    public function destroy(Request $request, $id)
    {
        $tag = $request->user()->tags()->findOrFail($id);

        // Check if tag is used by any tasks
        if ($tag->tasks()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete tag that is assigned to tasks. Please remove the tag from all tasks first.'
            ], 409);
        }

        $tag->delete();

        return response()->json(['message' => 'Tag deleted']);
    }
}

