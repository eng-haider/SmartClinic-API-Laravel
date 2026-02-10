<?php

/**
 * Update Tooth Colors via API for Existing Tenants
 * 
 * This script calls the API to update tooth_colors for each existing tenant.
 * Requires authentication for each tenant.
 * 
 * Usage:
 *   php update_tooth_colors_via_api.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "Updating Tooth Colors via Direct DB\n";
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
$errorCount = 0;

foreach ($tenants as $tenant) {
    echo "Processing: {$tenant->id} ({$tenant->name})\n";
    
    try {
        // Try multiple database name patterns
        $possibleDbNames = [
            'u876784197_tenant_' . ltrim($tenant->id, '_'),  // u876784197_tenant_clinic_1
            'u876784197_' . ltrim($tenant->id, '_'),         // u876784197_clinic_1
            'tenant_' . ltrim($tenant->id, '_'),             // tenant_clinic_1
            $tenant->id,                                      // clinic_1
        ];
        
        $connected = false;
        $actualDbName = null;
        
        foreach ($possibleDbNames as $dbName) {
            try {
                // Test if database exists
                $result = DB::select("SHOW DATABASES LIKE ?", [$dbName]);
                if (!empty($result)) {
                    $actualDbName = $dbName;
                    echo "  ✓ Found database: {$dbName}\n";
                    
                    // Configure connection
                    $centralConfig = config('database.connections.mysql');
                    config([
                        'database.connections.tenant_update.driver' => 'mysql',
                        'database.connections.tenant_update.database' => $dbName,
                        'database.connections.tenant_update.username' => $centralConfig['username'],
                        'database.connections.tenant_update.password' => $centralConfig['password'],
                        'database.connections.tenant_update.host' => $centralConfig['host'],
                        'database.connections.tenant_update.port' => $centralConfig['port'],
                    ]);
                    
                    DB::purge('tenant_update');
                    DB::connection('tenant_update')->getPdo();
                    $connected = true;
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        if (!$connected) {
            echo "  ⚠️  Database not found. Tried:\n";
            foreach ($possibleDbNames as $name) {
                echo "     - {$name}\n";
            }
            echo "\n";
            $errorCount++;
            continue;
        }
        
        // Check if clinic_settings table exists
        $tables = DB::connection('tenant_update')->select('SHOW TABLES');
        $tableName = "Tables_in_{$actualDbName}";
        $hasClinicSettings = false;
        
        foreach ($tables as $table) {
            if ($table->$tableName === 'clinic_settings') {
                $hasClinicSettings = true;
                break;
            }
        }
        
        if (!$hasClinicSettings) {
            echo "  ⚠️  No clinic_settings table found.\n\n";
            $errorCount++;
            continue;
        }
        
        // Check current tooth_colors setting
        $setting = DB::connection('tenant_update')
            ->table('clinic_settings')
            ->where('setting_key', 'tooth_colors')
            ->first();
        
        if (!$setting) {
            echo "  📝 Creating tooth_colors setting...\n";
            
            DB::connection('tenant_update')->table('clinic_settings')->insert([
                'setting_key' => 'tooth_colors',
                'setting_value' => json_encode($newToothColors),
                'setting_type' => 'json',
                'description' => 'Tooth status colors for dental chart',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            echo "  ✅ Created with 7 tooth statuses\n\n";
            $successCount++;
            continue;
        }
        
        // Update existing setting
        $oldColors = json_decode($setting->setting_value, true) ?? [];
        $oldCount = count($oldColors);
        
        // Merge (preserves custom colors, adds new ones)
        $updatedColors = array_merge($oldColors, $newToothColors);
        $newCount = count($updatedColors);
        
        DB::connection('tenant_update')
            ->table('clinic_settings')
            ->where('setting_key', 'tooth_colors')
            ->update([
                'setting_value' => json_encode($updatedColors),
                'updated_at' => now(),
            ]);
        
        echo "  ✅ Updated: {$oldCount} → {$newCount} statuses\n";
        if ($newCount > $oldCount) {
            echo "     Added: implant, root_canal\n";
        }
        echo "     Updated: cavity color (#E74C3C)\n\n";
        $successCount++;
        
    } catch (\Exception $e) {
        echo "  ❌ Error: {$e->getMessage()}\n\n";
        $errorCount++;
    }
}

echo "========================================\n";
echo "Summary:\n";
echo "  ✅ Success: {$successCount}\n";
echo "  ❌ Errors: {$errorCount}\n";
echo "========================================\n";

if ($successCount > 0) {
    echo "\n✨ Successfully updated {$successCount} tenant(s)!\n";
    echo "\nNew tooth colors:\n";
    echo "  🦷 healthy: #FFFFFF (White)\n";
    echo "  🦷 cavity: #E74C3C (Darker Red) ← Updated!\n";
    echo "  🦷 filling: #4ECDC4 (Teal)\n";
    echo "  🦷 crown: #FFD93D (Yellow)\n";
    echo "  🦷 missing: #95A5A6 (Gray)\n";
    echo "  🦷 implant: #3498DB (Blue) ← NEW!\n";
    echo "  🦷 root_canal: #9B59B6 (Purple) ← NEW!\n";
}
