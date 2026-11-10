<?php

namespace App\Services\AI\Agent\Tools;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\AgentSession;
use App\Services\AI\Agent\Api\ApiClient;
use App\Services\AI\Agent\Api\ApiResponse;
use Carbon\CarbonImmutable;

/**
 * Runs a registry tool as the signed-in user, through the API client.
 *
 * Guards, in order:
 *  1. Unknown or missing parameters, or values of the wrong type, are refused.
 *  2. The user must hold one of the tool's permissions (from GET /auth/me).
 *  3. A tool that changes data runs only for an action the user confirmed and
 *     that this request claimed (status "executing"); anything else throws.
 * The API itself then applies its own auth, tenancy, permission and validation.
 */
class ToolExecutor
{
    public function __construct(private ApiClient $api, private ToolRegistry $registry)
    {
    }

    public function registry(): ToolRegistry
    {
        return $this->registry;
    }

    public function can(AgentSession $session, string $tool): bool
    {
        return $session->hasAnyPermission($this->registry->get($tool)->permissions);
    }

    public function run(AgentSession $session, string $toolName, array $params = [], ?AiAgentAction $claimed = null): ApiResponse
    {
        $tool = $this->registry->get($toolName);

        if ($tool->requiresConfirmation || $tool->isMutation()) {
            if (!$claimed || $claimed->status !== AiAgentAction::EXECUTING || $claimed->tool !== $tool->name
                || $claimed->user_id !== $session->userId || $claimed->tenant_id !== $session->tenantId) {
                throw new \LogicException("Tool [{$tool->name}] changes data and needs a confirmed action");
            }
        }

        $params = $this->validate($tool, $params);

        if (!$session->hasAnyPermission($tool->permissions)) {
            throw new ToolForbidden($tool);
        }

        $path = $tool->path;
        foreach ($tool->pathParams() as $name) {
            $path = str_replace('{' . $name . '}', rawurlencode((string) $params[$name]), $path);
            unset($params[$name]);
        }

        $payload = [];
        foreach ($params as $name => $value) {
            data_set($payload, $tool->params[$name]['as'] ?? $name, $value);
        }
        foreach ($tool->fixed as $name => $value) {
            data_set($payload, $name, $value);
        }

        $headers = $claimed ? ['Idempotency-Key' => $claimed->idempotency_key] : [];

        return $tool->method === 'GET'
            ? $this->api->send($session->apiContext, 'GET', $path, $payload, null, $headers)
            : $this->api->send($session->apiContext, $tool->method, $path, [], $payload, $headers);
    }

    private function validate(ToolDefinition $tool, array $params): array
    {
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');

        $unknown = array_diff(array_keys($params), array_keys($tool->params));
        if ($unknown !== []) {
            throw new \InvalidArgumentException("Unknown parameter(s) for [{$tool->name}]: " . implode(', ', $unknown));
        }

        foreach ($tool->required() as $name) {
            if (!array_key_exists($name, $params)) {
                throw new \InvalidArgumentException("Missing parameter [{$name}] for [{$tool->name}]");
            }
        }

        foreach ($params as $name => $value) {
            $type = $tool->params[$name]['type'] ?? 'string';
            $params[$name] = match ($type) {
                'int' => $this->int($name, $value),
                'bool' => (bool) $value,
                'date' => $this->date($name, $value),
                'time' => $this->time($name, $value),
                default => $this->string($name, $value),
            };
        }

        return $params;
    }

    private function int(string $name, mixed $value): int
    {
        if (!is_numeric($value) || (int) $value != $value) {
            throw new \InvalidArgumentException("[{$name}] must be a whole number");
        }

        return (int) $value;
    }

    private function date(string $name, mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new \InvalidArgumentException("[{$name}] must be a Y-m-d date");
        }
        CarbonImmutable::createFromFormat('Y-m-d', $value);

        return $value;
    }

    private function time(string $name, mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value)) {
            throw new \InvalidArgumentException("[{$name}] must be a H:i:s time");
        }

        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private function string(string $name, mixed $value): string
    {
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException("[{$name}] must be text");
        }

        return mb_substr((string) $value, 0, 255);
    }
}
