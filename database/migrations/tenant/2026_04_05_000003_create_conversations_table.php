<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversations must be created before messages (messages references conversations)
        // But migration ordering by filename handles this — rename if needed.
        // Actually, let's create conversations first and adjust the messages migration.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('channel')->default('whatsapp');
            $table->string('phone_number'); // patient's phone
            $table->string('status')->default('open'); // open, closed
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'channel']);
            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
