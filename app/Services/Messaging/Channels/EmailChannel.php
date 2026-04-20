<?php

namespace App\Services\Messaging\Channels;

use App\Services\Messaging\Contracts\ChannelDriver;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder for future email channel implementation.
 */
class EmailChannel implements ChannelDriver
{
    public function channel(): string
    {
        return 'email';
    }

    public function send(string $to, string $body, array $options = []): array
    {
        // Future: integrate with Laravel Mail (Postmark/SES/Resend)
        Log::info('EmailChannel: send not yet implemented', ['to' => $to]);

        return [
            'success' => false,
            'external_id' => null,
            'error' => 'Email channel not yet implemented',
        ];
    }
}
