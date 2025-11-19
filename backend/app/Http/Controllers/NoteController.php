<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NoteController extends Controller
{
    protected EncryptionService $encryption;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    public function index(Request $request)
    {
        $notes = $request->user()->notes()
            ->with('tag')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($note) {
                return [
                    'id' => $note->id,
                    'title' => $note->title,
                    'body' => $this->encryption->decrypt($note->encrypted_body),
                    'tag_id' => $note->tag_id,
                    'tag' => $note->tag,
                    'created_at' => $note->created_at,
                    'updated_at' => $note->updated_at,
                ];
            });

        return response()->json($notes);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
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

        $note = $request->user()->notes()->create([
            'title' => $request->title,
            'encrypted_body' => $this->encryption->encrypt($request->body),
            'tag_id' => $request->tag_id,
        ]);

        $note->load('tag');

        return response()->json([
            'id' => $note->id,
            'title' => $note->title,
            'body' => $request->body,
            'tag_id' => $note->tag_id,
            'tag' => $note->tag,
            'created_at' => $note->created_at,
            'updated_at' => $note->updated_at,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $note = $request->user()->notes()->with('tag')->findOrFail($id);

        return response()->json([
            'id' => $note->id,
            'title' => $note->title,
            'body' => $this->encryption->decrypt($note->encrypted_body),
            'tag_id' => $note->tag_id,
            'tag' => $note->tag,
            'created_at' => $note->created_at,
            'updated_at' => $note->updated_at,
        ]);
    }

    public function update(Request $request, $id)
    {
        $note = $request->user()->notes()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
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

        $updateData = [];
        if ($request->has('title')) {
            $updateData['title'] = $request->title;
        }
        if ($request->has('body')) {
            $updateData['encrypted_body'] = $this->encryption->encrypt($request->body);
        }
        if ($request->has('tag_id')) {
            $updateData['tag_id'] = $request->tag_id;
        }

        $note->update($updateData);
        $note->load('tag');

        return response()->json([
            'id' => $note->id,
            'title' => $note->title,
            'body' => $this->encryption->decrypt($note->encrypted_body),
            'tag_id' => $note->tag_id,
            'tag' => $note->tag,
            'created_at' => $note->created_at,
            'updated_at' => $note->updated_at,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $note = $request->user()->notes()->findOrFail($id);
        $note->delete();

        return response()->json(['message' => 'Note deleted']);
    }
}

