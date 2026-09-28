<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->timestamp('scheduled_for');
            $table->string('status')->default('pending'); // pending, sent, failed, cancelled
            $table->unsignedBigInteger('message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index('patient_id');
            $table->index('automation_rule_id');

            $table->foreign('case_id')->references('id')->on('cases')->nullOnDelete();
            $table->foreign('message_id')->references('id')->on('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_targets');
    }
};
