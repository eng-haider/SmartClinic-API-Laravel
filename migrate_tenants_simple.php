<?php

/**
 * Simple Notification Migration Script
 * 
 * This script migrates notification tables to all tenant databases
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;

echo "🚀 Starting tenant migrations...\n\n";

$tenants = Tenant::all();

foreach ($tenants as $tenant) {
    echo "Migrating tenant: {$tenant->id}... ";
    
    try {
        tenancy()->initialize($tenant);
        
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
        
        echo "✅ Done\n";
        
        tenancy()->end();
    } catch (\Exception $e) {
        echo "❌ Error: {$e->getMessage()}\n";
        tenancy()->end();
    }
}

echo "\n✅ All migrations completed!\n";
