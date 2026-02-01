<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            // Clinic custom columns
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('rx_img')->nullable();
            $table->string('whatsapp_template_sid')->nullable();
            $table->integer('whatsapp_message_count')->default(0);
            $table->string('whatsapp_phone')->nullable();
            $table->boolean('show_image_case')->default(false);
            $table->integer('doctor_mony')->default(0);
            $table->boolean('teeth_v2')->default(false);
            $table->boolean('send_msg')->default(false);
            $table->boolean('show_rx_id')->default(false);
            $table->string('logo')->nullable();
            $table->boolean('api_whatsapp')->default(false);

            $table->timestamps();
            $table->softDeletes();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
