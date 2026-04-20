<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('direction'); // inbound, outbound
            $table->string('channel')->default('whatsapp');
            $table->string('from_number')->nullable();
            $table->string('to_number')->nullable();
            $table->text('body')->nullable();
            $table->string('template_key')->nullable();
            $table->json('template_params')->nullable();
            $table->string('external_id')->nullable(); // WhatsApp message ID
            $table->string('status')->default('queued'); // queued, sent, delivered, read, failed
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('external_id');
            $table->index('status');
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
