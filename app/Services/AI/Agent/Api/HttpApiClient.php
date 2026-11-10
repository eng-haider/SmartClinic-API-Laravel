<?php

namespace App\Services\AI\Agent\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sends the API request over the network to AI_AGENT_API_BASE_URL. A timeout
 * comes back as "no answer" so a write can be checked before any retry.
 */
class HttpApiClient implements ApiClient
{
    public function send(
        ApiContext $context,
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        array $headers = [],
    ): ApiResponse {
        $url = rtrim((string) config('ai_agent.api_base_url'), '/')
            . '/' . trim(config('ai_agent.api_prefix', 'api/tenant'), '/')
            . '/' . ltrim($path, '/');

        $request = Http::acceptJson()
            ->timeout((int) config('ai_agent.api_timeout', 15))
            ->withHeaders(array_merge([
                'X-Tenant-ID' => $context->tenantId,
                'Accept-Language' => $context->locale,
                'X-Requested-By' => 'ai-agent',
            ], $headers));

        if ($context->bearerToken) {
            $request = $request->withToken($context->bearerToken);
        }

        $options = [];
        if ($query !== []) {
            $options['query'] = $query;
        }
        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $request->send(strtoupper($method), $url, $options);
        } catch (ConnectionException $e) {
            return ApiResponse::networkFailure($e->getMessage());
        }

        $decoded = $response->json();

        return new ApiResponse($response->status(), is_array($decoded) ? $decoded : null);
    }
}
