<?php

namespace App\Services\Messaging\Contracts;

interface ChannelDriver
{
    /**
     * Send a message through this channel.
     *
     * @param string $to Recipient identifier (phone number, email, etc.)
     * @param string $body Rendered message body
     * @param array $options Channel-specific options (template_key, template_params, etc.)
     * @return array ['success' => bool, 'external_id' => ?string, 'error' => ?string]
     */
    public function send(string $to, string $body, array $options = []): array;

    /**
     * Get the channel name.
     */
    public function channel(): string;
}
