<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates tenant_features table for per-tenant feature flags.
     * Allows enabling/disabling modules (tooth_chart, xray_analysis, etc.) per tenant.
     */
    public function up(): void
    {
        Schema::create('tenant_features', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('feature_key');      // e.g. 'tooth_chart', 'xray_analysis'
            $table->boolean('is_enabled')->default(true);
            $table->json('config')->nullable();  // optional per-feature config
            $table->timestamps();

            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade');

            $table->unique(['tenant_id', 'feature_key']);
            $table->index('feature_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_features');
    }
};
