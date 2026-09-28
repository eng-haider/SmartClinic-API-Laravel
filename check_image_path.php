<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;

// Initialize tenant context
$tenant = Tenant::where('id', 'haider')->first();

if (!$tenant) {
    echo "Tenant 'haider' not found\n";
    exit(1);
}

echo "=== Initializing Tenant: haider ===\n";
$tenant->run(function () {
    echo "Tenant initialized successfully\n\n";
    
    // Get image
    $image = Image::find(5);
    
    if (!$image) {
        echo "Image with ID 5 not found\n";
        return;
    }
    
    echo "=== Image Details ===\n";
    echo "ID: {$image->id}\n";
    echo "Path (from DB): {$image->path}\n";
    echo "Disk: {$image->disk}\n";
    echo "Type: {$image->type}\n";
    echo "Mime Type: {$image->mime_type}\n\n";
    
    echo "=== Storage Information ===\n";
    echo "Storage Path: " . storage_path('app/public') . "\n";
    echo "Full File Path: " . storage_path('app/public/' . $image->path) . "\n";
    
    // Check if file exists
    $fullPath = storage_path('app/public/' . $image->path);
    if (file_exists($fullPath)) {
        echo "✅ File EXISTS at: $fullPath\n";
    } else {
        echo "❌ File NOT FOUND at: $fullPath\n";
    }
    
    echo "\n=== URL Generation ===\n";
    echo "Generated URL (via model): {$image->url}\n";
    echo "Storage disk URL: " . Storage::disk($image->disk)->url($image->path) . "\n";
    
    echo "\n=== What should it be? ===\n";
    echo "The URL should be: " . config('app.url') . "/storage/{$image->path}\n";
});
