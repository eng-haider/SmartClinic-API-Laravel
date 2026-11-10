<?php

namespace App\Services\AI\Agent;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The chatbot's own tables in a clinic database: conversations, their
 * messages, and the actions waiting for confirmation.
 *
 * ensure() creates only what is missing, so it can run again on a clinic
 * whose earlier migration stopped half-way (e.g. a dropped connection on
 * shared hosting left two of the three tables).
 */
class AgentSchema
{
    public static function ensure(): void
    {
        if (!Schema::hasTable('ai_agent_conversations')) {
            Schema::create('ai_agent_conversations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->string('language', 5)->default('ar');
                $table->json('state')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                // One turn at a time per conversation (not the cache: tenancy tags it)
                $table->timestamp('locked_until')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'user_id']);
            });
        } elseif (!Schema::hasColumn('ai_agent_conversations', 'locked_until')) {
            Schema::table('ai_agent_conversations', function (Blueprint $table) {
                $table->timestamp('locked_until')->nullable();
            });
        }

        if (!Schema::hasTable('ai_agent_messages')) {
            Schema::create('ai_agent_messages', function (Blueprint $table) {
                $table->id();
                $table->uuid('conversation_id');
                $table->string('client_message_id', 100)->nullable();
                $table->string('role', 10);
                $table->text('content');
                $table->json('reply')->nullable();
                $table->timestamps();

                $table->unique(['conversation_id', 'client_message_id']);
                $table->index('conversation_id');
            });
        }

        if (!Schema::hasTable('ai_agent_actions')) {
            Schema::create('ai_agent_actions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->uuid('conversation_id');
                $table->string('tool', 64);
                $table->json('params');
                $table->json('entities')->nullable();
                $table->json('summary');
                $table->json('snapshot')->nullable();
                $table->string('language', 5)->default('ar');
                $table->uuid('idempotency_key')->unique();
                // pending, executing, executed, failed, unknown, cancelled, expired
                $table->string('status', 20)->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->json('result')->nullable();
                $table->text('error')->nullable();
                // DATETIME, not TIMESTAMP: on MariaDB/MySQL without explicit_defaults_for_timestamp
                // the first NOT NULL TIMESTAMP silently becomes "ON UPDATE CURRENT_TIMESTAMP"
                $table->dateTime('expires_at');
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'user_id', 'status']);
                $table->index('conversation_id');
            });
        }
    }

    /** True when this clinic's database has everything the chatbot's actions need. */
    public static function ready(): bool
    {
        try {
            return Schema::hasTable('ai_agent_actions')
                && Schema::hasTable('ai_agent_messages')
                && Schema::hasColumn('ai_agent_conversations', 'locked_until');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function drop(): void
    {
        Schema::dropIfExists('ai_agent_actions');
        Schema::dropIfExists('ai_agent_messages');
        Schema::dropIfExists('ai_agent_conversations');
    }
}
