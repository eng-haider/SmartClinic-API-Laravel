<?php

use Illuminate\Support\Facades\Route;


Route::get('file/tenant/{tenant}/{path}', function ($tenant, $path) {
    // Tenancy stores files at: storage/tenant{tenantId}/app/public/{path}
    // e.g. tenant "_haider" → storage/tenant_haider/app/public/...
    // The suffix_base in config/tenancy.php is "tenant", so it appends "tenant" + tenantId
    $tenantStorageDir = storage_path('tenant' . $tenant . '/app/public/' . $path);

    if (!file_exists($tenantStorageDir)) {
        abort(404, "File not found");
    }

    $mimeType = mime_content_type($tenantStorageDir) ?: 'application/octet-stream';

    return response()->file($tenantStorageDir, [
        'Cache-Control' => 'public, max-age=31536000',
        'Content-Type'  => $mimeType,
    ]);
})->where('path', '.+')->name('file.tenant');

Route::get('/', function () {
    return view('welcome');
});


