<?php

/**
 * Cleanup script for failed tenant creations
 * 
 * Usage: php cleanup_failed_tenant.php <tenant_id>
 * Example: php cleanup_failed_tenant.php _alamal
 * 
 * This script will remove:
 * 1. Tenant record from tenants table
 * 2. Clinic record from clinics table
 * 3. Associated users from users table
 * 4. Optionally drop the tenant database (if you have permissions)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Get tenant ID from command line argument
$tenantId = $argv[1] ?? null;

if (!$tenantId) {
    echo "❌ Error: Please provide a tenant ID\n";
    echo "Usage: php cleanup_failed_tenant.php <tenant_id>\n";
    echo "Example: php cleanup_failed_tenant.php _alamal\n";
    exit(1);
}

echo "🧹 Starting cleanup for tenant: {$tenantId}\n";
echo str_repeat("=", 60) . "\n";

$centralConnection = config('tenancy.database.central_connection');
$cleaned = false;

try {
    DB::connection($centralConnection)->beginTransaction();
    
    // 1. Find and delete tenant
    $tenant = Tenant::find($tenantId);
    if ($tenant) {
        echo "✓ Found tenant record: {$tenant->name}\n";
        $tenant->delete();
        echo "✓ Deleted tenant record\n";
        $cleaned = true;
    } else {
        echo "ℹ No tenant record found with ID: {$tenantId}\n";
    }
    
    // 2. Find and delete clinic
    $clinic = Clinic::on($centralConnection)->find($tenantId);
    if ($clinic) {
        echo "✓ Found clinic record: {$clinic->name}\n";
        $clinic->forceDelete();
        echo "✓ Deleted clinic record\n";
        $cleaned = true;
    } else {
        echo "ℹ No clinic record found with ID: {$tenantId}\n";
    }
    
    // 3. Find and delete users associated with this clinic
    $users = User::on($centralConnection)->where('clinic_id', $tenantId)->get();
    if ($users->count() > 0) {
        echo "✓ Found {$users->count()} user(s) associated with this clinic\n";
        foreach ($users as $user) {
            echo "  - Deleting user: {$user->name} (phone: {$user->phone})\n";
            $user->forceDelete();
        }
        echo "✓ Deleted all associated users\n";
        $cleaned = true;
    } else {
        echo "ℹ No users found associated with this clinic\n";
    }
    
    DB::connection($centralConnection)->commit();
    
    // 4. Try to drop the tenant database (might fail on shared hosting)
    $databaseName = config('tenancy.database.prefix') . $tenantId;
    echo "\n📊 Attempting to drop database: {$databaseName}\n";
    
    try {
        DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");
        echo "✓ Database dropped successfully\n";
        $cleaned = true;
    } catch (\Exception $e) {
        echo "⚠ Could not drop database (expected on shared hosting): {$e->getMessage()}\n";
        echo "ℹ On Hostinger, you must delete the database manually through hPanel:\n";
        echo "  1. Go to hPanel → Databases → MySQL Databases\n";
        echo "  2. Find database: {$databaseName}\n";
        echo "  3. Click Delete\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    
    if ($cleaned) {
        echo "✅ Cleanup completed successfully!\n";
        echo "You can now create a new tenant with ID: {$tenantId}\n";
    } else {
        echo "ℹ Nothing to clean up - tenant does not exist\n";
    }
    
} catch (\Exception $e) {
    DB::connection($centralConnection)->rollBack();
    echo "\n❌ Error during cleanup: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
