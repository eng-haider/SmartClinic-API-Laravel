<?php

namespace App\Services\Messaging;

use App\Models\MessagingSetting;
use App\Services\Messaging\Channels\EmailChannel;
use App\Services\Messaging\Channels\GupshupChannel;
use App\Services\Messaging\Channels\PushChannel;
use App\Services\Messaging\Channels\WhatsAppChannel;
use App\Services\Messaging\Contracts\ChannelDriver;

class ChannelManager
{
    /** @var array<string, ChannelDriver> */
    private array $drivers = [];

    public function driver(string $channel): ChannelDriver
    {
        // WhatsApp settings belong to the clinic, and this singleton outlives a tenant
        // in queue workers, so the WhatsApp driver is never cached.
        if ($channel === 'whatsapp') {
            return $this->whatsAppDriver();
        }

        if (!isset($this->drivers[$channel])) {
            $this->drivers[$channel] = $this->resolveDriver($channel);
        }

        return $this->drivers[$channel];
    }

    private function resolveDriver(string $channel): ChannelDriver
    {
        return match ($channel) {
            'email' => new EmailChannel(),
            'push' => new PushChannel(),
            default => throw new \InvalidArgumentException("Unsupported messaging channel: {$channel}"),
        };
    }

    private function whatsAppDriver(): ChannelDriver
    {
        $settings = MessagingSetting::active()->forProvider('whatsapp')->first();

        return $settings?->usesGupshup() ? new GupshupChannel($settings) : new WhatsAppChannel($settings);
    }
}
