<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== SEARCHING FOR _alamal RECORDS ===\n\n";

// Check tenants table
echo "1. Tenants table:\n";
$tenants = DB::table('tenants')->where('id', 'LIKE', '%alamal%')->get();
foreach ($tenants as $tenant) {
    echo "   Found: {$tenant->id} - {$tenant->name}\n";
}

// Check clinics table  
echo "\n2. Clinics table:\n";
$clinics = DB::table('clinics')->where('id', 'LIKE', '%alamal%')->get();
foreach ($clinics as $clinic) {
    echo "   Found: {$clinic->id} - {$clinic->name}\n";
}

// Check users
echo "\n3. Users with clinic_id containing 'alamal':\n";
$users = DB::table('users')->where('clinic_id', 'LIKE', '%alamal%')->get();
foreach ($users as $user) {
    echo "   Found: User ID {$user->id} - {$user->name} (clinic: {$user->clinic_id})\n";
}

if ($tenants->isEmpty() && $clinics->isEmpty() && $users->isEmpty()) {
    echo "\n✅ No _alamal records found in database.\n";
    echo "\nThe issue is likely that:\n";
    echo "1. Your server code hasn't been updated yet (run: git pull)\n";
    echo "2. There's a cached request in your API client (clear cache/restart)\n";
    echo "3. PHP opcache needs clearing (run: php artisan optimize:clear)\n";
} else {
    echo "\n❌ Found _alamal records. Deleting them...\n\n";
    
    // Delete in correct order (users first, then clinics, then tenants)
    $deletedUsers = DB::table('users')->where('clinic_id', 'LIKE', '%alamal%')->delete();
    echo "Deleted {$deletedUsers} user(s)\n";
    
    $deletedClinics = DB::table('clinics')->where('id', 'LIKE', '%alamal%')->delete();
    echo "Deleted {$deletedClinics} clinic(s)\n";
    
    $deletedTenants = DB::table('tenants')->where('id', 'LIKE', '%alamal%')->delete();
    echo "Deleted {$deletedTenants} tenant(s)\n";
    
    echo "\n✅ Cleanup complete!\n";
}

echo "\n=== NOW CHECKING WHAT'S AVAILABLE ===\n\n";
echo "All existing tenant IDs:\n";
$allTenants = DB::table('tenants')->pluck('id');
if ($allTenants->isEmpty()) {
    echo "  (none)\n";
} else {
    foreach ($allTenants as $id) {
        echo "  - {$id}\n";
    }
}
