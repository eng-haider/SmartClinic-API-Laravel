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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship - can notify any model (User, Patient, etc.)
            $table->morphs('notifiable');
            
            // Notification content
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('general'); // general, appointment, payment, case, etc.
            
            // Additional data (JSON format for flexibility)
            $table->json('data')->nullable();
            
            // OneSignal integration
            $table->string('onesignal_notification_id')->nullable();
            $table->enum('onesignal_status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('onesignal_error')->nullable();
            
            // Read status
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            
            // Sender information (optional - who triggered this notification)
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
            
            // Priority level
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            
            // Action URL or deep link
            $table->string('action_url')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['is_read', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
