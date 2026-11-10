<?php

namespace App\Services\AI\Agent;

/**
 * The clinic the request was initialized for by the tenancy middleware.
 * Never taken from the message body or the LLM.
 */
class CurrentTenant
{
    public function id(): ?string
    {
        $id = function_exists('tenant') ? tenant('id') : null;

        return $id !== null ? (string) $id : null;
    }
}
