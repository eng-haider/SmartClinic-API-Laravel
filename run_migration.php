<?php

/**
 * Emergency migration runner for shared hosting without SSH access
 * 
 * SECURITY WARNING: Delete this file after running the migration!
 * 
 * To use: Visit https://your-domain.com/run_migration.php in your browser
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Artisan;

echo "<h1>Running Migration</h1>";
echo "<pre>";

try {
    // Run the specific migration
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_02_03_000001_add_db_credentials_to_tenants_table.php',
        '--force' => true,
    ]);
    
    echo Artisan::output();
    echo "\n✅ Migration completed successfully!\n";
    echo "\n⚠️  IMPORTANT: Delete this file (run_migration.php) for security!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
