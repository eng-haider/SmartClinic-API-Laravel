<?php

namespace App\Services\AI\Agent\Api;

/**
 * The only way the agent touches clinic data: an authenticated request to the
 * clinic REST API (paths relative to /api/tenant).
 */
interface ApiClient
{
    public function send(
        ApiContext $context,
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        array $headers = [],
    ): ApiResponse;
}
