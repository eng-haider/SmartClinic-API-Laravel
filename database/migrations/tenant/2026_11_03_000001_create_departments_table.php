<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic departments (e.g. Orthodontics, Cosmetic). A case category may belong
 * to one department, and its cases and bills are filtered by it. Only used when
 * the clinic turns on the enable_departments setting.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('case_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('is_active');

            $table->foreign('department_id')
                  ->references('id')
                  ->on('departments')
                  ->nullOnDelete();

            $table->index('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_categories', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
