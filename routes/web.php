<?php

use App\Http\Controllers\TenantFileController;
use Illuminate\Support\Facades\Route;


// Public pages may only read explicit clinic-brand paths. All other tenant
// files, including patient images/documents, use a short-lived signed URL.
Route::get('file/tenant/{tenant}/public/{path}', [TenantFileController::class, 'public'])
    ->where('path', '.+')
    ->name('file.tenant.public');

Route::get('file/tenant/{tenant}/{path}', [TenantFileController::class, 'private'])
    ->where('path', '.+')
    ->middleware('signed')
    ->name('file.tenant.private');

Route::get('/', function () {
    return view('welcome');
});

