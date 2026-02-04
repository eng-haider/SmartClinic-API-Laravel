<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CHECKING FOR _alamal TENANT ===\n\n";

// Check tenants table
echo "1. Checking tenants table:\n";
$tenants = DB::table('tenants')->where('id', '_alamal')->get();
echo "   Found " . $tenants->count() . " tenant(s) with ID '_alamal'\n";
foreach ($tenants as $tenant) {
    echo "   - ID: {$tenant->id}, Name: {$tenant->name}\n";
}

// Check clinics table
echo "\n2. Checking clinics table:\n";
$clinics = DB::table('clinics')->where('id', '_alamal')->get();
echo "   Found " . $clinics->count() . " clinic(s) with ID '_alamal'\n";
foreach ($clinics as $clinic) {
    echo "   - ID: {$clinic->id}, Name: {$clinic->name}\n";
}

// Check all clinics
echo "\n3. All clinics in database:\n";
$allClinics = DB::table('clinics')->get(['id', 'name']);
if ($allClinics->isEmpty()) {
    echo "   No clinics found\n";
} else {
    foreach ($allClinics as $clinic) {
        echo "   - ID: {$clinic->id}, Name: {$clinic->name}\n";
    }
}

// Check all tenants
echo "\n4. All tenants in database:\n";
$allTenants = DB::table('tenants')->get(['id', 'name']);
if ($allTenants->isEmpty()) {
    echo "   No tenants found\n";
} else {
    foreach ($allTenants as $tenant) {
        echo "   - ID: {$tenant->id}, Name: {$tenant->name}\n";
    }
}

echo "\n=== CLEANUP OPTIONS ===\n";
echo "To delete _alamal tenant, run:\n";
echo "  php cleanup_failed_tenant.php _alamal\n\n";
echo "Or manually delete:\n";
echo "  DELETE FROM tenants WHERE id = '_alamal';\n";
echo "  DELETE FROM clinics WHERE id = '_alamal';\n";
echo "  DELETE FROM users WHERE clinic_id = '_alamal';\n";
