<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Models\Clinic;
use Illuminate\Support\Facades\DB;

echo "🗑️  Deleting _alamal tenant and clinic...\n\n";

$tenantId = '_alamal';

try {
    // 1. Delete tenant
    $tenant = Tenant::find($tenantId);
    if ($tenant) {
        echo "   ✓ Found tenant: {$tenant->name}\n";
        $tenant->delete();
        echo "   ✓ Tenant deleted\n";
    } else {
        echo "   - Tenant not found\n";
    }
    
    // 2. Delete clinic from central database
    $clinic = Clinic::find($tenantId);
    if ($clinic) {
        echo "   ✓ Found clinic: {$clinic->name}\n";
        $clinic->delete();
        echo "   ✓ Clinic deleted\n";
    } else {
        echo "   - Clinic not found\n";
    }
    
    // 3. Drop tenant database
    $dbName = config('tenancy.database.prefix') . $tenantId;
    echo "   ⚠️  Attempting to drop database: {$dbName}\n";
    
    try {
        DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
        echo "   ✓ Database dropped\n";
    } catch (\Exception $e) {
        echo "   ⚠️  Could not drop database: {$e->getMessage()}\n";
    }
    
    echo "\n✅ Successfully deleted _alamal tenant!\n";
    echo "   You can now create a new tenant with name 'haider' or 'alamal'\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: {$e->getMessage()}\n\n";
    echo $e->getTraceAsString();
}
