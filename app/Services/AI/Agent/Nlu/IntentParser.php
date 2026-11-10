<?php

namespace App\Services\AI\Agent\Nlu;

interface IntentParser
{
    /**
     * @param array $context today's date, what the assistant is waiting for,
     *                       the patient being discussed and recent turns
     */
    public function parse(string $message, array $context): ParsedMessage;
}
