<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('rx_img')->nullable();
            $table->text('whatsapp_template_sid')->nullable();
            $table->bigInteger('whatsapp_message_count')->nullable();
            $table->string('whatsapp_phone')->nullable();
            $table->boolean('show_image_case')->default(false);
            $table->bigInteger('doctor_mony')->nullable();
            $table->boolean('teeth_v2')->default(false);
            $table->boolean('send_msg')->default(false);
            $table->boolean('show_rx_id')->default(false);
            $table->string('logo')->nullable();
            $table->boolean('api_whatsapp')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
