<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('central_user_id')->nullable();
            $table->unsignedBigInteger('tenant_user_id')->nullable();
            // Cleared once the account is purged.
            $table->string('phone', 50)->nullable();
            $table->boolean('deletes_clinic')->default(false);
            $table->timestamp('purge_after');
            $table->timestamp('purged_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['purged_at', 'cancelled_at', 'purge_after']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
