<?php

/*
|--------------------------------------------------------------------------
| AI Action Agent
|--------------------------------------------------------------------------
|
| The chatbot agent understands requests with the LLM, then performs them
| through the clinic's own REST API as the signed-in user. Nothing that
| changes data runs before the user confirms it.
|
*/

return [

    // OpenAI model for intent and entity extraction (same provider as the chatbot)
    'model' => env('AI_AGENT_MODEL', env('OPENAI_ANALYZER_MODEL', 'gpt-4o-mini')),
    'llm_timeout' => (int) env('AI_AGENT_LLM_TIMEOUT', 12),

    // How API calls are sent:
    //  - "kernel": a real HTTP request handled by this app's HTTP kernel (routes,
    //    JWT, tenancy, permission middleware and validation all run), without a
    //    network hop. Safe on single-worker servers.
    //  - "http": a network request to api_base_url.
    'api_transport' => env('AI_AGENT_API_TRANSPORT', 'kernel'),
    'api_base_url' => env('AI_AGENT_API_BASE_URL', env('APP_URL')),
    'api_prefix' => 'api/tenant',
    'api_timeout' => (int) env('AI_AGENT_API_TIMEOUT', 15),

    // A confirmation card is valid for this many minutes
    'confirmation_ttl_minutes' => (int) env('AI_AGENT_CONFIRMATION_TTL', 10),

    // A patient mentioned earlier ("his balance", "record 50k") stays the
    // conversation's subject for this many minutes
    'context_ttl_minutes' => (int) env('AI_AGENT_CONTEXT_TTL', 30),

    // Dates like "tomorrow" are resolved in this timezone (empty: app.timezone)
    'timezone' => env('AI_AGENT_TIMEZONE'),

    // Reservation status ids (statuses table seed)
    'statuses' => [
        'new' => 1,
        'in_progress' => 2,
        'completed' => 3,
        'cancelled' => 4,
    ],

    // billable_type the web app sends when it records a payment for a case
    'case_billable_type' => 'App\\Models\\Case',

    // Recent turns sent to the LLM for context
    'history_turns' => 6,
];
