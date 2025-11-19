<?php

namespace App\Console\Commands;

use App\Models\MeetingReminder;
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

        $sentCount = 0;

        // Get task reminders that are due and not yet sent
        $taskReminders = Reminder::where('remind_at', '<=', now())
            ->whereNull('sent_at')
            ->with(['task.user.pushSubscriptions'])
            ->get();

        foreach ($taskReminders as $reminder) {
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

        // Get meeting reminders that are due and not yet sent
        $meetingReminders = MeetingReminder::where('remind_at', '<=', now())
            ->whereNull('sent_at')
            ->with(['meeting.user.pushSubscriptions'])
            ->get();

        foreach ($meetingReminders as $reminder) {
            $user = $reminder->meeting->user;
            $subscriptions = $user->pushSubscriptions;

            if ($subscriptions->isEmpty()) {
                continue;
            }

            $meeting = $reminder->meeting;
            $startTime = $meeting->start_time->format('M d, Y g:i A');
            $location = $meeting->location ? " at {$meeting->location}" : '';

            $payload = [
                'title' => 'Meeting Reminder: ' . $meeting->title,
                'body' => "Starts at {$startTime}{$location}" . ($meeting->description ? "\n{$meeting->description}" : ''),
                'tag' => 'meeting-reminder-' . $reminder->id,
                'data' => [
                    'meeting_id' => $meeting->id,
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
                    $this->error("Failed to send meeting reminder {$reminder->id} to subscription: " . $e->getMessage());
                }
            }

            // Mark as sent
            $reminder->update(['sent_at' => now()]);
            
            // Record interaction
            $memoryService->recordInteraction($user, 'meeting_reminder_fired', [
                'reminder_id' => $reminder->id,
                'meeting_id' => $reminder->meeting_id,
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
