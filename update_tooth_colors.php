<?php

/**
 * Update Tooth Colors for All Existing Tenants
 * 
 * This script updates the tooth_colors setting to include:
 * - New cavity color: #E74C3C (darker red)
 * - New status: implant (#3498DB - blue)
 * - New status: root_canal (#9B59B6 - purple)
 * 
 * Usage:
 *   php update_tooth_colors.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "Updating Tooth Colors for All Tenants\n";
echo "========================================\n\n";

$newToothColors = [
    'healthy' => '#FFFFFF',
    'cavity' => '#E74C3C',      // Changed from #FF6B6B
    'filling' => '#4ECDC4',
    'crown' => '#FFD93D',
    'missing' => '#95A5A6',
    'implant' => '#3498DB',     // NEW
    'root_canal' => '#9B59B6',  // NEW
];

echo "New tooth colors:\n";
foreach ($newToothColors as $status => $color) {
    echo "  • {$status}: {$color}\n";
}
echo "\n";

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
        
        // Check if tooth_colors setting exists
        $setting = DB::connection('tenant')
            ->table('clinic_settings')
            ->where('setting_key', 'tooth_colors')
            ->first();
        
        if (!$setting) {
            echo "  ℹ️  No tooth_colors setting found. Creating...\n";
            
            DB::connection('tenant')->table('clinic_settings')->insert([
                'setting_key' => 'tooth_colors',
                'setting_value' => json_encode($newToothColors),
                'setting_type' => 'json',
                'description' => 'Tooth status colors for dental chart',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            echo "  ✅ Created tooth_colors setting with new values.\n\n";
            $successCount++;
            continue;
        }
        
        // Update existing setting
        $oldColors = json_decode($setting->setting_value, true);
        
        echo "  📝 Old colors: " . count($oldColors ?? []) . " statuses\n";
        
        // Merge with new colors (preserves any custom colors, adds new ones)
        $updatedColors = array_merge($oldColors ?? [], $newToothColors);
        
        DB::connection('tenant')
            ->table('clinic_settings')
            ->where('setting_key', 'tooth_colors')
            ->update([
                'setting_value' => json_encode($updatedColors),
                'updated_at' => now(),
            ]);
        
        echo "  ✅ Updated tooth_colors: " . count($updatedColors) . " statuses\n";
        echo "     Added: implant, root_canal\n";
        echo "     Updated: cavity color\n\n";
        $successCount++;
        
    } catch (\Exception $e) {
        echo "  ❌ Error: {$e->getMessage()}\n\n";
        $errorCount++;
    }
}

echo "========================================\n";
echo "Summary:\n";
echo "  ✅ Updated: {$successCount}\n";
echo "  ⏭️  Skipped: {$skipCount}\n";
echo "  ❌ Errors: {$errorCount}\n";
echo "========================================\n";

if ($successCount > 0) {
    echo "\n✨ Successfully updated tooth colors for {$successCount} tenant(s)!\n";
    echo "\nNew tooth statuses:\n";
    echo "  • healthy: White (#FFFFFF)\n";
    echo "  • cavity: Darker Red (#E74C3C) ← Updated!\n";
    echo "  • filling: Teal (#4ECDC4)\n";
    echo "  • crown: Yellow (#FFD93D)\n";
    echo "  • missing: Gray (#95A5A6)\n";
    echo "  • implant: Blue (#3498DB) ← NEW!\n";
    echo "  • root_canal: Purple (#9B59B6) ← NEW!\n";
}
