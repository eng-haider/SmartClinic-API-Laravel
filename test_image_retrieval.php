<?php

/**
 * Quick Test: Check if image exists and why findById fails
 * 
 * Run this in artisan tinker after setting your tenant context
 */

use App\Models\Image;
use App\Repositories\ImageRepository;
use Illuminate\Support\Facades\DB;

echo "\n=== IMAGE RETRIEVAL TEST ===\n\n";

$imageId = 5;

// Test 1: Raw database query
echo "Test 1: Raw Query\n";
$raw = DB::table('images')->where('id', $imageId)->first();
if ($raw) {
    echo "✅ Raw query found image\n";
    echo "   ID: {$raw->id}, Path: {$raw->path}, Deleted: " . ($raw->deleted_at ? 'YES' : 'NO') . "\n";
} else {
    echo "❌ Raw query: NOT FOUND\n";
}

// Test 2: Query builder get()
echo "\nTest 2: Query Builder get()\n";
$builder = DB::table('images')->where('id', $imageId)->get();
if ($builder->count() > 0) {
    echo "✅ Query builder found " . $builder->count() . " record(s)\n";
} else {
    echo "❌ Query builder: NOT FOUND\n";
}

// Test 3: Eloquent withoutTrashed
echo "\nTest 3: Eloquent (withoutTrashed)\n";
$eloquentNoTrashed = Image::withoutTrashed()->find($imageId);
if ($eloquentNoTrashed) {
    echo "✅ Found via Eloquent (without trashed)\n";
} else {
    echo "❌ Eloquent (without trashed): NOT FOUND\n";
}

// Test 4: Eloquent default (with soft deletes)
echo "\nTest 4: Eloquent (default - respects soft deletes)\n";
$eloquent = Image::find($imageId);
if ($eloquent) {
    echo "✅ Found via Eloquent\n";
} else {
    echo "❌ Eloquent: NOT FOUND\n";
    echo "   This means: Either image doesn't exist OR is soft-deleted\n";
}

// Test 5: Eloquent with trashed
echo "\nTest 5: Eloquent (withTrashed)\n";
$eloquentTrashed = Image::withTrashed()->find($imageId);
if ($eloquentTrashed) {
    echo "✅ Found with trashed: {$eloquentTrashed->id}\n";
    echo "   Deleted at: " . ($eloquentTrashed->deleted_at ?? 'NOT DELETED') . "\n";
} else {
    echo "❌ Eloquent (with trashed): NOT FOUND\n";
    echo "   This means: Image DEFINITELY doesn't exist in database\n";
}

// Test 6: Repository
echo "\nTest 6: ImageRepository\n";
$repo = app(ImageRepository::class);
$fromRepo = $repo->findById($imageId);
if ($fromRepo) {
    echo "✅ Repository found image\n";
} else {
    echo "❌ Repository: NOT FOUND\n";
}

// Test 7: Check all images
echo "\nTest 7: All Images in Database\n";
$all = Image::withTrashed()->get();
echo "Total images (including soft-deleted): " . $all->count() . "\n";
foreach ($all as $img) {
    echo "   - ID {$img->id}: {$img->path} (Deleted: " . ($img->deleted_at ? 'YES' : 'NO') . ")\n";
}

// Test 8: Current connection
echo "\nTest 8: Database Connection\n";
echo "Connection name: " . DB::connection()->getName() . "\n";
echo "Database: " . DB::connection()->getDatabaseName() . "\n";

// Test 9: Check if problem is with morphable models
echo "\nTest 9: Check Imageable Model\n";
$img = Image::withTrashed()->find($imageId);
if ($img) {
    echo "Image data:\n";
    echo "   imageable_type: {$img->imageable_type}\n";
    echo "   imageable_id: {$img->imageable_id}\n";
    
    // Try to load the imageable
    try {
        $imageable = $img->imageable;
        if ($imageable) {
            echo "   ✅ Imageable model found (Patient ID {$imageable->id})\n";
        } else {
            echo "   ⚠️  Imageable is NULL - model doesn't exist!\n";
            echo "      This could cause issues in queries\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Error loading imageable: " . $e->getMessage() . "\n";
    }
} else {
    echo "Image not found in database\n";
}

echo "\n=== END TEST ===\n\n";
