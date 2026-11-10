<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * State for the chatbot's action agent: conversations (slot filling and the
 * patient being discussed), their messages (for duplicate-delivery checks) and
 * the actions waiting for the user's confirmation.
 */
return new class extends Migration
{
    public function up(): void
    {
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
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'status']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_actions');
        Schema::dropIfExists('ai_agent_messages');
        Schema::dropIfExists('ai_agent_conversations');
    }
};
