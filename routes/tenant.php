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
use App\Http\Controllers\Report\BillReportController;
use App\Http\Controllers\Report\DashboardReportController;
use App\Http\Controllers\Report\PatientReportController;
use App\Http\Controllers\Report\CaseReportController;
use App\Http\Controllers\Report\ReservationReportController;
use App\Http\Controllers\Report\FinancialReportController;

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
    
    // Public patient routes (no authentication required)
    // Tenant ID passed as query parameter for QR code links: ?clinic={tenant_id}
    Route::prefix('public/patients')->group(function () {
        Route::get('/{token}', [PublicPatientController::class, 'show']);
        Route::get('/{token}/cases', [PublicPatientController::class, 'cases']);
        Route::get('/{token}/images', [PublicPatientController::class, 'images']);
        Route::get('/{token}/reservations', [PublicPatientController::class, 'reservations']);
    });
    
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
        Route::get('bills/statistics/summary', [BillController::class, 'statistics']);
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
        Route::get('notes/{noteableType}/{noteableId}', [NoteController::class, 'byNoteable']);
        Route::apiResource('notes', NoteController::class);
    });

    // Image routes
    Route::middleware('jwt')->group(function () {
        Route::apiResource('images', ImageController::class);
    });

    // Doctor routes
    Route::middleware('jwt')->group(function () {
        Route::apiResource('doctors', DoctorController::class);
        Route::get('doctors-active', [DoctorController::class, 'active']);
    });

    // Secretary routes
    Route::middleware('jwt')->prefix('secretaries')->group(function () {
        Route::get('/available-permissions', [SecretaryController::class, 'availablePermissions']);
        Route::get('/', [SecretaryController::class, 'index']);
        Route::post('/', [SecretaryController::class, 'store']);
        Route::get('/{secretary}', [SecretaryController::class, 'show']);
        Route::put('/{secretary}', [SecretaryController::class, 'update']);
        Route::delete('/{secretary}', [SecretaryController::class, 'destroy']);
        Route::patch('/{secretary}/permissions', [SecretaryController::class, 'updatePermissions']);
        Route::patch('/{secretary}/toggle-status', [SecretaryController::class, 'toggleStatus']);
    });

    // Clinic settings routes
    Route::middleware('jwt')->prefix('clinic-settings')->group(function () {
        Route::get('/', [ClinicSettingController::class, 'index']);
        Route::get('/{key}', [ClinicSettingController::class, 'show']);
        Route::put('/{key}', [ClinicSettingController::class, 'update']);
        Route::post('/bulk-update', [ClinicSettingController::class, 'updateBulk']);
    });

    // ============================================
    // REPORTS & ANALYTICS ROUTES (JWT required)
    // ============================================
    Route::middleware('jwt')->prefix('reports')->group(function () {
        
        // Dashboard Overview
        Route::get('dashboard/overview', [DashboardReportController::class, 'overview']);
        Route::get('dashboard/today', [DashboardReportController::class, 'today']);
        
        // Patient Reports
        Route::prefix('patients')->group(function () {
            Route::get('summary', [PatientReportController::class, 'summary']);
            Route::get('by-source', [PatientReportController::class, 'bySource']);
            Route::get('by-doctor', [PatientReportController::class, 'byDoctor']);
            Route::get('trend', [PatientReportController::class, 'trend']);
            Route::get('age-distribution', [PatientReportController::class, 'ageDistribution']);
        });
        
        // Case Reports
        Route::prefix('cases')->group(function () {
            Route::get('summary', [CaseReportController::class, 'summary']);
            Route::get('by-category', [CaseReportController::class, 'byCategory']);
            Route::get('by-status', [CaseReportController::class, 'byStatus']);
            Route::get('by-doctor', [CaseReportController::class, 'byDoctor']);
            Route::get('trend', [CaseReportController::class, 'trend']);
        });
        
        // Reservation Reports
        Route::prefix('reservations')->group(function () {
            Route::get('summary', [ReservationReportController::class, 'summary']);
            Route::get('by-status', [ReservationReportController::class, 'byStatus']);
            Route::get('by-doctor', [ReservationReportController::class, 'byDoctor']);
            Route::get('trend', [ReservationReportController::class, 'trend']);
        });
        
        // Financial Reports
        Route::prefix('financial')->group(function () {
            // Bills/Revenue
            Route::get('bills/summary', [FinancialReportController::class, 'billsSummary']);
            Route::get('revenue/by-doctor', [FinancialReportController::class, 'revenueByDoctor']);
            Route::get('revenue/trend', [FinancialReportController::class, 'revenueTrend']);
            Route::get('bills/by-payment-status', [FinancialReportController::class, 'billsByPaymentStatus']);
            
            // Expenses
            Route::get('expenses/summary', [FinancialReportController::class, 'expensesSummary']);
            Route::get('expenses/by-category', [FinancialReportController::class, 'expensesByCategory']);
            Route::get('expenses/trend', [FinancialReportController::class, 'expensesTrend']);
            
            // Profit/Loss
            Route::get('profit-loss', [FinancialReportController::class, 'profitLoss']);
            Route::get('profit-loss/trend', [FinancialReportController::class, 'profitLossTrend']);
            
            // Doctor Performance
            Route::get('doctor-performance', [FinancialReportController::class, 'doctorPerformance']);
        });
        
        // Legacy bill report (kept for backward compatibility)
        Route::get('bills', [BillReportController::class, 'index']);
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

