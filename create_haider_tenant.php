<?php

/**
 * Script to create the _haider tenant with proper data
 * This will make an API call to create the tenant
 */

$apiUrl = 'https://api.smartclinic.software/api/tenants';

$data = [
    'name' => 'haider',
    'address' => 'haider',
    'user_name' => 'haider',
    'user_phone' => '07700281899',
    'user_password' => '12345678',
];

echo "=== CREATING HAIDER TENANT ===\n\n";
echo "API URL: {$apiUrl}\n";
echo "Data:\n";
print_r($data);
echo "\n";

// Make the API request
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n";
echo "Response:\n";
echo $response;
echo "\n\n";

$responseData = json_decode($response, true);

if ($responseData && isset($responseData['success']) && $responseData['success']) {
    echo "✅ SUCCESS! Tenant created.\n";
    if (isset($responseData['data']['tenant']['id'])) {
        echo "Tenant ID: " . $responseData['data']['tenant']['id'] . "\n";
    }
} else {
    echo "❌ FAILED to create tenant.\n";
    if ($responseData && isset($responseData['message'])) {
        echo "Error: " . $responseData['message'] . "\n";
    }
}
