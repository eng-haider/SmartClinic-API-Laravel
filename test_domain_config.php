<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Testing Domain Configuration\n\n";

$centralDomains = config('tenancy.central_domains');

echo "Central Domains (domains that won't trigger tenant identification):\n";
foreach ($centralDomains as $domain) {
    echo "  ✓ {$domain}\n";
}

echo "\n";

$testDomain = 'api.smartclinic.software';
$isCentral = in_array($testDomain, $centralDomains);

echo "Testing domain: {$testDomain}\n";
echo "Is central domain? " . ($isCentral ? "✅ YES" : "❌ NO") . "\n";

if ($isCentral) {
    echo "\n✅ SUCCESS: {$testDomain} is configured as a central domain.\n";
    echo "   This means it will NOT try to identify a tenant by domain.\n";
    echo "   Use header-based identification (X-Tenant-ID) for API routes.\n";
} else {
    echo "\n❌ ERROR: {$testDomain} is NOT configured as a central domain.\n";
    echo "   The system will try to find a tenant with this domain and fail.\n";
    echo "   Add it to 'central_domains' in config/tenancy.php\n";
}

echo "\n";
