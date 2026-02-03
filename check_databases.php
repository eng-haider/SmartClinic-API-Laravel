<?php

/**
 * Check what databases exist on the server
 * and verify tenant setup
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "==========================================\n";
echo "  DATABASE STATUS CHECK\n";
echo "==========================================\n\n";

// 1. Check central database
echo "1️⃣  CENTRAL DATABASE\n";
echo "   Connection: " . config('database.default') . "\n";
echo "   Database: " . config('database.connections.mysql.database') . "\n\n";

try {
    DB::connection()->getPdo();
    echo "   ✅ Connection: SUCCESS\n";
    
    // Count records
    $clinicsCount = DB::table('clinics')->count();
    $tenantsCount = DB::table('tenants')->count();
    $usersCount = DB::table('users')->count();
    
    echo "   📊 Clinics: $clinicsCount\n";
    echo "   📊 Tenants: $tenantsCount\n";
    echo "   📊 Users: $usersCount\n\n";
    
    if ($clinicsCount > 0) {
        echo "   📋 Existing Clinics:\n";
        $clinics = DB::table('clinics')->select('id', 'name')->get();
        foreach ($clinics as $clinic) {
            echo "      - ID: {$clinic->id} | Name: {$clinic->name}\n";
        }
        echo "\n";
    }
    
    if ($tenantsCount > 0) {
        echo "   📋 Existing Tenants:\n";
        $tenants = DB::table('tenants')->select('id', 'data')->get();
        foreach ($tenants as $tenant) {
            $data = json_decode($tenant->data, true);
            $dbName = $data['tenancy_db_name'] ?? 'N/A';
            echo "      - ID: {$tenant->id} | DB: {$dbName}\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Connection FAILED: " . $e->getMessage() . "\n\n";
}

// 2. Show all available databases
echo "2️⃣  ALL DATABASES ON SERVER\n";
try {
    $databases = DB::select('SHOW DATABASES');
    $dbList = array_column($databases, 'Database');
    
    $tenantDbs = array_filter($dbList, function($db) {
        return strpos($db, 'u876784197_tenant_') === 0;
    });
    
    if (empty($tenantDbs)) {
        echo "   ⚠️  No tenant databases found!\n";
        echo "   Expected pattern: u876784197_tenant_*\n\n";
    } else {
        echo "   ✅ Found " . count($tenantDbs) . " tenant database(s):\n";
        foreach ($tenantDbs as $db) {
            echo "      - $db\n";
        }
        echo "\n";
    }
    
    // Show central database
    $centralDb = config('database.connections.mysql.database');
    if (in_array($centralDb, $dbList)) {
        echo "   ✅ Central database exists: $centralDb\n\n";
    } else {
        echo "   ❌ Central database NOT found: $centralDb\n\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Could not list databases: " . $e->getMessage() . "\n\n";
}

// 3. Configuration check
echo "3️⃣  TENANT CONFIGURATION\n";
echo "   Tenant DB Prefix: " . config('tenancy.database.prefix') . "\n";
echo "   Template DB: " . config('tenancy.database.template_tenant_connection') . "\n\n";

// 4. Recommendation
echo "4️⃣  RECOMMENDATIONS\n";

if ($clinicsCount === 0 && $tenantsCount === 0) {
    echo "   ⚠️  No clinics or tenants found!\n\n";
    echo "   SOLUTION 1: Register a new clinic\n";
    echo "   POST /api/auth/register with clinic details\n\n";
    echo "   SOLUTION 2: Create tenant manually\n";
    echo "   POST /api/tenants with clinic data\n\n";
} else {
    echo "   ✅ System has existing data\n";
    echo "   You can login with existing credentials\n\n";
}

echo "==========================================\n\n";
