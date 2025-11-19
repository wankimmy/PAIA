<?php

namespace App\Http\Controllers;

use App\Services\BehaviorAnalysisService;
use Illuminate\Http\Request;

class BehaviorInsightsController extends Controller
{
    protected BehaviorAnalysisService $behaviorAnalysis;

    public function __construct(BehaviorAnalysisService $behaviorAnalysis)
    {
        $this->behaviorAnalysis = $behaviorAnalysis;
    }

    public function insights(Request $request)
    {
        $user = $request->user();

        $taskPatterns = $this->behaviorAnalysis->analyzeTaskCompletionPatterns($user);
        $reminderPatterns = $this->behaviorAnalysis->analyzeReminderPatterns($user);
        $chatPatterns = $this->behaviorAnalysis->analyzeChatPatterns($user);

        return response()->json([
            'task_patterns' => $taskPatterns,
            'reminder_patterns' => $reminderPatterns,
            'chat_patterns' => $chatPatterns,
            'summary' => $this->generateSummary($taskPatterns, $reminderPatterns, $chatPatterns),
        ]);
    }

    protected function generateSummary(array $taskPatterns, array $reminderPatterns, array $chatPatterns): array
    {
        $summary = [];

        if (!empty($taskPatterns)) {
            $timeDist = $taskPatterns['time_distribution'] ?? [];
            if (!empty($timeDist)) {
                $peakTime = array_search(max($timeDist), $timeDist);
                $summary[] = "Most productive during: {$peakTime}";
            }

            if (isset($taskPatterns['overdue_rate'])) {
                $summary[] = "Task completion rate: " . round(100 - $taskPatterns['overdue_rate'], 1) . "% on time";
            }
        }

        if (!empty($reminderPatterns)) {
            $summary[] = "Reminder response rate: " . round(100 - ($reminderPatterns['snooze_rate'] ?? 0), 1) . "%";
        }

        if (!empty($chatPatterns)) {
            $summary[] = "AI interaction frequency: {$chatPatterns['total_chats']} chats in last 30 days";
        }

        return $summary;
    }
}

