<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;

// Get tenant_id from command line argument
$tenantId = $argv[1] ?? null;

if (!$tenantId) {
    echo "❌ Usage: php seed_tenant_roles.php <tenant_id>\n";
    echo "Example: php seed_tenant_roles.php clinic_1\n";
    exit(1);
}

echo "🌱 Seeding roles and permissions for tenant: {$tenantId}\n\n";

$tenant = Tenant::find($tenantId);

if (!$tenant) {
    echo "❌ Tenant not found: {$tenantId}\n";
    exit(1);
}

// Initialize tenant context
tenancy()->initialize($tenant);

echo "✅ Tenant initialized: {$tenant->name}\n";
echo "📊 Database: {$tenant->db_name}\n\n";

try {
    // Run the seeder
    Illuminate\Support\Facades\Artisan::call('db:seed', [
        '--class' => 'RoleAndPermissionSeeder',
        '--database' => 'tenant',
        '--force' => true,
    ]);
    
    $output = Illuminate\Support\Facades\Artisan::output();
    echo $output;
    
    echo "\n✅ Roles and permissions seeded successfully!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// End tenant context
tenancy()->end();

echo "✅ Done!\n";
