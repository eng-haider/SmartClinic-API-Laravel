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
            // Add new columns that don't exist
            if (!Schema::hasColumn('patients', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('patients', 'age')) {
                $table->integer('age')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('patients', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('age');
            }
            if (!Schema::hasColumn('patients', 'doctor_id')) {
                $table->unsignedBigInteger('doctor_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('patients', 'clinics_id')) {
                $table->unsignedBigInteger('clinics_id')->nullable()->after('doctor_id');
            }
            if (!Schema::hasColumn('patients', 'systemic_conditions')) {
                $table->string('systemic_conditions')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('patients', 'sex')) {
                $table->integer('sex')->nullable()->after('systemic_conditions');
            }
            if (!Schema::hasColumn('patients', 'notes')) {
                $table->text('notes')->nullable()->after('address');
            }
            if (!Schema::hasColumn('patients', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('patients', 'deleted_at')) {
                $table->softDeletes()->after('birth_date');
            }
            if (!Schema::hasColumn('patients', 'rx_id')) {
                $table->string('rx_id')->nullable()->after('deleted_at');
            }
            if (!Schema::hasColumn('patients', 'note')) {
                $table->text('note')->nullable()->after('rx_id');
            }
            if (!Schema::hasColumn('patients', 'from_where_come_id')) {
                $table->unsignedBigInteger('from_where_come_id')->nullable()->after('note');
            }
            if (!Schema::hasColumn('patients', 'identifier')) {
                $table->string('identifier')->nullable()->after('from_where_come_id');
            }
            if (!Schema::hasColumn('patients', 'credit_balance')) {
                $table->bigInteger('credit_balance')->nullable()->after('identifier');
            }
            if (!Schema::hasColumn('patients', 'credit_balance_add_at')) {
                $table->timestamp('credit_balance_add_at')->nullable()->after('credit_balance');
            }

            // Add indexes
            $table->index('user_id');
            $table->index('doctor_id');
            $table->index('clinics_id');
            $table->index('from_where_come_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Drop foreign keys
            $table->dropForeign(['user_id']);
            $table->dropForeign(['doctor_id']);
            $table->dropForeign(['clinics_id']);
            $table->dropForeign(['from_where_come_id']);

            // Drop new columns
            $table->dropColumn([
                'name',
                'age',
                'user_id',
                'doctor_id',
                'clinics_id',
                'systemic_conditions',
                'sex',
                'notes',
                'birth_date',
                'rx_id',
                'note',
                'from_where_come_id',
                'identifier',
                'credit_balance',
                'credit_balance_add_at'
            ]);

            // Restore old columns
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->unique();
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female', 'other']);
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
