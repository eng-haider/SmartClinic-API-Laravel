<?php

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessagingSetting;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\WebhookLog;
use App\Repositories\PatientRepository;
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
                ->orWhere('phone2', $fromNumber)
                ->orWhere('phone2', '+' . $fromNumber)
                ->first();

            $this->recordInbound($patient, $fromNumber, $body, $messageId, $msg);
        }
    }

    /**
     * Record an inbound WhatsApp message, in the patient's conversation when known.
     */
    private function recordInbound(?Patient $patient, string $fromNumber, ?string $body, ?string $messageId, array $meta): void
    {
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
            'meta' => $meta,
        ]);
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

            $error = null;

            if ($status === 'failed') {
                $error = $statusUpdate['errors'][0]['message'] ?? 'Unknown error';
            }

            $this->updateMessageStatus($externalId, $status, $error);
        }
    }

    /**
     * Apply a delivery status to the outbound message sent with this provider id.
     * Providers do not guarantee event order, so a late "delivered" never
     * overwrites "read"; a failure always applies.
     */
    private function updateMessageStatus(string $externalId, string $status, ?string $error): void
    {
        $message = Message::where('external_id', $externalId)->first();

        if (!$message) {
            return;
        }

        $statusMap = [
            'sent' => Message::STATUS_SENT,
            'delivered' => Message::STATUS_DELIVERED,
            'read' => Message::STATUS_READ,
            'failed' => Message::STATUS_FAILED,
        ];
        $progress = [Message::STATUS_SENT => 1, Message::STATUS_DELIVERED => 2, Message::STATUS_READ => 3];

        $mappedStatus = $statusMap[$status] ?? $status;

        if (($progress[$mappedStatus] ?? 0) > 0 && ($progress[$message->status] ?? 0) >= $progress[$mappedStatus]) {
            return;
        }

        $message->updateStatus($mappedStatus, $error);
    }

    /**
     * Handle a Gupshup webhook (Gupshup's V2 format). Gupshup signs nothing, so
     * the callback URL names the clinic and carries its secret token. Returns
     * false when they do not match an active Gupshup setup.
     */
    public function handleGupshup(string $tenantId, string $token, array $payload): bool
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return false;
        }

        return $tenant->run(function () use ($token, $payload) {
            $setting = MessagingSetting::active()->forProvider('whatsapp')->first();
            $secret = (string) $setting?->whatsapp_webhook_verify_token;

            if (!$setting?->usesGupshup() || $secret === '' || !hash_equals($secret, $token)) {
                return false;
            }

            $log = WebhookLog::create([
                'source' => 'gupshup',
                'event_type' => $payload['type'] ?? 'unknown',
                'payload' => $payload,
                'status' => 'received',
            ]);

            try {
                $event = $payload['payload'] ?? [];

                // Other types (user-event such as opt-ins and sandbox-start, billing-event) are only logged
                match ($payload['type'] ?? null) {
                    'message' => $this->processGupshupInbound($event),
                    'message-event' => $this->processGupshupStatus($event),
                    default => null,
                };

                $log->markProcessed();
            } catch (\Throwable $e) {
                Log::error('Gupshup webhook processing failed', ['error' => $e->getMessage()]);
                $log->markFailed($e->getMessage());
            }

            return true;
        });
    }

    private function processGupshupInbound(array $event): void
    {
        $fromNumber = $event['source'] ?? null;

        if (!$fromNumber) {
            return;
        }

        // Text messages and quick-reply button taps both carry their text here
        $body = $event['payload']['text'] ?? null;
        $patient = app(PatientRepository::class)->findByPhoneNumber($fromNumber);

        $this->recordInbound($patient, $fromNumber, $body, $event['id'] ?? null, $event);
    }

    private function processGupshupStatus(array $event): void
    {
        $status = $event['type'] ?? null;

        // The send API returned Gupshup's id. Sent/delivered/read events carry it in
        // gsId (their id is WhatsApp's); enqueued and failed carry it in id.
        $externalId = $event['gsId'] ?? $event['id'] ?? null;

        if (!$externalId || !in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        $error = $status === 'failed' ? ($event['payload']['reason'] ?? 'Unknown error') : null;

        $this->updateMessageStatus($externalId, $status, $error);
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
