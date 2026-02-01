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
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('path'); // File path or URL
            $table->string('disk')->default('public'); // Storage disk (public, s3, etc.)
            $table->string('type')->nullable(); // Image type: profile, document, xray, etc.
            $table->string('mime_type')->nullable(); // Image mime type
            $table->unsignedBigInteger('size')->nullable(); // File size in bytes
            $table->integer('width')->nullable(); // Image width
            $table->integer('height')->nullable(); // Image height
            $table->string('alt_text')->nullable(); // Alternative text for accessibility
            $table->integer('order')->default(0); // For ordering multiple images
            $table->morphs('imageable'); // Creates imageable_id and imageable_type with index
            $table->timestamps();

            // Additional indexes
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
