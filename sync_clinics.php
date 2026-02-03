<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenants = \App\Models\Tenant::all();

foreach ($tenants as $tenant) {
    $clinic = \App\Models\Clinic::find($tenant->id);
    
    if (!$clinic) {
        \App\Models\Clinic::create([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'address' => $tenant->address,
            'rx_img' => $tenant->rx_img,
            'whatsapp_template_sid' => $tenant->whatsapp_template_sid,
            'whatsapp_phone' => $tenant->whatsapp_phone,
            'logo' => $tenant->logo,
        ]);
        echo "✓ Created clinic for tenant: {$tenant->id}\n";
    } else {
        echo "- Clinic already exists for tenant: {$tenant->id}\n";
    }
}

echo "\nDone! Created clinic records for all tenants.\n";
