<?php

namespace App\Services\Messaging;

use App\Models\AutomationTarget;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function __construct(
        private ChannelManager $channelManager,
        private TemplateEngine $templateEngine,
    ) {}

    /**
     * Send a message for an automation target.
     */
    public function sendForTarget(AutomationTarget $target): Message
    {
        $target->loadMissing(['rule', 'patient']);

        $patient = $target->patient;
        $rule = $target->rule;
        $channel = $rule->channel;

        // Render the message body from template
        $body = $this->templateEngine->renderForTarget($target);

        // Get or create conversation
        $conversation = Conversation::findOrCreateForPatient($patient, $channel);

        // Create the message record
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'channel' => $channel,
            'to_number' => $patient->phone,
            'body' => $body,
            'template_key' => $rule->template_key,
            'template_params' => $this->templateEngine->buildVariables($target),
            'status' => Message::STATUS_QUEUED,
        ]);

        // Send via channel driver
        $driver = $this->channelManager->driver($channel);
        $result = $driver->send($patient->phone, $body, [
            'template_name' => $rule->template_key,
            'template_params' => array_values($this->templateEngine->buildVariables($target)),
        ]);

        if ($result['success']) {
            $message->update([
                'status' => Message::STATUS_SENT,
                'external_id' => $result['external_id'],
            ]);
            $target->markSent($message->id);
        } else {
            $message->update([
                'status' => Message::STATUS_FAILED,
                'error_message' => $result['error'],
            ]);
            $target->markFailed($result['error']);
        }

        // Update conversation timestamp
        $conversation->update(['last_message_at' => now()]);

        return $message;
    }

    /**
     * Send a direct message (not tied to automation).
     */
    public function sendDirect(
        string $toNumber,
        string $body,
        string $channel = 'whatsapp',
        ?int $conversationId = null,
        array $options = []
    ): Message {
        $message = Message::create([
            'conversation_id' => $conversationId,
            'direction' => Message::DIRECTION_OUTBOUND,
            'channel' => $channel,
            'to_number' => $toNumber,
            'body' => $body,
            'status' => Message::STATUS_QUEUED,
            'meta' => $options['meta'] ?? null,
        ]);

        $driver = $this->channelManager->driver($channel);
        $result = $driver->send($toNumber, $body, $options);

        if ($result['success']) {
            $message->update([
                'status' => Message::STATUS_SENT,
                'external_id' => $result['external_id'],
            ]);
        } else {
            $message->update([
                'status' => Message::STATUS_FAILED,
                'error_message' => $result['error'],
            ]);
        }

        return $message;
    }

    /**
     * Record an inbound message from a webhook.
     */
    public function recordInbound(array $data): Message
    {
        return Message::create([
            'conversation_id' => $data['conversation_id'] ?? null,
            'direction' => Message::DIRECTION_INBOUND,
            'channel' => $data['channel'] ?? 'whatsapp',
            'from_number' => $data['from_number'],
            'body' => $data['body'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'status' => Message::STATUS_DELIVERED,
            'meta' => $data['meta'] ?? null,
        ]);
    }
}
