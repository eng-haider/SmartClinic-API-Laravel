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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('age')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('systemic_conditions')->nullable();
            $table->integer('sex')->nullable(); // 1 = male, 2 = female
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('rx_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('from_where_come_id')->nullable();
            $table->string('identifier')->nullable();
            $table->bigInteger('credit_balance')->nullable();
            $table->timestamp('credit_balance_add_at')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('updator_id')->nullable();
            $table->json('tooth_details')->nullable();
            $table->string('public_token')->nullable()->unique();
            $table->boolean('is_public_profile_enabled')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('from_where_come_id')->references('id')->on('from_where_comes')->onDelete('set null');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updator_id')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('phone');
            $table->index('doctor_id');
            $table->index('from_where_come_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
