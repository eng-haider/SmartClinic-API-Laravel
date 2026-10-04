<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Browser Web Push subscriptions (one row per browser/device a user enabled
 * notifications on). See WebPushService.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                // The push service URL is too long for a unique index, so it is unique by hash
                $table->text('endpoint');
                $table->char('endpoint_hash', 64)->unique();

                // Browser encryption keys (base64url)
                $table->string('public_key');
                $table->string('auth_token');
                $table->string('content_encoding', 20)->default('aes128gcm');

                // UI language of that browser, so the push text matches it
                $table->string('locale', 10)->nullable();
                $table->string('user_agent')->nullable();

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
