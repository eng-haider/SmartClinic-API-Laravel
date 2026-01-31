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
        // Add soft deletes to case_categories
        Schema::table('case_categories', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to clinics
        Schema::table('clinics', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to clinic_settings
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to from_where_comes
        Schema::table('from_where_comes', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to images
        Schema::table('images', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to notes
        Schema::table('notes', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to recipes
        Schema::table('recipes', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to statuses
        Schema::table('statuses', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to users
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove soft deletes from case_categories
        Schema::table('case_categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from clinics
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from clinic_settings
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from from_where_comes
        Schema::table('from_where_comes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from images
        Schema::table('images', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from notes
        Schema::table('notes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from recipes
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from statuses
        Schema::table('statuses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
