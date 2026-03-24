<?php

namespace App\Services\AI\Tools;

interface AIToolInterface
{
    /**
     * Get the tool name identifier.
     */
    public function name(): string;

    /**
     * Get a human-readable description of what this tool does.
     */
    public function description(): string;

    /**
     * Execute the tool with the given parameters and return context text.
     *
     * @param array $params Parameters including 'date_range', 'entities', etc.
     * @return string Formatted context text for the LLM
     */
    public function execute(array $params): string;
}
