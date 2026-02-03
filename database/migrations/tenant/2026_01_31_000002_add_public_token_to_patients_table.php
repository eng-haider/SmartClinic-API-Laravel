<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Patient;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->unique()->after('id');
            $table->boolean('is_public_profile_enabled')->default(false)->after('public_token');
        });

        // Generate public_token for existing patients
        Patient::withTrashed()->whereNull('public_token')->each(function ($patient) {
            $patient->update([
                'public_token' => Str::uuid()->toString(),
            ]);
        });

        // Make public_token not nullable after populating
        Schema::table('patients', function (Blueprint $table) {
            $table->uuid('public_token')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['public_token', 'is_public_profile_enabled']);
        });
    }
};
