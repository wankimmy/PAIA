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
            'preferences' => 'sometimes|array',
            'ai_context' => 'sometimes|array',
        ]);

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

