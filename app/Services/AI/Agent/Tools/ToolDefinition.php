<?php

namespace App\Services\AI\Agent\Tools;

/**
 * One API operation the agent may use.
 *
 * $params maps an input name to its rule: type (int|string|date|time|bool),
 * whether it is required, and where it goes ("as": a query/body key such as
 * "filter.patient_id"; path params are the {placeholders} in $path).
 * $fixed values are always sent and cannot be overridden.
 * $permissions: the user needs at least one of them (empty: any signed-in user).
 */
final class ToolDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $method,
        public readonly string $path,
        public readonly array $params = [],
        public readonly array $fixed = [],
        public readonly array $permissions = [],
        public readonly bool $requiresConfirmation = false,
        public readonly string $operation = '',
    ) {
    }

    public function isMutation(): bool
    {
        return $this->method !== 'GET';
    }

    /** @return list<string> */
    public function pathParams(): array
    {
        preg_match_all('/\{(\w+)\}/', $this->path, $m);

        return $m[1];
    }

    /** @return list<string> */
    public function required(): array
    {
        return array_keys(array_filter($this->params, fn ($rule) => $rule['required'] ?? false));
    }
}
