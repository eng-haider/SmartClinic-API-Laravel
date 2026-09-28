<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

echo "🦷 Updating tooth_colors format for all tenants...\n\n";

$tenants = Tenant::all();

foreach ($tenants as $tenant) {
    echo "Processing tenant: {$tenant->id} ({$tenant->name})...\n";
    
    $tenant->run(function () use ($tenant) {
        try {
            // Get the tooth_colors setting
            $setting = DB::table('clinic_settings')
                ->where('setting_key', 'tooth_colors')
                ->first();
            
            if (!$setting) {
                echo "  ⚠️  No tooth_colors setting found\n";
                return;
            }
            
            $currentValue = json_decode($setting->setting_value, true);
            
            // Check if already in new format (array of objects with 'name' key)
            if (isset($currentValue[0]['name'])) {
                echo "  ✅ Already in new format\n";
                return;
            }
            
            // Convert old format to new format
            $newFormat = [];
            foreach ($currentValue as $id => $color) {
                $newFormat[] = [
                    'id' => $id,
                    'name' => ucfirst(str_replace('_', ' ', $id)),
                    'color' => $color,
                ];
            }
            
            // Update the setting
            DB::table('clinic_settings')
                ->where('setting_key', 'tooth_colors')
                ->update([
                    'setting_value' => json_encode($newFormat),
                    'updated_at' => now(),
                ]);
            
            echo "  ✅ Updated to new format with " . count($newFormat) . " statuses\n";
            
        } catch (\Exception $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    });
}

echo "\n✅ All tenants processed!\n";
