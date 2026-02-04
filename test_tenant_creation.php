<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTING TENANT CREATION FOR 'haider' ===\n\n";

// Simulate the request data you're sending
$requestData = [
    'id' => null,
    'name' => 'haider',
    'address' => 'haider',
    'rx_img' => null,
    'whatsapp_template_sid' => null,
    'whatsapp_phone' => null,
    'logo' => null,
    'user_name' => 'haider',
    'user_phone' => '07700281899',
    'user_email' => null,
    'user_password' => '12345678',
];

echo "1. Request Data:\n";
print_r($requestData);

// Simulate ID generation
$clinicName = $requestData['name'];
$baseId = \Illuminate\Support\Str::slug($clinicName, '_');
echo "\n2. Generated base ID from name '{$clinicName}': {$baseId}\n";

// Check what the actual tenant ID would be
$prefix = config('tenancy.database.prefix', 'tenant');
$attemptId = '_' . $baseId;
echo "3. Tenant ID that will be generated: {$attemptId}\n";
echo "4. Database name that will be created: {$prefix}{$attemptId}\n";

// Check if this ID exists
echo "\n5. Checking if tenant exists:\n";
$existingTenant = DB::table('tenants')->where('id', $attemptId)->first();
if ($existingTenant) {
    echo "   ❌ TENANT EXISTS: {$attemptId}\n";
    echo "   Name: {$existingTenant->name}\n";
} else {
    echo "   ✅ Tenant ID is available: {$attemptId}\n";
}

$existingClinic = DB::table('clinics')->where('id', $attemptId)->first();
if ($existingClinic) {
    echo "   ❌ CLINIC EXISTS: {$attemptId}\n";
    echo "   Name: {$existingClinic->name}\n";
} else {
    echo "   ✅ Clinic ID is available: {$attemptId}\n";
}

// Check database
$dbName = $prefix . $attemptId;
$dbExists = DB::select("SHOW DATABASES LIKE '{$dbName}'");
if (!empty($dbExists)) {
    echo "   ❌ DATABASE EXISTS: {$dbName}\n";
} else {
    echo "   ✅ Database name is available: {$dbName}\n";
}

echo "\n6. READY TO CREATE TENANT: {$attemptId}\n";
echo "\n=== To create this tenant, make a POST request to: ===\n";
echo "URL: https://api.smartclinic.software/api/tenants\n";
echo "Data:\n";
echo json_encode($requestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";
echo "⚠️  IMPORTANT: Make sure you're NOT sending 'id' field or send it as null\n";
echo "⚠️  The system will automatically generate: {$attemptId}\n";
