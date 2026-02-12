<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;

echo "🌱 Seeding roles and permissions for ALL tenants...\n\n";

$tenants = Tenant::all();

if ($tenants->isEmpty()) {
    echo "⚠️  No tenants found.\n";
    exit(0);
}

$successCount = 0;
$failCount = 0;

foreach ($tenants as $tenant) {
    echo "Processing: {$tenant->id} ({$tenant->name})...\n";
    
    try {
        // Initialize tenant context
        tenancy()->initialize($tenant);
        
        // Run the seeder
        Illuminate\Support\Facades\Artisan::call('db:seed', [
            '--class' => 'RoleAndPermissionSeeder',
            '--database' => 'tenant',
            '--force' => true,
        ]);
        
        echo "  ✅ Success\n\n";
        $successCount++;
        
        // End tenant context
        tenancy()->end();
        
    } catch (\Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n\n";
        $failCount++;
        
        try {
            tenancy()->end();
        } catch (\Exception $e) {
            // Ignore
        }
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 Summary:\n";
echo "  ✅ Successful: {$successCount}\n";
echo "  ❌ Failed: {$failCount}\n";
echo "  📝 Total: " . ($successCount + $failCount) . "\n";
echo str_repeat("=", 50) . "\n";
