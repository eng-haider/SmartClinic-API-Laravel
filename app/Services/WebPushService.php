<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Free browser push notifications: standard Web Push with VAPID keys, no third-party account.
 *
 * Browsers subscribe through POST notifications/push/subscribe. Every notification created by
 * NotificationService is then pushed to each of the recipient's browsers, where the service
 * worker (frontend public/push-sw.js) shows it as a system notification and tells open tabs to
 * refresh the bell. The payload is encrypted for the browser, so the push service cannot read it.
 *
 * Keys: VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY / VAPID_SUBJECT in .env. Generate a pair with
 *   php -r "require 'vendor/autoload.php'; print_r(Minishlink\WebPush\VAPID::createVapidKeys());"
 * Changing the keys invalidates every existing subscription (browsers re-subscribe on next visit).
 */
class WebPushService
{
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('services.webpush.public_key');
    }

    /**
     * Push a stored notification to every browser the user subscribed. Subscriptions the push
     * service reports as gone (browser unsubscribed or expired) are deleted.
     */
    public function sendToUser(User $user, Notification $notification): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $user->id)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        // Short timeouts: this can run inside a request, so a slow push service must not hang it
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ], ['TTL' => 86400, 'urgency' => 'high'], new Client(['timeout' => 5, 'connect_timeout' => 3]));
        $webPush->setReuseVAPIDHeaders(true);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                json_encode($this->payload($notification, $subscription->locale), JSON_UNESCAPED_UNICODE)
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', PushSubscription::hashEndpoint($report->getEndpoint()))->delete();
            } elseif (!$report->isSuccess()) {
                Log::warning('Web push failed', [
                    'notification_id' => $notification->id,
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }

    /**
     * What the service worker receives. Arabic browsers get the Arabic copy when the
     * notification carries one (data.title_ar / data.body_ar).
     */
    private function payload(Notification $notification, ?string $locale): array
    {
        $data = $notification->data ?? [];
        $arabic = str_starts_with((string) $locale, 'ar');

        return [
            'notification_id' => $notification->id,
            'type' => $notification->type,
            'title' => ($arabic ? ($data['title_ar'] ?? null) : null) ?? $notification->title,
            'body' => ($arabic ? ($data['body_ar'] ?? null) : null) ?? $notification->body,
            'url' => $notification->action_url,
        ];
    }
}
