<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('whatsapp'); // whatsapp, email, push
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_access_token')->nullable();
            $table->string('whatsapp_business_account_id')->nullable();
            $table->string('whatsapp_webhook_verify_token')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('meta')->nullable(); // extra provider-specific config
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_settings');
    }
};
