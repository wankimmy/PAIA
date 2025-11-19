<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $profile = $request->user()->profile;
        
        if (!$profile) {
            // Return defaults
            return response()->json([
                'full_name' => null,
                'nickname' => null,
                'ai_name' => null,
                'pronouns' => null,
                'bio' => null,
                'timezone' => null,
                'primary_language' => null,
                'preferred_tone' => null,
                'preferred_answer_length' => null,
            ]);
        }

        return response()->json($profile);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'ai_name' => 'nullable|string|max:255',
            'pronouns' => 'nullable|string|max:50',
            'bio' => 'nullable|string|max:1000',
            'timezone' => 'nullable|string|max:100',
            'primary_language' => 'nullable|string|max:50',
            'preferred_tone' => 'nullable|in:friendly,professional,casual',
            'preferred_answer_length' => 'nullable|in:short,normal,detailed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $profile = $request->user()->profile;
        
        if (!$profile) {
            $profile = $request->user()->profile()->create($request->only([
                'full_name', 'nickname', 'ai_name', 'pronouns', 'bio', 'timezone',
                'primary_language', 'preferred_tone', 'preferred_answer_length'
            ]));
        } else {
            $profile->update($request->only([
                'full_name', 'nickname', 'ai_name', 'pronouns', 'bio', 'timezone',
                'primary_language', 'preferred_tone', 'preferred_answer_length'
            ]));
        }

        return response()->json($profile);
    }
}

