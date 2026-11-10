<?php

use App\Services\AI\Agent\AgentSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * State for the chatbot's actions: conversations (slot filling and the
 * patient being discussed), their messages (for duplicate-delivery checks) and
 * the actions waiting for the user's confirmation. Creates only what is
 * missing, so it can be run again after a partial failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new AgentSchema())->ensure();
    }

    public function down(): void
    {
        (new AgentSchema())->drop();
    }
};
