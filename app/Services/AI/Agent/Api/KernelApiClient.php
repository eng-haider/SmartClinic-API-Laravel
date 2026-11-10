<?php

namespace App\Services\AI\Agent\Api;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;

/**
 * Sends the API request through this application's HTTP kernel.
 *
 * It is a real HTTP request to the real route: global middleware, JWT auth,
 * tenancy by header, permission middleware, form request validation and the
 * controller all run exactly as for the web app. Only the network hop is
 * skipped, so it cannot deadlock a single-worker server calling itself.
 */
class KernelApiClient implements ApiClient
{
    public function __construct(private Application $app)
    {
    }

    public function send(
        ApiContext $context,
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        array $headers = [],
    ): ApiResponse {
        $method = strtoupper($method);
        $uri = '/' . trim(config('ai_agent.api_prefix', 'api/tenant'), '/') . '/' . ltrim($path, '/');
        if ($query !== []) {
            $uri .= '?' . http_build_query($query);
        }

        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TENANT_ID' => $context->tenantId,
            'HTTP_ACCEPT_LANGUAGE' => $context->locale,
            'HTTP_X_REQUESTED_BY' => 'ai-agent',
            'REMOTE_ADDR' => $context->clientIp ?? '127.0.0.1',
        ];
        if ($context->bearerToken) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $context->bearerToken;
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $subRequest = Request::create(
            ($context->host ?? '') . $uri,
            $method,
            [],
            [],
            [],
            $server,
            $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : null,
        );

        $original = $this->app->bound('request') ? $this->app->make('request') : null;

        try {
            $response = $this->app->make(HttpKernel::class)->handle($subRequest);
        } catch (\Throwable $e) {
            Log::error('AI agent API call failed', ['method' => $method, 'path' => $path, 'error' => $e->getMessage()]);

            return ApiResponse::networkFailure($e->getMessage(), false);
        } finally {
            // Put the chatbot's own request back for the rest of this request
            if ($original) {
                $this->app->instance('request', $original);
                Facade::clearResolvedInstance('request');
            }
        }

        $decoded = json_decode((string) $response->getContent(), true);

        return new ApiResponse($response->getStatusCode(), is_array($decoded) ? $decoded : null);
    }
}
