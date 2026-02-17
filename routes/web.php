<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::get('/', function () {
    return view('welcome');
});

// Serve tenant storage files
Route::get('/storage/tenant_{tenant}/{path}', function ($tenant, $path) {
    $fullPath = "tenant_{$tenant}/app/public/{$path}";
    
    // Check if file exists in public disk storage
    if (!Storage::disk('public')->exists($fullPath)) {
        abort(404, "File not found: {$fullPath}");
    }
    
    $file = Storage::disk('public')->path($fullPath);
    
    if (!file_exists($file)) {
        abort(404, "Physical file not found: {$file}");
    }
    
    return response()->file($file, [
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.+');

