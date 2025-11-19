<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\AiMemoryService;
use App\Services\WebPushService;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'Send push notifications for due reminders';

    public function handle(): int
    {
        $webPush = app(WebPushService::class);
        $memoryService = app(AiMemoryService::class);

        // Get reminders that are due and not yet sent
        $reminders = Reminder::where('remind_at', '<=', now())
            ->whereNull('sent_at')
            ->with(['task.user.pushSubscriptions'])
            ->get();

        $sentCount = 0;

        foreach ($reminders as $reminder) {
            $user = $reminder->task->user;
            $subscriptions = $user->pushSubscriptions;

            if ($subscriptions->isEmpty()) {
                continue;
            }

            $payload = [
                'title' => 'Reminder: ' . $reminder->task->title,
                'body' => $reminder->task->description ?? 'You have a reminder',
                'tag' => 'reminder-' . $reminder->id,
                'data' => [
                    'task_id' => $reminder->task->id,
                    'reminder_id' => $reminder->id,
                ],
            ];

            // Send to all user's subscriptions
            foreach ($subscriptions as $subscription) {
                try {
                    $webPush->sendNotification(
                        $subscription->endpoint,
                        $subscription->p256dh,
                        $subscription->auth,
                        $payload
                    );
                } catch (\Exception $e) {
                    $this->error("Failed to send reminder {$reminder->id} to subscription: " . $e->getMessage());
                    // Optionally remove invalid subscription
                    // $subscription->delete();
                }
            }

            // Mark as sent
            $reminder->update(['sent_at' => now()]);
            
            // Record interaction
            $memoryService->recordInteraction($user, 'reminder_fired', [
                'reminder_id' => $reminder->id,
                'task_id' => $reminder->task_id,
                'remind_at_hour' => $reminder->remind_at->hour,
            ]);
            
            $sentCount++;
        }

        if ($sentCount > 0) {
            $this->info("Sent {$sentCount} reminder(s)");
        }

        return Command::SUCCESS;
    }
}
