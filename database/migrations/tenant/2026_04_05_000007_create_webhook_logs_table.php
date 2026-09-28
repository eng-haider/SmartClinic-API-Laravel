<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('whatsapp');
            $table->string('event_type')->nullable(); // message, status, error
            $table->json('payload');
            $table->string('status')->default('received'); // received, processed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['source', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
