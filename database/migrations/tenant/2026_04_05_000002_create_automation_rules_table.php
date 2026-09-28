<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->string('trigger_type'); // case_created, case_completed, manual, custom_date, periodic
            $table->unsignedInteger('delay_minutes')->nullable();
            $table->unsignedInteger('delay_days')->nullable();
            $table->timestamp('exact_datetime')->nullable();
            $table->boolean('is_periodic')->default(false);
            $table->unsignedInteger('periodic_interval_days')->nullable();
            $table->string('template_key');
            $table->string('channel')->default('whatsapp'); // whatsapp, email, push
            $table->json('conditions_json')->nullable(); // e.g. {"status_id": 3, "category_id": 5}
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('trigger_type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
