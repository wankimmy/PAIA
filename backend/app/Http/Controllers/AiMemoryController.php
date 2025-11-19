<?php

namespace App\Http\Controllers;

use App\Models\AiMemory;
use App\Services\AiMemoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AiMemoryController extends Controller
{
    protected AiMemoryService $memoryService;

    public function __construct(AiMemoryService $memoryService)
    {
        $this->memoryService = $memoryService;
    }

    public function index(Request $request)
    {
        $query = $request->user()->aiMemories();

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by source
        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        // Filter by importance
        if ($request->has('min_importance')) {
            $query->where('importance', '>=', $request->min_importance);
        }

        // Search in value or key
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('value', 'like', "%{$search}%")
                  ->orWhere('key', 'like', "%{$search}%");
            });
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'importance');
        $sortOrder = $request->get('sort_order', 'desc');

        $validSortFields = ['importance', 'updated_at', 'created_at', 'category'];
        if (in_array($sortBy, $validSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('importance', 'desc')->orderBy('updated_at', 'desc');
        }

        $limit = min($request->get('limit', 50), 100);
        $memories = $query->limit($limit)->get();

        return response()->json($memories);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|in:personal_fact,preference,habit,goal,boundary',
            'key' => 'required|string|max:255',
            'value' => 'required|string|max:512',
            'importance' => 'nullable|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $memory = $this->memoryService->updateOrCreateMemory(
                $request->user(),
                $request->category,
                $request->key,
                $request->value,
                $request->importance ?? 3,
                'user_input'
            );

            return response()->json($memory, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        $memory = $request->user()->aiMemories()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'value' => 'sometimes|required|string|max:512',
            'importance' => 'sometimes|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $memory->update($request->only(['value', 'importance']));

        return response()->json($memory);
    }

    public function destroy(Request $request, $id)
    {
        $memory = $request->user()->aiMemories()->findOrFail($id);
        $memory->delete();

        return response()->json(['message' => 'Memory deleted']);
    }
}

