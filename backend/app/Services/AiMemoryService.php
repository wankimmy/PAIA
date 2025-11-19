<?php

namespace App\Services;

use App\Models\AiInteraction;
use App\Models\AiMemory;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AiMemoryService
{
    public function getUserProfileContext(User $user): array
    {
        $profile = $user->profile;

        if (!$profile) {
            return [];
        }

        return [
            'name' => $profile->full_name,
            'nickname' => $profile->nickname,
            'ai_name' => $profile->ai_name,
            'pronouns' => $profile->pronouns,
            'bio' => $profile->bio,
            'timezone' => $profile->timezone,
            'primary_language' => $profile->primary_language,
            'preferred_tone' => $profile->preferred_tone,
            'preferred_answer_length' => $profile->preferred_answer_length,
        ];
    }

    public function getUserMemoryContext(User $user, int $limit = 20): array
    {
        $memories = $user->aiMemories()
            ->orderBy('importance', 'desc')
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return $memories->map(function ($memory) {
            return [
                'category' => $memory->category,
                'key' => $memory->key,
                'value' => $memory->value,
                'importance' => $memory->importance,
            ];
        })->toArray();
    }

    public function recordInteraction(User $user, string $type, array $meta = []): void
    {
        try {
            AiInteraction::create([
                'user_id' => $user->id,
                'interaction_type' => $type,
                'metadata' => $meta,
                'occurred_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record interaction', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updateOrCreateMemory(
        User $user,
        string $category,
        string $key,
        string $value,
        int $importance = 3,
        string $source = 'ai_inferred'
    ): AiMemory {
        // Basic safety filter: skip if contains password-like patterns
        if ($this->containsSensitiveData($value)) {
            throw new \Exception('Memory value contains potentially sensitive data');
        }

        return AiMemory::updateOrCreate(
            [
                'user_id' => $user->id,
                'key' => $key,
            ],
            [
                'category' => $category,
                'value' => $value,
                'importance' => $importance,
                'source' => $source,
            ]
        );
    }

    protected function containsSensitiveData(string $value): bool
    {
        $sensitivePatterns = [
            '/password\s*is/i',
            '/my password/i',
            '/pwd\s*[:=]/i',
            '/secret\s*[:=]/i',
            '/api[_-]?key/i',
            '/token\s*[:=]/i',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    public function extractMemoriesFromConversation(string $userMessage, string $aiResponse, array $existingMemories = []): array
    {
        // This will be called by the AI service to extract new memories
        // For now, return empty array - will be implemented with Ollama call
        return [];
    }

    public function getChatHistory(User $user, int $limit = 10): array
    {
        $interactions = AiInteraction::where('user_id', $user->id)
            ->where('interaction_type', 'chat')
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get();

        return $interactions->map(function ($interaction) {
            $meta = $interaction->metadata ?? [];
            return [
                'user_message' => $meta['user_message'] ?? '',
                'ai_response' => $meta['ai_response'] ?? '',
                'occurred_at' => $interaction->occurred_at->toIso8601String(),
            ];
        })->reverse()->values()->toArray(); // Reverse to get chronological order
    }
}

