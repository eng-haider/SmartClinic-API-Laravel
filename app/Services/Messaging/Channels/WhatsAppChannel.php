<?php

namespace App\Services\Messaging\Channels;

use App\Models\MessagingSetting;
use App\Services\Messaging\Contracts\ChannelDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel implements ChannelDriver
{
    private ?MessagingSetting $settings = null;

    public function channel(): string
    {
        return 'whatsapp';
    }

    public function send(string $to, string $body, array $options = []): array
    {
        $settings = $this->getSettings();

        if (!$settings) {
            return [
                'success' => false,
                'external_id' => null,
                'error' => 'WhatsApp messaging settings not configured or inactive',
            ];
        }

        try {
            // If a template is specified, send template message
            if (!empty($options['template_name'])) {
                return $this->sendTemplate($to, $options, $settings);
            }

            // Otherwise send a plain text message
            return $this->sendText($to, $body, $settings);
        } catch (\Throwable $e) {
            Log::error('WhatsApp send failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'external_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function sendText(string $to, string $body, MessagingSetting $settings): array
    {
        $response = Http::withToken($settings->whatsapp_access_token)
            ->post("https://graph.facebook.com/v21.0/{$settings->whatsapp_phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizePhone($to),
                'type' => 'text',
                'text' => ['body' => $body],
            ]);

        if ($response->successful()) {
            $messageId = $response->json('messages.0.id');
            return [
                'success' => true,
                'external_id' => $messageId,
                'error' => null,
            ];
        }

        $error = $response->json('error.message', 'Unknown WhatsApp API error');
        return [
            'success' => false,
            'external_id' => null,
            'error' => $error,
        ];
    }

    private function sendTemplate(string $to, array $options, MessagingSetting $settings): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($to),
            'type' => 'template',
            'template' => [
                'name' => $options['template_name'],
                'language' => ['code' => $options['language'] ?? 'ar'],
            ],
        ];

        // Add template components (parameters) if provided
        if (!empty($options['template_params'])) {
            $components = [];
            $parameters = [];
            foreach ($options['template_params'] as $value) {
                $parameters[] = [
                    'type' => 'text',
                    'text' => (string) $value,
                ];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters,
            ];
            $payload['template']['components'] = $components;
        }

        $response = Http::withToken($settings->whatsapp_access_token)
            ->post("https://graph.facebook.com/v21.0/{$settings->whatsapp_phone_number_id}/messages", $payload);

        if ($response->successful()) {
            $messageId = $response->json('messages.0.id');
            return [
                'success' => true,
                'external_id' => $messageId,
                'error' => null,
            ];
        }

        $error = $response->json('error.message', 'Unknown WhatsApp API error');
        return [
            'success' => false,
            'external_id' => null,
            'error' => $error,
        ];
    }

    private function getSettings(): ?MessagingSetting
    {
        if ($this->settings === null) {
            $this->settings = MessagingSetting::active()
                ->forProvider('whatsapp')
                ->first();
        }

        return $this->settings;
    }

    private function normalizePhone(string $phone): string
    {
        // Remove spaces, dashes, parentheses
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);

        // Ensure it starts with country code (no +)
        return ltrim($phone, '+');
    }
}
