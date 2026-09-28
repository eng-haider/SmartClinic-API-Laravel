<?php

/**
 * Notification System Migration Helper
 * 
 * Run this script to migrate the notification tables to all tenant databases
 * 
 * Usage: php migrate_notifications.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Stancl\Tenancy\Facades\Tenancy;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;

echo "\n";
echo "========================================\n";
echo "Notification System Migration Tool\n";
echo "========================================\n\n";

// Get all tenants
$tenants = Tenant::all();
$totalTenants = $tenants->count();

if ($totalTenants === 0) {
    echo "⚠️  No tenants found in the database.\n";
    echo "   Create a tenant first before running migrations.\n\n";
    exit(1);
}

echo "📋 Found {$totalTenants} tenant(s) to migrate:\n\n";

foreach ($tenants as $tenant) {
    $tenantName = $tenant->data['name'] ?? 'No name';
    echo "  • {$tenant->id} ({$tenantName})\n";
}

echo "\n";
echo "This will run the following migrations:\n";
echo "  1. Create notifications table\n";
echo "  2. Add onesignal_player_id to users table\n\n";

echo "Do you want to continue? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'yes' && strtolower($line) !== 'y') {
    echo "\n❌ Migration cancelled.\n\n";
    exit(0);
}

echo "\n";
echo "========================================\n";
echo "Starting Migrations\n";
echo "========================================\n\n";

$successCount = 0;
$failedCount = 0;
$errors = [];

foreach ($tenants as $tenant) {
    echo "🔄 Migrating tenant: {$tenant->id}...\n";
    
    try {
        // Initialize tenant
        tenancy()->initialize($tenant);
        
        // Run migrations
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
        
        $output = Artisan::output();
        
        // Check if migrations were successful
        if (str_contains($output, 'Nothing to migrate')) {
            echo "   ✅ Already migrated (up to date)\n\n";
        } elseif (str_contains($output, 'Migrated')) {
            echo "   ✅ Successfully migrated\n\n";
        } else {
            echo "   ℹ️  Completed\n\n";
        }
        
        $successCount++;
        
        // End tenancy
        tenancy()->end();
        
    } catch (\Exception $e) {
        echo "   ❌ Error: {$e->getMessage()}\n\n";
        $failedCount++;
        $errors[] = [
            'tenant' => $tenant->id,
            'error' => $e->getMessage(),
        ];
        
        // End tenancy on error
        tenancy()->end();
    }
}

echo "========================================\n";
echo "Migration Summary\n";
echo "========================================\n\n";
echo "Total Tenants: {$totalTenants}\n";
echo "✅ Successful: {$successCount}\n";
echo "❌ Failed: {$failedCount}\n\n";

if ($failedCount > 0) {
    echo "Failed Migrations:\n\n";
    foreach ($errors as $error) {
        echo "  • Tenant: {$error['tenant']}\n";
        echo "    Error: {$error['error']}\n\n";
    }
}

echo "========================================\n\n";

if ($failedCount > 0) {
    echo "⚠️  Some migrations failed. Please check the errors above.\n";
    echo "   You can try running migrations manually for failed tenants:\n";
    echo "   php artisan tenants:run migrate --tenants=TENANT_ID\n\n";
    exit(1);
} else {
    echo "🎉 All migrations completed successfully!\n\n";
    echo "Next Steps:\n";
    echo "  1. Test the notification system using the API endpoints\n";
    echo "  2. Integrate OneSignal in your mobile/web app\n";
    echo "  3. Send test notifications to verify everything works\n\n";
    echo "Documentation:\n";
    echo "  • Full Guide: NOTIFICATION_SYSTEM_DOCUMENTATION.md\n";
    echo "  • Quick Start: NOTIFICATION_QUICK_REFERENCE.md\n";
    echo "  • Examples: app/Examples/NotificationExamples.php\n\n";
    exit(0);
}
