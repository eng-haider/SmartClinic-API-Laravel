<?php

/**
 * Migration Script: Add order and item_cost columns to case_categories table
 * Run this on production server to fix the missing columns issue
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Get tenant database configuration from environment
$tenantPrefix = env('TENANCY_DB_PREFIX', 'u876784197_tenant');
$tenantId = $argv[1] ?? null;

if (!$tenantId) {
    echo "❌ Error: Please provide tenant ID\n";
    echo "Usage: php migrate_case_categories_fix.php <tenant_id>\n";
    echo "Example: php migrate_case_categories_fix.php alamal\n";
    exit(1);
}

$tenantDatabase = $tenantPrefix . $tenantId;

echo "🔧 Migrating case_categories table in tenant database: {$tenantDatabase}\n\n";

try {
    // Configure tenant connection
    config(['database.connections.tenant' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST'),
        'port' => env('DB_PORT'),
        'database' => $tenantDatabase,
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ]]);

    DB::purge('tenant');
    DB::reconnect('tenant');

    // Check if columns already exist
    $hasOrder = Schema::connection('tenant')->hasColumn('case_categories', 'order');
    $hasItemCost = Schema::connection('tenant')->hasColumn('case_categories', 'item_cost');

    if ($hasOrder && $hasItemCost) {
        echo "✅ Columns 'order' and 'item_cost' already exist. Nothing to do.\n";
        exit(0);
    }

    // Add missing columns
    Schema::connection('tenant')->table('case_categories', function (Blueprint $table) use ($hasOrder, $hasItemCost) {
        if (!$hasOrder) {
            echo "  ➕ Adding 'order' column...\n";
            $table->integer('order')->default(0)->after('name');
        }
        
        if (!$hasItemCost) {
            echo "  ➕ Adding 'item_cost' column...\n";
            $table->integer('item_cost')->default(0)->after('order');
        }
    });

    echo "\n✅ Migration completed successfully!\n";
    echo "   Database: {$tenantDatabase}\n";
    echo "   Columns added: " . (!$hasOrder ? "'order' " : "") . (!$hasItemCost ? "'item_cost'" : "") . "\n";

} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
