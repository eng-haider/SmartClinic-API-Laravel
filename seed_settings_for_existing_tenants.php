<?php

/**
 * Script to Add Default Clinic Settings to Existing Tenants
 * 
 * This script runs the TenantClinicSettingsSeeder on all existing tenants
 * that have empty or incomplete clinic_settings tables.
 * 
 * Usage:
 *   php seed_settings_for_existing_tenants.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

echo "========================================\n";
echo "Seeding Clinic Settings for Existing Tenants\n";
echo "========================================\n\n";

// Get all tenants
$tenants = Tenant::all();

if ($tenants->isEmpty()) {
    echo "❌ No tenants found.\n";
    exit(1);
}

echo "Found " . $tenants->count() . " tenant(s).\n\n";

$successCount = 0;
$skipCount = 0;
$errorCount = 0;

foreach ($tenants as $tenant) {
    echo "Processing: {$tenant->id} ({$tenant->name})\n";
    
    try {
        // Configure tenant database connection
        $prefix = config('tenancy.database.prefix', 'tenant');
        $cleanName = ltrim($tenant->id, '_');
        $databaseName = $prefix . '_' . $cleanName;
        
        // Use credentials from tenant or config
        $dbUsername = $tenant->db_username ?? $databaseName;
        $dbPassword = $tenant->db_password ?? config('database.connections.mysql.password');
        
        $centralConfig = config('database.connections.mysql');
        
        config([
            'database.connections.tenant.database' => $databaseName,
            'database.connections.tenant.username' => $dbUsername,
            'database.connections.tenant.password' => $dbPassword,
            'database.connections.tenant.host' => $centralConfig['host'],
            'database.connections.tenant.port' => $centralConfig['port'],
        ]);
        
        DB::purge('tenant');
        
        // Test connection
        try {
            DB::connection('tenant')->getPdo();
        } catch (\Exception $e) {
            echo "  ❌ Cannot connect to database: {$e->getMessage()}\n\n";
            $errorCount++;
            continue;
        }
        
        // Check if clinic_settings table exists
        $tables = DB::connection('tenant')->select('SHOW TABLES');
        $tableName = "Tables_in_{$databaseName}";
        $hasClinicSettings = false;
        
        foreach ($tables as $table) {
            if ($table->$tableName === 'clinic_settings') {
                $hasClinicSettings = true;
                break;
            }
        }
        
        if (!$hasClinicSettings) {
            echo "  ⚠️  No clinic_settings table found. Skipping.\n\n";
            $skipCount++;
            continue;
        }
        
        // Check if settings already exist
        $existingCount = DB::connection('tenant')->table('clinic_settings')->count();
        
        if ($existingCount > 0) {
            echo "  ℹ️  Already has {$existingCount} settings. Skipping.\n\n";
            $skipCount++;
            continue;
        }
        
        // Run seeder
        echo "  🌱 Seeding clinic settings...\n";
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => 'TenantClinicSettingsSeeder',
            '--force' => true,
        ]);
        
        $newCount = DB::connection('tenant')->table('clinic_settings')->count();
        echo "  ✅ Successfully seeded {$newCount} settings.\n\n";
        $successCount++;
        
    } catch (\Exception $e) {
        echo "  ❌ Error: {$e->getMessage()}\n\n";
        $errorCount++;
    }
}

echo "========================================\n";
echo "Summary:\n";
echo "  ✅ Seeded: {$successCount}\n";
echo "  ⏭️  Skipped: {$skipCount}\n";
echo "  ❌ Errors: {$errorCount}\n";
echo "========================================\n";

if ($successCount > 0) {
    echo "\n✨ Successfully seeded clinic settings for {$successCount} tenant(s)!\n";
}
