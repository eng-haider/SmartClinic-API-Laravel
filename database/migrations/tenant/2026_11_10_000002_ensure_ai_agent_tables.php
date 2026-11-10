<?php

use App\Services\AI\Agent\AgentSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Finishes clinics where the chatbot tables were only partly created (for
 * example ai_agent_conversations and ai_agent_messages present but
 * ai_agent_actions missing). Does nothing where everything already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        AgentSchema::ensure();
    }

    public function down(): void
    {
        // The tables belong to 2026_11_10_000001_create_ai_agent_tables
    }
};
