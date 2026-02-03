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

// Get database name from command line argument
$tenantDatabase = $argv[1] ?? null;

if (!$tenantDatabase) {
    echo "❌ Error: Please provide the full tenant database name\n";
    echo "Usage: php migrate_case_categories_fix.php <tenant_database_name>\n";
    echo "Example: php migrate_case_categories_fix.php u876784197_tenant_alamal\n";
    echo "Example: php migrate_case_categories_fix.php tenant_clinic1\n";
    exit(1);
}

echo "🔧 Migrating case_categories table in database: {$tenantDatabase}\n\n";

try {
    // Configure tenant connection
    config(['database.connections.tenant_temp' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => $tenantDatabase,
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ]]);

    DB::purge('tenant_temp');
    DB::reconnect('tenant_temp');

    // Test connection
    DB::connection('tenant_temp')->getPdo();
    echo "✅ Connected to database: {$tenantDatabase}\n\n";

    // Check if table exists
    if (!Schema::connection('tenant_temp')->hasTable('case_categories')) {
        echo "❌ Error: Table 'case_categories' does not exist in database '{$tenantDatabase}'\n";
        exit(1);
    }

    // Check if columns already exist
    $hasOrder = Schema::connection('tenant_temp')->hasColumn('case_categories', 'order');
    $hasItemCost = Schema::connection('tenant_temp')->hasColumn('case_categories', 'item_cost');

    if ($hasOrder && $hasItemCost) {
        echo "✅ Columns 'order' and 'item_cost' already exist. Nothing to do.\n";
        exit(0);
    }

    // Show current table structure
    echo "📋 Current table structure:\n";
    $columns = DB::connection('tenant_temp')->select("SHOW COLUMNS FROM case_categories");
    foreach ($columns as $column) {
        echo "   - {$column->Field} ({$column->Type})\n";
    }
    echo "\n";

    // Add missing columns
    echo "🔄 Adding missing columns...\n";
    Schema::connection('tenant_temp')->table('case_categories', function (Blueprint $table) use ($hasOrder, $hasItemCost) {
        if (!$hasOrder) {
            echo "  ➕ Adding 'order' column...\n";
            $table->integer('order')->default(0)->after('name');
        }
        
        if (!$hasItemCost) {
            echo "  ➕ Adding 'item_cost' column...\n";
            $table->integer('item_cost')->default(0)->after($hasOrder ? 'order' : 'name');
        }
    });

    echo "\n✅ Migration completed successfully!\n";
    echo "   Database: {$tenantDatabase}\n";
    
    // Show updated structure
    echo "\n📋 Updated table structure:\n";
    $columns = DB::connection('tenant_temp')->select("SHOW COLUMNS FROM case_categories");
    foreach ($columns as $column) {
        echo "   - {$column->Field} ({$column->Type})\n";
    }

} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
