<?php

namespace App\Services\Messaging;

use App\Services\Messaging\Channels\EmailChannel;
use App\Services\Messaging\Channels\PushChannel;
use App\Services\Messaging\Channels\WhatsAppChannel;
use App\Services\Messaging\Contracts\ChannelDriver;

class ChannelManager
{
    /** @var array<string, ChannelDriver> */
    private array $drivers = [];

    public function driver(string $channel): ChannelDriver
    {
        if (!isset($this->drivers[$channel])) {
            $this->drivers[$channel] = $this->resolveDriver($channel);
        }

        return $this->drivers[$channel];
    }

    private function resolveDriver(string $channel): ChannelDriver
    {
        return match ($channel) {
            'whatsapp' => new WhatsAppChannel(),
            'email' => new EmailChannel(),
            'push' => new PushChannel(),
            default => throw new \InvalidArgumentException("Unsupported messaging channel: {$channel}"),
        };
    }
}
