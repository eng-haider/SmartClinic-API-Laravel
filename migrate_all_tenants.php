<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$tenants = Tenant::all();
$prefix = config('tenancy.database.prefix', 'tenant');

echo "Checking and migrating tenant databases...\n\n";

foreach ($tenants as $tenant) {
    $dbName = $prefix . $tenant->id;
    
    // Check if database exists
    $dbExists = DB::select("SHOW DATABASES LIKE '{$dbName}'");
    
    if (empty($dbExists)) {
        echo "⚠️  Skipping {$tenant->id} - database does not exist\n";
        continue;
    }
    
    try {
        echo "Migrating: {$tenant->id}...";
        $tenant->run(function () {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        });
        echo " ✓\n";
    } catch (\Exception $e) {
        echo " ✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n✓ Done!\n";
