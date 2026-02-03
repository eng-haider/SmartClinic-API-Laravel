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
            $table->string('id')->primary(); // Same as tenant ID
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('rx_img')->nullable();
            $table->string('whatsapp_template_sid')->nullable();
            $table->integer('whatsapp_message_count')->default(0);
            $table->string('whatsapp_phone')->nullable();
            $table->boolean('show_image_case')->default(true);
            $table->integer('doctor_mony')->default(0);
            $table->boolean('teeth_v2')->default(false);
            $table->boolean('send_msg')->default(true);
            $table->boolean('show_rx_id')->default(true);
            $table->string('logo')->nullable();
            $table->boolean('api_whatsapp')->default(false);
            $table->timestamps();
            $table->softDeletes();
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
