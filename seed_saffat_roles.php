<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

// Manually configure the tenant connection for clinic_102 (عيادة الصافات)
$databaseName = 'u876784197_tenant_saffat';

echo "🌱 Seeding roles and permissions for: {$databaseName}\n\n";

// Configure tenant connection
config([
    'database.connections.tenant.database' => $databaseName,
    'database.connections.tenant.username' => $databaseName,
    'database.connections.tenant.password' => env('TENANT_DB_PASSWORD'),
    'database.connections.tenant.host' => config('database.connections.mysql.host'),
    'database.connections.tenant.port' => config('database.connections.mysql.port'),
]);

// Purge and reconnect
DB::purge('tenant');

try {
    // Test connection
    DB::connection('tenant')->getPdo();
    echo "✅ Connected to database: {$databaseName}\n\n";
    
    // Run the seeder
    Artisan::call('db:seed', [
        '--class' => 'RoleAndPermissionSeeder',
        '--database' => 'tenant',
        '--force' => true,
    ]);
    
    echo Artisan::output();
    
    echo "\n✅ Roles and permissions seeded successfully!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
