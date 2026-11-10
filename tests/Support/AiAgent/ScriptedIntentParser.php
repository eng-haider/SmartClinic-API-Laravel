<?php

namespace Tests\Support\AiAgent;

use App\Services\AI\Agent\Nlu\Intent;
use App\Services\AI\Agent\Nlu\IntentParser;
use App\Services\AI\Agent\Nlu\ParsedMessage;

/**
 * Stands in for the LLM: returns what the model is instructed to extract for
 * each scripted message (the user's own words per detail). Everything after
 * that, from date and amount reading to confirmation, is the real agent.
 */
class ScriptedIntentParser implements IntentParser
{
    public array $calls = [];
    public bool $available = true;
    private array $script = [];

    public function on(string $message, string $intent, array $entities = [], bool $injection = false, ?string $language = null): static
    {
        $this->script[$message] = new ParsedMessage($intent, $entities, $language, true, $injection);

        return $this;
    }

    public function parse(string $message, array $context): ParsedMessage
    {
        $this->calls[] = ['message' => $message, 'context' => $context];

        if (!$this->available) {
            return ParsedMessage::unavailable();
        }

        return $this->script[$message] ?? new ParsedMessage(Intent::UNKNOWN);
    }
}
