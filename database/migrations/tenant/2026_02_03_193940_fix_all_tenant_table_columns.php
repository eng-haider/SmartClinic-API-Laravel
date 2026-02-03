<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration:
     * 1. Removes clinic_id/clinics_id from all tenant tables
     * 2. Adds any missing columns to tenant tables
     */
    public function up(): void
    {
        // ====================================================================
        // STEP 1: Remove clinic_id/clinics_id columns from all tenant tables
        // ====================================================================
        
        // Remove from bills table
        if (Schema::hasTable('bills') && Schema::hasColumn('bills', 'clinics_id')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->dropForeign(['clinics_id']);
                $table->dropIndex(['clinics_id']);
                $table->dropColumn('clinics_id');
            });
        }

        // Remove from cases table
        if (Schema::hasTable('cases') && Schema::hasColumn('cases', 'clinic_id')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropForeign(['clinic_id']);
                $table->dropIndex(['clinic_id']);
                $table->dropColumn('clinic_id');
            });
        }

        // Remove from case_categories table
        if (Schema::hasTable('case_categories') && Schema::hasColumn('case_categories', 'clinic_id')) {
            Schema::table('case_categories', function (Blueprint $table) {
                if (Schema::hasIndex('case_categories', 'case_categories_clinic_id_index')) {
                    $table->dropIndex(['clinic_id']);
                }
                $table->dropColumn('clinic_id');
            });
        }

        // Remove from clinic_expenses table
        if (Schema::hasTable('clinic_expenses') && Schema::hasColumn('clinic_expenses', 'clinic_id')) {
            Schema::table('clinic_expenses', function (Blueprint $table) {
                $table->dropForeign(['clinic_id']);
                $table->dropIndex(['clinic_id']);
                $table->dropColumn('clinic_id');
            });
        }

        // Remove from clinic_expense_categories table
        if (Schema::hasTable('clinic_expense_categories') && Schema::hasColumn('clinic_expense_categories', 'clinic_id')) {
            Schema::table('clinic_expense_categories', function (Blueprint $table) {
                $table->dropForeign(['clinic_id']);
                $table->dropIndex(['clinic_id']);
                $table->dropColumn('clinic_id');
            });
        }

        // Remove from clinic_settings table
        if (Schema::hasTable('clinic_settings') && Schema::hasColumn('clinic_settings', 'clinic_id')) {
            Schema::table('clinic_settings', function (Blueprint $table) {
                $table->dropForeign(['clinic_id']);
                // Drop unique constraint that includes clinic_id
                $table->dropUnique(['clinic_id', 'setting_key']);
                $table->dropIndex(['clinic_id']);
                $table->dropColumn('clinic_id');
                // Recreate unique constraint on setting_key only
                $table->unique('setting_key');
            });
        }

        // Remove from patients table
        if (Schema::hasTable('patients') && Schema::hasColumn('patients', 'clinics_id')) {
            Schema::table('patients', function (Blueprint $table) {
                if (Schema::hasIndex('patients', 'patients_clinics_id_index')) {
                    $table->dropIndex(['clinics_id']);
                }
                $table->dropColumn('clinics_id');
            });
        }

        // Remove from recipe_items table
        if (Schema::hasTable('recipe_items') && Schema::hasColumn('recipe_items', 'clinics_id')) {
            Schema::table('recipe_items', function (Blueprint $table) {
                $table->dropForeign(['clinics_id']);
                $table->dropIndex(['clinics_id']);
                $table->dropColumn('clinics_id');
            });
        }

        // Remove from reservations table
        if (Schema::hasTable('reservations') && Schema::hasColumn('reservations', 'clinics_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropForeign(['clinics_id']);
                $table->dropIndex(['clinics_id']);
                $table->dropColumn('clinics_id');
            });
        }

        // ====================================================================
        // STEP 2: Add missing columns to tenant tables
        // ====================================================================
        
        // Fix case_categories table
        if (Schema::hasTable('case_categories')) {
            Schema::table('case_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('case_categories', 'order')) {
                    $table->integer('order')->default(0)->after('name');
                }
                if (!Schema::hasColumn('case_categories', 'item_cost')) {
                    $table->integer('item_cost')->default(0)->after('order');
                }
            });
        }

        // Fix from_where_comes table
        if (Schema::hasTable('from_where_comes')) {
            Schema::table('from_where_comes', function (Blueprint $table) {
                if (!Schema::hasColumn('from_where_comes', 'order')) {
                    $table->integer('order')->nullable()->after('is_active');
                }
            });
        }

        // Fix statuses table
        if (Schema::hasTable('statuses')) {
            Schema::table('statuses', function (Blueprint $table) {
                if (!Schema::hasColumn('statuses', 'order')) {
                    $table->integer('order')->nullable()->after('color');
                }
            });
        }

        // Fix patients table
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                if (!Schema::hasColumn('patients', 'public_token')) {
                    $table->uuid('public_token')->after('id');
                }
                if (!Schema::hasColumn('patients', 'is_public_profile_enabled')) {
                    $table->boolean('is_public_profile_enabled')->default(false)->after('public_token');
                }
                if (!Schema::hasColumn('patients', 'credit_balance')) {
                    $table->bigInteger('credit_balance')->nullable()->after('identifier');
                }
                if (!Schema::hasColumn('patients', 'credit_balance_add_at')) {
                    $table->timestamp('credit_balance_add_at')->nullable()->after('credit_balance');
                }
            });
        }

        // Fix bills table
        if (Schema::hasTable('bills')) {
            Schema::table('bills', function (Blueprint $table) {
                if (!Schema::hasColumn('bills', 'use_credit')) {
                    $table->boolean('use_credit')->default(false)->after('updator_id');
                }
            });
        }

        // Fix clinic_expenses table
        if (Schema::hasTable('clinic_expenses')) {
            Schema::table('clinic_expenses', function (Blueprint $table) {
                if (!Schema::hasColumn('clinic_expenses', 'quantity')) {
                    $table->bigInteger('quantity')->nullable()->after('name');
                }
                if (!Schema::hasColumn('clinic_expenses', 'date')) {
                    $table->date('date')->default(now())->after('clinic_expense_category_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
