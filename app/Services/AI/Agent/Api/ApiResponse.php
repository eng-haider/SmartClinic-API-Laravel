<?php

namespace App\Services\AI\Agent\Api;

/**
 * The result of one API call. A status of 0 means no HTTP answer came back
 * (timeout or connection failure), which for a write means "outcome unknown".
 */
final class ApiResponse
{
    public function __construct(
        public readonly int $status,
        public readonly ?array $body,
        public readonly bool $timedOut = false,
        public readonly ?string $transportError = null,
    ) {
    }

    public static function networkFailure(string $error, bool $timedOut = true): self
    {
        return new self(0, null, $timedOut, $error);
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300 && ($this->body['success'] ?? true) !== false;
    }

    public function noAnswer(): bool
    {
        return $this->status === 0;
    }

    public function forbidden(): bool
    {
        return in_array($this->status, [401, 403], true);
    }

    public function notFound(): bool
    {
        return $this->status === 404;
    }

    public function invalid(): bool
    {
        return $this->status === 422;
    }

    public function serverError(): bool
    {
        return $this->status >= 500 || $this->status === 0;
    }

    public function data(mixed $default = null): mixed
    {
        return $this->body['data'] ?? $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->body, $key, $default);
    }

    /** The API's own error text, first validation error included. */
    public function message(): string
    {
        $errors = $this->body['errors'] ?? null;
        if (is_array($errors)) {
            $first = collect($errors)->flatten()->first();
            if ($first) {
                return (string) $first;
            }
        }

        return (string) ($this->body['message'] ?? $this->transportError ?? 'Unknown error');
    }
}
