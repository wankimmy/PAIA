<?php

namespace App\Services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class WebPushService
{
    protected WebPush $webPush;

    public function __construct()
    {
        $publicKey = env('VAPID_PUBLIC_KEY');
        $privateKey = env('VAPID_PRIVATE_KEY');
        $subject = env('VAPID_SUBJECT', 'mailto:admin@example.com');

        if (!$publicKey || !$privateKey) {
            throw new \Exception('VAPID keys not configured. Please set VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY in .env');
        }

        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);
    }

    public function sendNotification(string $endpoint, string $p256dh, string $auth, array $payload): void
    {
        $subscription = Subscription::create([
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => $p256dh,
                'auth' => $auth,
            ],
        ]);

        $this->webPush->queueNotification(
            $subscription,
            json_encode($payload)
        );

        // Flush notifications
        foreach ($this->webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                throw new \Exception('Failed to send notification: ' . $report->getReason());
            }
        }
    }
}

