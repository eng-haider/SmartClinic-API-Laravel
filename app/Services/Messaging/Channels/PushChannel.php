<?php

namespace App\Services\Messaging\Channels;

use App\Services\Messaging\Contracts\ChannelDriver;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder for future push notification channel.
 */
class PushChannel implements ChannelDriver
{
    public function channel(): string
    {
        return 'push';
    }

    public function send(string $to, string $body, array $options = []): array
    {
        // Future: integrate with OneSignal or Firebase
        Log::info('PushChannel: send not yet implemented', ['to' => $to]);

        return [
            'success' => false,
            'external_id' => null,
            'error' => 'Push channel not yet implemented',
        ];
    }
}
