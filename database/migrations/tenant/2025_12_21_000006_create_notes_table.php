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
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('noteable'); // Creates noteable_id and noteable_type with index
            $table->text('content');
            $table->unsignedBigInteger('created_by')->nullable(); // User who created the note
            $table->timestamps();
            
            // Foreign key for created_by
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            // Index for created_by
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
