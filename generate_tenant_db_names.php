#!/usr/bin/env php
<?php

/**
 * Tenant Database Name Generator
 * 
 * This script helps you determine the correct database names
 * that need to be created in your hosting panel for new tenants.
 * 
 * Usage:
 *   php generate_tenant_db_names.php clinic1 clinic2 clinic3
 *   php generate_tenant_db_names.php "My Clinic Name"
 */

// Load Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Str;

// Get tenant names from command line arguments
$tenantNames = array_slice($argv, 1);

if (empty($tenantNames)) {
    echo "\n❌ No tenant names provided!\n\n";
    echo "Usage:\n";
    echo "  php generate_tenant_db_names.php \"Clinic Name 1\" \"Clinic Name 2\"\n";
    echo "  php generate_tenant_db_names.php clinic1 clinic2 clinic3\n\n";
    exit(1);
}

// Get configuration
$prefix = config('tenancy.database.prefix', 'tenant');
$dbUsername = env('DB_USERNAME', 'root');

// Detect if we're on shared hosting (username has underscore pattern like u123_name)
$isSharedHosting = preg_match('/^[a-z]\d+_/', $dbUsername);
$hostingPrefix = '';

if ($isSharedHosting) {
    // Extract the hosting prefix (e.g., u876784197_)
    $parts = explode('_', $dbUsername);
    if (count($parts) >= 2) {
        $hostingPrefix = $parts[0] . '_';
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║          Tenant Database Name Generator                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Configuration:\n";
echo "  • Database Prefix: {$prefix}\n";
echo "  • Hosting Type: " . ($isSharedHosting ? "Shared Hosting" : "VPS/Local") . "\n";
if ($hostingPrefix) {
    echo "  • Hosting Prefix: {$hostingPrefix}\n";
}
echo "\n";

echo "─────────────────────────────────────────────────────────────────\n\n";

$results = [];

foreach ($tenantNames as $index => $name) {
    $name = trim($name);
    if (empty($name)) continue;
    
    // Generate tenant ID (same logic as in TenantController)
    $baseId = Str::slug($name, '_');
    
    if (empty($baseId) || is_numeric($baseId) || strlen($baseId) < 2) {
        $baseId = preg_replace('/[^a-z0-9]+/i', '_', strtolower($name));
        if (empty($baseId) || strlen($baseId) < 2) {
            $baseId = 'clinic_' . Str::lower(Str::random(6));
        }
    }
    
    $tenantId = '_' . $baseId;
    $databaseName = $prefix . $tenantId;
    $fullDatabaseName = $hostingPrefix . $databaseName;
    
    $results[] = [
        'number' => $index + 1,
        'clinic_name' => $name,
        'tenant_id' => $tenantId,
        'database_name' => $databaseName,
        'full_database_name' => $fullDatabaseName,
    ];
}

// Display results
foreach ($results as $result) {
    echo "Tenant #{$result['number']}: {$result['clinic_name']}\n";
    echo "├─ Tenant ID (for API): {$result['tenant_id']}\n";
    echo "├─ Database Name: {$result['database_name']}\n";
    
    if ($isSharedHosting) {
        echo "└─ Create in Hosting Panel: {$result['full_database_name']}\n";
        echo "   ⚠️  Use this exact name in your hosting panel!\n";
    } else {
        echo "└─ Database will be auto-created: {$result['database_name']}\n";
    }
    
    echo "\n";
}

echo "─────────────────────────────────────────────────────────────────\n\n";

if ($isSharedHosting) {
    echo "📋 Next Steps for Shared Hosting:\n\n";
    echo "1. Log into your hosting panel (hPanel/cPanel)\n";
    echo "2. Go to: Databases → MySQL Databases\n";
    echo "3. Create each database listed above with the exact name\n";
    echo "4. Ensure your database user has ALL PRIVILEGES\n";
    echo "5. Call the tenant creation API with the 'Tenant ID'\n\n";
    
    echo "Example API Request:\n";
    echo "POST /api/tenants\n";
    echo "Content-Type: application/json\n\n";
    if (!empty($results)) {
        echo json_encode([
            'id' => $results[0]['tenant_id'],
            'name' => $results[0]['clinic_name'],
            'address' => 'Optional address',
            'user_name' => 'Admin Name',
            'user_phone' => '1234567890',
            'user_email' => 'admin@example.com',
            'user_password' => 'SecurePassword123'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "\n";
    }
} else {
    echo "✅ VPS/Local Environment Detected\n\n";
    echo "Databases will be created automatically when you call the API.\n";
    echo "Make sure TENANCY_AUTO_CREATE_DB=true in your .env file.\n\n";
}

echo "\n";
