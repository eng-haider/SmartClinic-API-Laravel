<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('channel')->default('whatsapp');
            $table->text('body'); // supports {{patient_name}}, {{doctor_name}}, etc.
            $table->string('language')->default('ar');
            $table->boolean('is_active')->default(true);
            $table->json('variables')->nullable(); // list of variables used: ["patient_name","doctor_name"]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
