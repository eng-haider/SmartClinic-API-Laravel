<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Str;

echo "🔍 Testing Tenant ID Generation\n\n";

$testNames = [
    'haider',
    'Haider',
    'HAIDER',
    'alamal',
    'Alamal',
    'ALAMAL',
];

foreach ($testNames as $name) {
    $baseId = Str::slug($name, '_');
    $tenantId = '_' . $baseId;
    
    echo "Name: '{$name}' → Generated ID: '{$tenantId}'\n";
}

echo "\n";
echo "❓ What name are you actually sending in your API request?\n";
echo "   Please check the 'name' field in your JSON body.\n\n";
