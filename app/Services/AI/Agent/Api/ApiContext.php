<?php

namespace App\Services\AI\Agent\Api;

use Illuminate\Http\Request;

/**
 * Who the API calls are made as. Built on the server from the incoming request:
 * the user's own bearer token and the tenant the request was initialized for.
 * Never shown to the LLM.
 */
final class ApiContext
{
    public function __construct(
        public readonly ?string $bearerToken,
        public readonly string $tenantId,
        public readonly string $locale = 'ar',
        public readonly ?string $clientIp = null,
        public readonly ?string $host = null,
    ) {
    }

    public static function fromRequest(Request $request, string $tenantId, string $locale): self
    {
        return new self(
            $request->bearerToken(),
            $tenantId,
            $locale,
            $request->ip(),
            $request->getSchemeAndHttpHost(),
        );
    }
}
