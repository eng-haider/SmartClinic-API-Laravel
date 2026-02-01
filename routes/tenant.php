<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Middleware\InitializeTenancyByHeader;

// Controllers
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseCategoryController;
use App\Http\Controllers\ClinicExpenseController;
use App\Http\Controllers\ClinicExpenseCategoryController;
use App\Http\Controllers\ClinicSettingController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\SecretaryController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\PublicPatientController;
use App\Http\Controllers\PatientPublicProfileController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

// ============================================
// TENANT API ROUTES (Initialized by Header)
// Use X-Tenant-ID or X-Clinic-ID header
// ============================================
Route::middleware([
    'api',
    InitializeTenancyByHeader::class,
])->prefix('api/tenant')->group(function () {
    
    // Auth routes
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    
    Route::middleware('jwt')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);
    });

    // Patient routes
    Route::middleware('jwt')->group(function () {
        Route::apiResource('patients', PatientController::class);
        Route::get('patients/search/phone/{phone}', [PatientController::class, 'searchByPhone']);
        Route::put('patients/{id}/tooth-details', [PatientController::class, 'updateToothDetails']);
        
        // Public Profile Management
        Route::get('patients/{id}/public-profile', [PatientPublicProfileController::class, 'getPublicProfile']);
        Route::post('patients/{id}/public-profile/enable', [PatientPublicProfileController::class, 'enablePublicProfile']);
        Route::post('patients/{id}/public-profile/disable', [PatientPublicProfileController::class, 'disablePublicProfile']);
        Route::post('patients/{id}/public-profile/regenerate-token', [PatientPublicProfileController::class, 'regenerateToken']);
    });

    // Case routes
    Route::middleware('jwt')->group(function () {
        Route::apiResource('cases', CaseController::class);
    });

    // Case category routes
    Route::middleware('jwt')->group(function () {
        Route::apiResource('case-categories', CaseCategoryController::class);
    });

    // Reservation routes
    Route::middleware('jwt')->group(function () {
        Route::apiResource('reservations', ReservationController::class);
    });

    // Recipe routes
    Route::middleware('jwt')->group(function () {
        Route::apiResource('recipes', RecipeController::class);
    });

    // Bill routes
    Route::middleware('jwt')->group(function () {
        Route::apiResource('bills', BillController::class);
        Route::patch('bills/{id}/mark-paid', [BillController::class, 'markAsPaid']);
        Route::patch('bills/{id}/mark-unpaid', [BillController::class, 'markAsUnpaid']);
        Route::get('bills/patient/{patientId}', [BillController::class, 'byPatient']);
    });

    // Clinic expense routes
    Route::middleware('jwt')->group(function () {
        Route::apiResource('clinic-expense-categories', ClinicExpenseCategoryController::class);
        Route::apiResource('clinic-expenses', ClinicExpenseController::class);
    });

    // Note routes
    Route::middleware('jwt')->group(function () {
        Route::apiResource('notes', NoteController::class);
    });

    // Image routes
    Route::middleware('jwt')->group(function () {
        Route::apiResource('images', ImageController::class);
    });

    // Clinic settings routes
    Route::middleware('jwt')->prefix('clinic-settings')->group(function () {
        Route::get('/', [ClinicSettingController::class, 'index']);
        Route::get('/{key}', [ClinicSettingController::class, 'show']);
        Route::put('/{key}', [ClinicSettingController::class, 'update']);
        Route::post('/bulk-update', [ClinicSettingController::class, 'updateBulk']);
    });
});

// ============================================
// TENANT WEB ROUTES (Initialized by Domain/Subdomain)
// For web-based access
// ============================================
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return response()->json([
            'success' => true,
            'message' => 'Welcome to ' . tenant('name'),
            'message_ar' => 'مرحباً بك في ' . tenant('name'),
            'tenant_id' => tenant('id'),
        ]);
    });
});

