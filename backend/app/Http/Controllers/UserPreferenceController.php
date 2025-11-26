<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserPreferenceController extends Controller
{
    public function show(Request $request)
    {
        $preference = $request->user()->preferences;
        
        if (!$preference) {
            $preference = $request->user()->preferences()->create([
                'onboarding_completed' => false,
                'preferences' => [],
                'ai_context' => [],
            ]);
        }

        return response()->json($preference);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'onboarding_completed' => 'sometimes|boolean',
            'preferences' => 'sometimes|array|max:100', // Limit array size
            'ai_context' => 'sometimes|array|max:100', // Limit array size
        ]);
        
        // Validate array contents to prevent excessive data
        if ($request->has('preferences') && is_array($request->preferences)) {
            $prefSize = strlen(json_encode($request->preferences));
            if ($prefSize > 100000) { // 100KB limit
                return response()->json(['errors' => ['preferences' => ['Preferences data too large.']]], 422);
            }
        }
        
        if ($request->has('ai_context') && is_array($request->ai_context)) {
            $contextSize = strlen(json_encode($request->ai_context));
            if ($contextSize > 100000) { // 100KB limit
                return response()->json(['errors' => ['ai_context' => ['AI context data too large.']]], 422);
            }
        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $preference = $request->user()->preferences;
        
        if (!$preference) {
            $preference = $request->user()->preferences()->create([
                'onboarding_completed' => false,
                'preferences' => [],
                'ai_context' => [],
            ]);
        }

        $preference->update($request->only(['onboarding_completed', 'preferences', 'ai_context']));

        return response()->json($preference);
    }
}

