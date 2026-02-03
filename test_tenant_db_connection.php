<?php

/**
 * Test Tenant Database Connection
 * 
 * This script helps diagnose tenant database connection issues on shared hosting.
 * Run: php test_tenant_db_connection.php
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get database configuration
$host = env('DB_HOST', '127.0.0.1');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');
$port = env('DB_PORT', '3306');

echo "\n==========================================\n";
echo "TENANT DATABASE CONNECTION TEST\n";
echo "==========================================\n\n";

echo "Central Database Configuration:\n";
echo "  Host: {$host}\n";
echo "  Port: {$port}\n";
echo "  Username: {$username}\n";
echo "  Password: " . (empty($password) ? '(empty)' : str_repeat('*', strlen($password))) . "\n\n";

// Test tenant database
$tenantId = $argv[1] ?? '_alamal';
$dbPrefix = config('tenancy.database.prefix', 'tenant');
$tenantDbName = $dbPrefix . $tenantId;

echo "Testing Tenant Database:\n";
echo "  Tenant ID: {$tenantId}\n";
echo "  Database Name: {$tenantDbName}\n";
echo "  Expected Format: {$dbPrefix}[tenant_id]\n\n";

echo "Attempting to connect...\n";

try {
    // Try to connect directly using PDO
    $dsn = "mysql:host={$host};port={$port};dbname={$tenantDbName}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ SUCCESS! Connected to database '{$tenantDbName}'\n\n";
    
    // Get list of tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "⚠️  WARNING: Database exists but has NO TABLES.\n";
        echo "   You need to run migrations for this tenant.\n\n";
    } else {
        echo "📋 Tables found (" . count($tables) . "):\n";
        foreach ($tables as $table) {
            echo "   - {$table}\n";
        }
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ FAILED to connect to database '{$tenantDbName}'\n\n";
    echo "Error Details:\n";
    echo "  Code: " . $e->getCode() . "\n";
    echo "  Message: " . $e->getMessage() . "\n\n";
    
    echo "Possible Solutions:\n";
    echo "  1. ✓ Verify database exists in hPanel:\n";
    echo "     - Go to: Websites → Manage → Databases → MySQL Databases\n";
    echo "     - Look for: {$tenantDbName}\n\n";
    
    echo "  2. ✓ Check database user permissions:\n";
    echo "     - Ensure user '{$username}' has ALL PRIVILEGES on '{$tenantDbName}'\n";
    echo "     - In hPanel, check: Current Databases section\n";
    echo "     - User should be assigned to this database\n\n";
    
    echo "  3. ✓ Verify database name matches exactly:\n";
    echo "     - Hostinger prefixes all databases with username\n";
    echo "     - Full name should be: {$tenantDbName}\n";
    echo "     - Check for typos or extra underscores\n\n";
    
    echo "  4. ✓ Check .env configuration:\n";
    echo "     - TENANCY_DB_PREFIX=" . config('tenancy.database.prefix') . "\n";
    echo "     - Should be: u876784197_tenant (with your username prefix)\n\n";
    
    exit(1);
}

// List all databases accessible to this user
echo "==========================================\n";
echo "LISTING ALL ACCESSIBLE DATABASES\n";
echo "==========================================\n\n";

try {
    $dsn = "mysql:host={$host};port={$port}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Databases accessible to user '{$username}':\n\n";
    
    $tenantDatabases = [];
    $otherDatabases = [];
    
    foreach ($databases as $db) {
        if (strpos($db, $dbPrefix) === 0) {
            $tenantDatabases[] = $db;
        } else {
            $otherDatabases[] = $db;
        }
    }
    
    if (!empty($tenantDatabases)) {
        echo "📁 Tenant Databases (prefix: {$dbPrefix}):\n";
        foreach ($tenantDatabases as $db) {
            $highlight = ($db === $tenantDbName) ? ' ⬅️  TARGET' : '';
            echo "   - {$db}{$highlight}\n";
        }
        echo "\n";
    } else {
        echo "⚠️  No tenant databases found with prefix '{$dbPrefix}'\n\n";
    }
    
    if (!empty($otherDatabases)) {
        echo "📁 Other Databases:\n";
        foreach ($otherDatabases as $db) {
            echo "   - {$db}\n";
        }
        echo "\n";
    }
    
    // Check if target database exists
    if (!in_array($tenantDbName, $databases)) {
        echo "\n❌ TARGET DATABASE NOT FOUND: {$tenantDbName}\n";
        echo "   You need to create this database in hPanel first!\n\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Could not list databases: " . $e->getMessage() . "\n\n";
}

echo "==========================================\n";
echo "TEST COMPLETE\n";
echo "==========================================\n\n";
