<?php

/**
 * Image Not Found (404) Troubleshooting Script
 * 
 * This script helps diagnose why images return 404 even though they exist in the database.
 * 
 * Usage: php artisan tinker
 * Then: include 'diagnose_image_issue.php';
 */

use App\Models\Image;
use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "  IMAGE NOT FOUND (404) DIAGNOSIS\n";
echo "========================================\n\n";

// Get the image ID to check
$imageId = 5; // Change this to your image ID

echo "📊 Checking Image ID: {$imageId}\n\n";

// 1. Check central database
echo "1️⃣  Central Database Check:\n";
$centralImages = DB::connection('mysql')
    ->table('images')
    ->where('id', $imageId)
    ->get();

if ($centralImages->count() > 0) {
    echo "   ✅ Found in CENTRAL database\n";
    foreach ($centralImages as $img) {
        echo "      - ID: {$img->id}, Path: {$img->path}, Deleted: " . ($img->deleted_at ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "   ❌ NOT found in central database\n";
}

// 2. Check tenant databases
echo "\n2️⃣  Tenant Databases Check:\n";

$tenants = DB::table('tenants')->select('id', 'name', 'db_name')->get();

if ($tenants->count() === 0) {
    echo "   ⚠️  No tenants found\n";
} else {
    foreach ($tenants as $tenant) {
        $dbName = $tenant->db_name ?? (config('tenancy.database.prefix') . $tenant->id);
        
        try {
            $config = config('database.connections.mysql');
            config([
                'database.connections.temp_check.driver' => 'mysql',
                'database.connections.temp_check.host' => $config['host'],
                'database.connections.temp_check.port' => $config['port'],
                'database.connections.temp_check.database' => $dbName,
                'database.connections.temp_check.username' => $config['username'],
                'database.connections.temp_check.password' => $config['password'],
            ]);
            
            DB::purge('temp_check');
            
            $images = DB::connection('temp_check')
                ->table('images')
                ->where('id', $imageId)
                ->get();
            
            if ($images->count() > 0) {
                echo "   ✅ Found in TENANT: {$tenant->id} ({$tenant->name})\n";
                foreach ($images as $img) {
                    echo "      - ID: {$img->id}, Path: {$img->path}, Deleted: " . ($img->deleted_at ? 'YES' : 'NO') . "\n";
                }
            } else {
                echo "   ❌ NOT in tenant: {$tenant->id}\n";
            }
        } catch (\Exception $e) {
            echo "   ⚠️  Cannot access tenant {$tenant->id}: " . $e->getMessage() . "\n";
        }
    }
}

// 3. Check via Eloquent (current connection)
echo "\n3️⃣  Eloquent Query (Current Connection):\n";
$image = Image::find($imageId);

if ($image) {
    echo "   ✅ Found via Eloquent\n";
    echo "      - ID: {$image->id}\n";
    echo "      - Path: {$image->path}\n";
    echo "      - Type: {$image->type}\n";
    echo "      - Imageable: {$image->imageable_type} (ID: {$image->imageable_id})\n";
    echo "      - Deleted: " . ($image->deleted_at ? 'YES' : 'NO') . "\n";
} else {
    echo "   ❌ NOT found via Eloquent\n";
}

// 4. Check with soft deletes included
echo "\n4️⃣  Soft Deletes Check:\n";
$imageWithDeleted = Image::withTrashed()->find($imageId);

if ($imageWithDeleted) {
    echo "   ✅ Found (including soft-deleted)\n";
    echo "      - Deleted at: " . ($imageWithDeleted->deleted_at ?? 'NOT DELETED') . "\n";
} else {
    echo "   ❌ NOT found even with soft-deleted\n";
}

// 5. Check database connection
echo "\n5️⃣  Database Connection Info:\n";
echo "   Current DB: " . DB::connection()->getName() . "\n";
echo "   Database: " . DB::connection()->getDatabaseName() . "\n";
echo "   Tables: " . implode(', ', DB::getSchemaBuilder()->getTableListing()) . "\n";

// 6. Check if images table exists
echo "\n6️⃣  Images Table Check:\n";
if (DB::getSchemaBuilder()->hasTable('images')) {
    $columns = DB::getSchemaBuilder()->getColumnListing('images');
    echo "   ✅ Images table EXISTS\n";
    echo "   Columns: " . implode(', ', $columns) . "\n";
    
    $imageCount = DB::table('images')->count();
    echo "   Total images: {$imageCount}\n";
} else {
    echo "   ❌ Images table DOES NOT EXIST\n";
}

// 7. Try raw query
echo "\n7️⃣  Raw SQL Query:\n";
try {
    $result = DB::select("SELECT * FROM images WHERE id = ?", [$imageId]);
    if (count($result) > 0) {
        echo "   ✅ Raw query returned data\n";
        echo "   " . json_encode($result[0], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "   ❌ Raw query returned no rows\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Raw query failed: " . $e->getMessage() . "\n";
}

// 8. Check repository
echo "\n8️⃣  Repository Test:\n";
try {
    $repository = app(\App\Repositories\ImageRepository::class);
    $image = $repository->findById($imageId);
    if ($image) {
        echo "   ✅ Repository returned image\n";
    } else {
        echo "   ❌ Repository returned NULL\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️  Repository error: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "SUMMARY:\n";
echo "========================================\n\n";

$findings = [];

if (Image::find($imageId)) {
    $findings[] = "✅ Image exists and is accessible";
} else {
    $findings[] = "❌ Image NOT accessible via Eloquent";
}

if (DB::getSchemaBuilder()->hasTable('images')) {
    $findings[] = "✅ Images table exists";
} else {
    $findings[] = "❌ Images table does NOT exist";
}

echo implode("\n", $findings) . "\n\n";

echo "💡 Next Steps:\n";
echo "1. If NOT found: Check you're in the correct database/tenant context\n";
echo "2. If found: The image should work - check your API request headers\n";
echo "3. For API requests, always include X-Tenant-ID header\n";
echo "4. Check that the image_id parameter is correct in your API call\n\n";
