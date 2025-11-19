<?php

namespace App\Services;

use App\Models\AiInteraction;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BehaviorAnalysisService
{
    protected AiMemoryService $memoryService;

    public function __construct(AiMemoryService $memoryService)
    {
        $this->memoryService = $memoryService;
    }

    public function analyzeTaskCompletionPatterns(User $user, int $days = 30): array
    {
        $completions = AiInteraction::where('user_id', $user->id)
            ->where('interaction_type', 'task_complete')
            ->where('occurred_at', '>=', now()->subDays($days))
            ->get();

        if ($completions->count() < 5) {
            return [];
        }

        $patterns = [
            'total_completions' => $completions->count(),
            'time_distribution' => $this->analyzeTimeDistribution($completions),
            'tag_patterns' => $this->analyzeTagPatterns($completions),
            'overdue_rate' => $this->analyzeOverdueRate($completions),
            'completion_consistency' => $this->analyzeConsistency($completions),
        ];

        return $patterns;
    }

    public function analyzeReminderPatterns(User $user, int $days = 30): array
    {
        $reminders = AiInteraction::where('user_id', $user->id)
            ->whereIn('interaction_type', ['reminder_fired', 'reminder_snooze'])
            ->where('occurred_at', '>=', now()->subDays($days))
            ->get();

        if ($reminders->isEmpty()) {
            return [];
        }

        $fired = $reminders->where('interaction_type', 'reminder_fired');
        $snoozed = $reminders->where('interaction_type', 'reminder_snooze');

        return [
            'total_fired' => $fired->count(),
            'total_snoozed' => $snoozed->count(),
            'snooze_rate' => $reminders->count() > 0 ? ($snoozed->count() / $reminders->count()) * 100 : 0,
            'most_active_hours' => $this->getMostActiveHours($fired),
        ];
    }

    public function analyzeChatPatterns(User $user, int $days = 30): array
    {
        $chats = AiInteraction::where('user_id', $user->id)
            ->where('interaction_type', 'chat')
            ->where('occurred_at', '>=', now()->subDays($days))
            ->get();

        if ($chats->isEmpty()) {
            return [];
        }

        $avgMessageLength = $chats->avg(fn($chat) => $chat->metadata['message_length'] ?? 0);
        $avgResponseLength = $chats->avg(fn($chat) => $chat->metadata['response_length'] ?? 0);
        $actionRate = $chats->where('metadata.actions_executed', true)->count() / $chats->count() * 100;

        return [
            'total_chats' => $chats->count(),
            'avg_message_length' => round($avgMessageLength),
            'avg_response_length' => round($avgResponseLength),
            'action_rate' => round($actionRate, 1),
            'most_active_days' => $this->getMostActiveDays($chats),
        ];
    }

    public function generateInsights(User $user): array
    {
        $taskPatterns = $this->analyzeTaskCompletionPatterns($user);
        $reminderPatterns = $this->analyzeReminderPatterns($user);
        $chatPatterns = $this->analyzeChatPatterns($user);

        $insights = [];

        // Task completion insights
        if (!empty($taskPatterns)) {
            $timeDist = $taskPatterns['time_distribution'] ?? [];
            if (!empty($timeDist)) {
                $peakTime = array_search(max($timeDist), $timeDist);
                $insights[] = [
                    'type' => 'habit',
                    'category' => 'task_completion',
                    'key' => 'peak_completion_time',
                    'value' => "You're most productive completing tasks during {$peakTime}.",
                    'importance' => 4,
                ];
            }

            if (isset($taskPatterns['overdue_rate']) && $taskPatterns['overdue_rate'] > 30) {
                $insights[] = [
                    'type' => 'habit',
                    'category' => 'task_management',
                    'key' => 'high_overdue_rate',
                    'value' => "You tend to complete many tasks after their due date. Consider setting earlier reminders.",
                    'importance' => 3,
                ];
            }
        }

        // Reminder insights
        if (!empty($reminderPatterns) && $reminderPatterns['snooze_rate'] > 50) {
            $insights[] = [
                'type' => 'preference',
                'category' => 'reminders',
                'key' => 'prefers_flexible_reminders',
                'value' => 'You frequently snooze reminders, suggesting you prefer more flexible scheduling.',
                'importance' => 3,
            ];
        }

        // Chat insights
        if (!empty($chatPatterns) && $chatPatterns['action_rate'] > 40) {
            $insights[] = [
                'type' => 'preference',
                'category' => 'ai_interaction',
                'key' => 'prefers_action_oriented',
                'value' => 'You often use the AI to create tasks and notes, showing an action-oriented approach.',
                'importance' => 4,
            ];
        }

        return $insights;
    }

    protected function analyzeTimeDistribution($completions): array
    {
        $distribution = [
            'morning' => 0,
            'afternoon' => 0,
            'evening' => 0,
            'night' => 0,
        ];

        foreach ($completions as $completion) {
            $hour = $completion->metadata['completed_at_hour'] ?? null;
            if ($hour !== null) {
                $timeOfDay = $this->getTimeOfDayFromHour($hour);
                $distribution[$timeOfDay]++;
            }
        }

        return $distribution;
    }

    protected function analyzeTagPatterns($completions): array
    {
        $tags = [];
        foreach ($completions as $completion) {
            $tag = $completion->metadata['tag'] ?? null;
            if ($tag) {
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }
        }

        arsort($tags);
        return array_slice($tags, 0, 5, true);
    }

    protected function analyzeOverdueRate($completions): float
    {
        $overdue = $completions->where('metadata.was_overdue', true)->count();
        return $completions->count() > 0 ? ($overdue / $completions->count()) * 100 : 0;
    }

    protected function analyzeConsistency($completions): array
    {
        $daysOfWeek = [];
        foreach ($completions as $completion) {
            $day = Carbon::parse($completion->occurred_at)->format('l');
            $daysOfWeek[$day] = ($daysOfWeek[$day] ?? 0) + 1;
        }

        arsort($daysOfWeek);
        return [
            'most_active_day' => array_key_first($daysOfWeek),
            'distribution' => $daysOfWeek,
        ];
    }

    protected function getMostActiveHours($interactions): array
    {
        $hours = [];
        foreach ($interactions as $interaction) {
            $hour = $interaction->metadata['remind_at_hour'] ?? Carbon::parse($interaction->occurred_at)->hour;
            $hours[$hour] = ($hours[$hour] ?? 0) + 1;
        }

        arsort($hours);
        return array_slice($hours, 0, 3, true);
    }

    protected function getMostActiveDays($interactions): array
    {
        $days = [];
        foreach ($interactions as $interaction) {
            $day = Carbon::parse($interaction->occurred_at)->format('l');
            $days[$day] = ($days[$day] ?? 0) + 1;
        }

        arsort($days);
        return array_slice($days, 0, 3, true);
    }

    protected function getTimeOfDayFromHour(int $hour): string
    {
        if ($hour >= 5 && $hour < 12) {
            return 'morning';
        } elseif ($hour >= 12 && $hour < 17) {
            return 'afternoon';
        } elseif ($hour >= 17 && $hour < 22) {
            return 'evening';
        } else {
            return 'night';
        }
    }
}

