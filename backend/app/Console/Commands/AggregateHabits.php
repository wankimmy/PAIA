<?php

namespace App\Console\Commands;

use App\Models\AiInteraction;
use App\Models\User;
use App\Services\AiMemoryService;
use App\Services\BehaviorAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateHabits extends Command
{
    protected $signature = 'habits:aggregate';
    protected $description = 'Aggregate user behavior patterns into AI memories';

    protected AiMemoryService $memoryService;
    protected BehaviorAnalysisService $behaviorAnalysis;

    public function __construct(AiMemoryService $memoryService, BehaviorAnalysisService $behaviorAnalysis)
    {
        parent::__construct();
        $this->memoryService = $memoryService;
        $this->behaviorAnalysis = $behaviorAnalysis;
    }

    public function handle(): int
    {
        $users = User::all();
        $aggregated = 0;

        foreach ($users as $user) {
            $insights = $this->behaviorAnalysis->generateInsights($user);

            foreach ($insights as $insight) {
                try {
                    $this->memoryService->updateOrCreateMemory(
                        $user,
                        $insight['category'],
                        $insight['key'],
                        $insight['value'],
                        $insight['importance'],
                        'system'
                    );
                    $aggregated++;
                } catch (\Exception $e) {
                    // Skip if error
                    continue;
                }
            }

            // Advanced pattern recognition
            $this->recognizeAdvancedPatterns($user);
        }

        $this->info("Aggregated habits and patterns for {$aggregated} user(s)");
        return Command::SUCCESS;
    }

    protected function recognizeAdvancedPatterns(User $user): void
    {
        // Analyze task completion patterns
        $taskPatterns = $this->behaviorAnalysis->analyzeTaskCompletionPatterns($user);
        
        if (!empty($taskPatterns)) {
            // Tag-based patterns
            if (!empty($taskPatterns['tag_patterns'])) {
                $topTag = array_key_first($taskPatterns['tag_patterns']);
                $tagCount = $taskPatterns['tag_patterns'][$topTag];
                
                if ($tagCount >= 5) {
                    try {
                        $this->memoryService->updateOrCreateMemory(
                            $user,
                            'preference',
                            "frequently_uses_tag_{$topTag}",
                            "You frequently work on tasks tagged '{$topTag}'.",
                            3,
                            'system'
                        );
                    } catch (\Exception $e) {
                        // Skip
                    }
                }
            }

            // Consistency patterns
            if (!empty($taskPatterns['completion_consistency'])) {
                $mostActiveDay = $taskPatterns['completion_consistency']['most_active_day'] ?? null;
                if ($mostActiveDay) {
                    try {
                        $this->memoryService->updateOrCreateMemory(
                            $user,
                            'habit',
                            'most_productive_day',
                            "You're most productive on {$mostActiveDay}s.",
                            3,
                            'system'
                        );
                    } catch (\Exception $e) {
                        // Skip
                    }
                }
            }
        }

        // Analyze chat patterns for preferences
        $chatPatterns = $this->behaviorAnalysis->analyzeChatPatterns($user);
        
        if (!empty($chatPatterns) && $chatPatterns['avg_message_length'] > 100) {
            try {
                $this->memoryService->updateOrCreateMemory(
                    $user,
                    'preference',
                    'prefers_detailed_communication',
                    'You tend to provide detailed information when chatting, indicating you prefer thorough communication.',
                    3,
                    'system'
                );
            } catch (\Exception $e) {
                // Skip
            }
        }
    }

    protected function getTimeOfDay(float $hour): string
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

