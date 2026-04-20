<?php

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessagingSetting;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    public function __construct(
        private MessageService $messageService,
    ) {}

    /**
     * Verify webhook (GET request from WhatsApp).
     */
    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        // Find tenant by verify token across all tenants
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $match = $tenant->run(function () use ($token) {
                return MessagingSetting::where('whatsapp_webhook_verify_token', $token)
                    ->active()
                    ->exists();
            });

            if ($match && $mode === 'subscribe') {
                return $challenge;
            }
        }

        return null;
    }

    /**
     * Handle incoming WhatsApp webhook payload.
     */
    public function handleWhatsApp(array $payload): void
    {
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                if (!$phoneNumberId) {
                    continue;
                }

                // Resolve tenant from phone_number_id
                $tenant = $this->resolveTenantByPhoneNumberId($phoneNumberId);

                if (!$tenant) {
                    Log::warning('Webhook: could not resolve tenant for phone_number_id', [
                        'phone_number_id' => $phoneNumberId,
                    ]);
                    continue;
                }

                $tenant->run(function () use ($value, $payload) {
                    // Log the webhook
                    $log = WebhookLog::create([
                        'source' => 'whatsapp',
                        'event_type' => $this->detectEventType($value),
                        'payload' => $payload,
                        'status' => 'received',
                    ]);

                    try {
                        // Handle inbound messages
                        $this->processInboundMessages($value);

                        // Handle status updates
                        $this->processStatusUpdates($value);

                        $log->markProcessed();
                    } catch (\Throwable $e) {
                        Log::error('Webhook processing failed', ['error' => $e->getMessage()]);
                        $log->markFailed($e->getMessage());
                    }
                });
            }
        }
    }

    /**
     * Resolve tenant by WhatsApp phone_number_id.
     */
    private function resolveTenantByPhoneNumberId(string $phoneNumberId): ?Tenant
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $found = $tenant->run(function () use ($phoneNumberId) {
                return MessagingSetting::where('whatsapp_phone_number_id', $phoneNumberId)
                    ->active()
                    ->exists();
            });

            if ($found) {
                return $tenant;
            }
        }

        return null;
    }

    /**
     * Process inbound messages from webhook payload.
     */
    private function processInboundMessages(array $value): void
    {
        $messages = $value['messages'] ?? [];

        foreach ($messages as $msg) {
            $fromNumber = $msg['from'] ?? null;
            $body = $msg['text']['body'] ?? null;
            $messageId = $msg['id'] ?? null;

            if (!$fromNumber) {
                continue;
            }

            // Try to find patient by phone number
            $patient = Patient::where('phone', $fromNumber)
                ->orWhere('phone', '+' . $fromNumber)
                ->first();

            $conversationId = null;
            if ($patient) {
                $conversation = Conversation::findOrCreateForPatient($patient, 'whatsapp');
                $conversationId = $conversation->id;
                $conversation->update(['last_message_at' => now()]);
            }

            $this->messageService->recordInbound([
                'conversation_id' => $conversationId,
                'channel' => 'whatsapp',
                'from_number' => $fromNumber,
                'body' => $body,
                'external_id' => $messageId,
                'meta' => $msg,
            ]);
        }
    }

    /**
     * Process message status updates from webhook payload.
     */
    private function processStatusUpdates(array $value): void
    {
        $statuses = $value['statuses'] ?? [];

        foreach ($statuses as $statusUpdate) {
            $externalId = $statusUpdate['id'] ?? null;
            $status = $statusUpdate['status'] ?? null;

            if (!$externalId || !$status) {
                continue;
            }

            $message = Message::where('external_id', $externalId)->first();

            if ($message) {
                $statusMap = [
                    'sent' => Message::STATUS_SENT,
                    'delivered' => Message::STATUS_DELIVERED,
                    'read' => Message::STATUS_READ,
                    'failed' => Message::STATUS_FAILED,
                ];

                $mappedStatus = $statusMap[$status] ?? $status;
                $error = null;

                if ($status === 'failed') {
                    $error = $statusUpdate['errors'][0]['message'] ?? 'Unknown error';
                }

                $message->updateStatus($mappedStatus, $error);
            }
        }
    }

    private function detectEventType(array $value): string
    {
        if (!empty($value['messages'])) {
            return 'message';
        }
        if (!empty($value['statuses'])) {
            return 'status';
        }
        return 'unknown';
    }
}
