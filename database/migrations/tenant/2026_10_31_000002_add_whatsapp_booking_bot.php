<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The WhatsApp booking bot (see WhatsAppBookingBot): booking requests remember
     * where they came from, and each customer's place in the bot's questions is
     * kept between their messages.
     */
    public function up(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            // 'website' (the public booking page) or 'whatsapp' (the bot)
            $table->string('source', 20)->default('website')->after('note');
        });

        Schema::create('whatsapp_booking_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 33)->unique();
            // Which question the customer is answering: day, time or name
            $table->string('step', 20);
            // The choices offered and the answers so far
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_booking_sessions');

        Schema::table('booking_requests', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
