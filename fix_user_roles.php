<?php

/**
 * Fix existing user roles - assign clinic_super_doctor role to users without roles
 * Run this if you already have users created without roles
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

echo "\n";
echo "==========================================\n";
echo "  FIX USER ROLES IN TENANT DATABASES\n";
echo "==========================================\n\n";

// Get all tenants
$tenants = Tenant::all();

if ($tenants->isEmpty()) {
    echo "⚠️  No tenants found.\n\n";
    exit(0);
}

echo "Found " . $tenants->count() . " tenant(s)\n\n";

foreach ($tenants as $tenant) {
    echo "📊 Tenant: {$tenant->id} ({$tenant->name})\n";
    
    try {
        // Initialize tenant context
        tenancy()->initialize($tenant);
        
        // Get all users without roles
        $users = User::doesntHave('roles')->get();
        
        if ($users->isEmpty()) {
            echo "   ✅ All users have roles assigned\n\n";
            continue;
        }
        
        echo "   Found {$users->count()} user(s) without roles\n";
        
        foreach ($users as $user) {
            echo "   👤 User: {$user->name} (ID: {$user->id}, Phone: {$user->phone})\n";
            
            try {
                // Assign clinic_super_doctor role
                $user->assignRole('clinic_super_doctor');
                echo "      ✅ Assigned 'clinic_super_doctor' role\n";
            } catch (Exception $e) {
                echo "      ❌ Failed to assign role: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
        
    } catch (Exception $e) {
        echo "   ❌ Error processing tenant: " . $e->getMessage() . "\n\n";
    }
}

echo "==========================================\n";
echo "✅ Role assignment completed!\n\n";

echo "🔄 Now test your login again:\n";
echo "   The 'roles' and 'permissions' arrays should be populated.\n\n";
