<?php

/**
 * Direct Database Query Test for Image Issue
 * 
 * This will run directly without Laravel ORM to see what's really in the database.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Image;

echo "\n========================================\n";
echo "  IMAGE DEBUG - DIRECT DATABASE CHECK\n";
echo "========================================\n\n";

$imageId = 5;

// 1. Raw SQL Query
echo "1️⃣  RAW SQL QUERY:\n";
try {
    $result = DB::select("SELECT * FROM images WHERE id = ?", [$imageId]);
    
    if (count($result) > 0) {
        echo "   ✅ Found " . count($result) . " record(s)\n\n";
        foreach ($result as $row) {
            echo "   Data:\n";
            foreach ((array)$row as $key => $value) {
                echo "   - {$key}: " . ($value === null ? 'NULL' : $value) . "\n";
            }
        }
    } else {
        echo "   ❌ No records found\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Query failed: " . $e->getMessage() . "\n";
}

// 2. Check with Laravel Query Builder
echo "\n2️⃣  LARAVEL QUERY BUILDER:\n";
try {
    $images = DB::table('images')->where('id', $imageId)->get();
    
    if ($images->count() > 0) {
        echo "   ✅ Found " . $images->count() . " record(s)\n\n";
        foreach ($images as $row) {
            echo "   Data:\n";
            foreach ((array)$row as $key => $value) {
                echo "   - {$key}: " . ($value === null ? 'NULL' : $value) . "\n";
            }
        }
    } else {
        echo "   ❌ No records found\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Query failed: " . $e->getMessage() . "\n";
}

// 3. Check with Eloquent (no soft deletes)
echo "\n3️⃣  ELOQUENT (without soft deletes):\n";
try {
    $image = Image::withoutTrashed()->find($imageId);
    
    if ($image) {
        echo "   ✅ Found image\n\n";
        echo "   Data:\n";
        foreach ($image->getAttributes() as $key => $value) {
            echo "   - {$key}: " . ($value === null ? 'NULL' : $value) . "\n";
        }
    } else {
        echo "   ❌ Not found\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Query failed: " . $e->getMessage() . "\n";
}

// 4. Check with Eloquent (with soft deletes)
echo "\n4️⃣  ELOQUENT (with soft deletes):\n";
try {
    $image = Image::find($imageId);
    
    if ($image) {
        echo "   ✅ Found image\n\n";
        echo "   Data:\n";
        foreach ($image->getAttributes() as $key => $value) {
            echo "   - {$key}: " . ($value === null ? 'NULL' : $value) . "\n";
        }
    } else {
        echo "   ❌ Not found\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Query failed: " . $e->getMessage() . "\n";
}

// 5. Check all images in database
echo "\n5️⃣  ALL IMAGES IN DATABASE:\n";
try {
    $all = DB::table('images')->get();
    echo "   Total images: {$all->count()}\n\n";
    
    if ($all->count() > 0) {
        foreach ($all as $img) {
            echo "   - ID: {$img->id}, Path: {$img->path}, Type: {$img->type}, " .
                 "Imageable: {$img->imageable_type} (ID: {$img->imageable_id}), " .
                 "Deleted: " . ($img->deleted_at ? 'YES' : 'NO') . "\n";
        }
    } else {
        echo "   No images found\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Query failed: " . $e->getMessage() . "\n";
}

// 6. Check if image is soft deleted
echo "\n6️⃣  SOFT DELETE STATUS:\n";
try {
    $image = DB::table('images')
        ->where('id', $imageId)
        ->first();
    
    if ($image) {
        echo "   - deleted_at: " . ($image->deleted_at ?? 'NULL (NOT deleted)') . "\n";
        if ($image->deleted_at) {
            echo "   ⚠️  Image IS soft-deleted!\n";
        } else {
            echo "   ✅ Image is NOT soft-deleted\n";
        }
    } else {
        echo "   ❌ Image not found in database\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Query failed: " . $e->getMessage() . "\n";
}

// 7. Check database name
echo "\n7️⃣  DATABASE INFO:\n";
try {
    $dbName = DB::select("SELECT DATABASE() as db")[0]->db;
    echo "   Current database: {$dbName}\n";
    
    // Check if images table exists
    $tables = DB::select("SHOW TABLES");
    $tableNames = array_column($tables, 'Tables_in_' . $dbName);
    
    if (in_array('images', $tableNames)) {
        echo "   ✅ Images table EXISTS\n";
        
        // Get table structure
        $columns = DB::select("SHOW COLUMNS FROM images");
        echo "   Columns: " . implode(', ', array_column($columns, 'Field')) . "\n";
    } else {
        echo "   ❌ Images table DOES NOT EXIST\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Failed: " . $e->getMessage() . "\n";
}

// 8. Test the repository
echo "\n8️⃣  IMAGE REPOSITORY TEST:\n";
try {
    $repository = app(\App\Repositories\ImageRepository::class);
    $image = $repository->findById($imageId);
    
    if ($image) {
        echo "   ✅ Repository found image\n";
    } else {
        echo "   ❌ Repository returned NULL\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Repository error: " . $e->getMessage() . "\n";
}

// 9. Check if Patient with ID 5 exists
echo "\n9️⃣  CHECK IMAGEABLE (Patient ID 5):\n";
try {
    $patient = DB::table('patients')
        ->where('id', 5)
        ->first();
    
    if ($patient) {
        echo "   ✅ Patient with ID 5 EXISTS\n";
    } else {
        echo "   ❌ Patient with ID 5 DOES NOT EXIST\n";
        echo "   ⚠️  This might cause issues with the morphTo relationship!\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️  Cannot check patients table: " . $e->getMessage() . "\n";
}

// 10. Check connection name
echo "\n🔟 CONNECTION CHECK:\n";
try {
    $connection = DB::connection()->getName();
    echo "   Using connection: {$connection}\n";
    
    // Get the actual database being used
    $currentDb = DB::select("SELECT DATABASE() as db")[0]->db;
    echo "   Database: {$currentDb}\n";
} catch (\Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "END OF DIAGNOSTIC\n";
echo "========================================\n\n";
