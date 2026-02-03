<?php

/**
 * Quick script to create a clinic and user in CENTRAL database
 * This allows you to test smart-login without full registration
 * 
 * Usage: php create_clinic_and_user.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "\n";
echo "==========================================\n";
echo "  CREATE CLINIC & USER (CENTRAL DB)\n";
echo "==========================================\n\n";

// Configuration - EDIT THESE VALUES
$clinicId = 'alamal';  // This will be used as tenant ID
$clinicName = 'Al-Amal Dental Clinic';
$clinicAddress = 'Baghdad, Iraq';
$clinicPhone = '07809876543';
$clinicEmail = 'alamal@clinic.com';

$userName = 'Dr. Ahmed';
$userPhone = '07801234567';  // Login phone
$userEmail = 'ahmed@alamal.com';
$userPassword = 'password123';  // Login password

echo "📋 Configuration:\n";
echo "   Clinic ID: {$clinicId}\n";
echo "   Clinic Name: {$clinicName}\n";
echo "   User Phone: {$userPhone}\n";
echo "   User Password: {$userPassword}\n\n";

// Get central connection
$centralConnection = config('tenancy.database.central_connection');
$centralDbName = config('database.connections.' . $centralConnection . '.database');

echo "🔧 Central Database: {$centralDbName}\n\n";

try {
    DB::connection($centralConnection)->beginTransaction();
    
    // 1. Check if clinic already exists
    $existingClinic = Clinic::on($centralConnection)->find($clinicId);
    if ($existingClinic) {
        echo "⚠️  Clinic '{$clinicId}' already exists!\n";
        echo "   Delete it first or use a different clinic ID.\n\n";
        DB::connection($centralConnection)->rollBack();
        exit(1);
    }
    
    // 2. Check if user already exists
    $existingUser = User::on($centralConnection)->where('phone', $userPhone)->first();
    if ($existingUser) {
        echo "⚠️  User with phone '{$userPhone}' already exists!\n";
        echo "   Delete it first or use a different phone number.\n\n";
        DB::connection($centralConnection)->rollBack();
        exit(1);
    }
    
    // 3. Create clinic
    $clinic = Clinic::on($centralConnection)->create([
        'id' => $clinicId,
        'name' => $clinicName,
        'address' => $clinicAddress,
        'phone' => $clinicPhone,
        'email' => $clinicEmail,
    ]);
    
    echo "✅ Clinic created successfully!\n";
    echo "   ID: {$clinic->id}\n";
    echo "   Name: {$clinic->name}\n\n";
    
    // 4. Create user
    $user = User::on($centralConnection)->create([
        'name' => $userName,
        'phone' => $userPhone,
        'email' => $userEmail,
        'password' => Hash::make($userPassword),
        'clinic_id' => $clinic->id,
        'is_active' => true,
    ]);
    
    echo "✅ User created successfully!\n";
    echo "   ID: {$user->id}\n";
    echo "   Name: {$user->name}\n";
    echo "   Phone: {$user->phone}\n\n";
    
    DB::connection($centralConnection)->commit();
    
    // 5. What's next?
    echo "==========================================\n";
    echo "  NEXT STEPS\n";
    echo "==========================================\n\n";
    
    $databaseName = config('tenancy.database.prefix') . $clinicId;
    
    echo "1️⃣  CREATE TENANT DATABASE in hPanel/cPanel:\n";
    echo "   Database Name: {$databaseName}\n";
    echo "   Database User: {$databaseName}\n";
    echo "   Password: (use TENANT_DB_PASSWORD from .env)\n\n";
    
    echo "2️⃣  TEST SMART-LOGIN:\n";
    echo "   POST /api/auth/smart-login\n";
    echo "   {\n";
    echo "     \"phone\": \"{$userPhone}\",\n";
    echo "     \"password\": \"{$userPassword}\"\n";
    echo "   }\n\n";
    
    echo "3️⃣  WHAT HAPPENS:\n";
    echo "   - Smart-login will detect clinic: {$clinicId}\n";
    echo "   - Will auto-create tenant record if needed\n";
    echo "   - Will run migrations on {$databaseName}\n";
    echo "   - Will create user in tenant database\n";
    echo "   - Will return JWT token\n\n";
    
    echo "✅ SUCCESS! You can now use smart-login.\n\n";
    
} catch (Exception $e) {
    DB::connection($centralConnection)->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}
