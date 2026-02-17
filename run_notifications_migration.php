<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;

echo "🔔 Running notifications migration on all tenants...\n\n";

$tenants = Tenant::all();

if ($tenants->isEmpty()) {
    echo "❌ No tenants found.\n";
    exit(1);
}

foreach ($tenants as $tenant) {
    echo "📦 Tenant: {$tenant->id} (DB: {$tenant->db_name})\n";

    try {
        $tenant->run(function () use ($tenant) {
            Artisan::call('migrate', [
                '--path'     => 'database/migrations/tenant',
                '--realpath' => false,
                '--force'    => true,
            ]);
            echo "   ✅ " . trim(Artisan::output()) . "\n";
        });
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Done!\n";
