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
        Schema::table('patients', function (Blueprint $table) {
            // Remove first_name and last_name columns if they exist
            if (Schema::hasColumn('patients', 'first_name')) {
                $table->dropColumn('first_name');
            }
            if (Schema::hasColumn('patients', 'last_name')) {
                $table->dropColumn('last_name');
            }
            
            // Also remove old columns that are no longer used
            if (Schema::hasColumn('patients', 'email')) {
                $table->dropUnique(['email']); // Drop unique constraint first
                $table->dropColumn('email');
            }
            if (Schema::hasColumn('patients', 'date_of_birth')) {
                $table->dropColumn('date_of_birth');
            }
            if (Schema::hasColumn('patients', 'gender')) {
                $table->dropColumn('gender');
            }
            if (Schema::hasColumn('patients', 'city')) {
                $table->dropColumn('city');
            }
            if (Schema::hasColumn('patients', 'state')) {
                $table->dropColumn('state');
            }
            if (Schema::hasColumn('patients', 'postal_code')) {
                $table->dropColumn('postal_code');
            }
            if (Schema::hasColumn('patients', 'country')) {
                $table->dropColumn('country');
            }
            if (Schema::hasColumn('patients', 'blood_type')) {
                $table->dropColumn('blood_type');
            }
            if (Schema::hasColumn('patients', 'allergies')) {
                $table->dropColumn('allergies');
            }
            if (Schema::hasColumn('patients', 'medical_history')) {
                $table->dropColumn('medical_history');
            }
            if (Schema::hasColumn('patients', 'emergency_contact_name')) {
                $table->dropColumn('emergency_contact_name');
            }
            if (Schema::hasColumn('patients', 'emergency_contact_phone')) {
                $table->dropColumn('emergency_contact_phone');
            }
            if (Schema::hasColumn('patients', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Restore first_name and last_name columns
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            
            // Restore other old columns
            $table->string('email')->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country')->nullable();
            $table->enum('blood_type', ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'])->nullable();
            $table->text('allergies')->nullable();
            $table->longText('medical_history')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
        });
    }
};
