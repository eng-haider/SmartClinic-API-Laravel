<?php

namespace App\Services\AI\Agent\Nlu;

/**
 * What the LLM understood from one message. Entities hold the user's own words
 * ("باچر", "50 ألف"); the agent turns them into exact values itself.
 */
final class ParsedMessage
{
    public const ENTITY_KEYS = [
        'patient_name', 'patient_phone', 'patient_ref', 'doctor_name',
        'date_text', 'time_text', 'date', 'time',
        'new_date_text', 'new_time_text',
        'amount_text', 'amount', 'currency', 'pay_remaining', 'invoice_ref',
        'selection', 'period', 'name', 'phone', 'age', 'notes',
    ];

    public function __construct(
        public readonly string $intent,
        public readonly array $entities = [],
        public readonly ?string $language = null,
        public readonly bool $llmAvailable = true,
        public readonly bool $injectionAttempt = false,
    ) {
    }

    public static function unavailable(): self
    {
        return new self(Intent::UNKNOWN, [], null, false);
    }

    public function entity(string $key, mixed $default = null): mixed
    {
        $value = $this->entities[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }
}
