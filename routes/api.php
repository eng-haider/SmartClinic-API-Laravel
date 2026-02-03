<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseCategoryController;
use App\Http\Controllers\ClinicExpenseController;
use App\Http\Controllers\ClinicExpenseCategoryController;
use App\Http\Controllers\ClinicSettingController;
use App\Http\Controllers\SettingDefinitionController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\SecretaryController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\PublicPatientController;
use App\Http\Controllers\PatientPublicProfileController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\Report\BillReportController;
use App\Http\Controllers\Report\DashboardReportController;
use App\Http\Controllers\Report\PatientReportController;
use App\Http\Controllers\Report\CaseReportController;
use App\Http\Controllers\Report\ReservationReportController;
use App\Http\Controllers\Report\FinancialReportController;
use Illuminate\Support\Facades\Route;

// ============================================
// TENANT MANAGEMENT ROUTES (Central Database)
// These routes manage clinics/tenants
// ============================================
Route::prefix('tenants')->group(function () {
    Route::get('/', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('/', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('/{id}', [TenantController::class, 'show'])->name('tenants.show');
    Route::put('/{id}', [TenantController::class, 'update'])->name('tenants.update');
    Route::delete('/{id}', [TenantController::class, 'destroy'])->name('tenants.destroy');
    Route::get('/{id}/domains', [TenantController::class, 'domains'])->name('tenants.domains');
    Route::post('/{id}/domains', [TenantController::class, 'addDomain'])->name('tenants.add-domain');
    Route::post('/{id}/migrate', [TenantController::class, 'migrate'])->name('tenants.migrate');
    Route::post('/{id}/seed', [TenantController::class, 'seed'])->name('tenants.seed');
});

// ============================================
// PUBLIC PATIENT PROFILE ROUTES (No authentication required)
// These routes are accessed via QR code scanning
// ============================================
Route::prefix('public/patients')->group(function () {
    Route::get('/{token}', [PublicPatientController::class, 'show'])->name('public.patients.show');
    Route::get('/{token}/cases', [PublicPatientController::class, 'cases'])->name('public.patients.cases');
    Route::get('/{token}/images', [PublicPatientController::class, 'images'])->name('public.patients.images');
    Route::get('/{token}/reservations', [PublicPatientController::class, 'reservations'])->name('public.patients.reservations');
});

// ============================================
// PUBLIC AUTH ROUTES (No authentication required)
// Step 1: Check credentials to get tenant_id
// Step 2: Login with X-Tenant-ID to get token
// ============================================
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/check-credentials', [AuthController::class, 'checkCredentials']); // ← خطوة 1: التحقق
Route::post('auth/login', [AuthController::class, 'login']); // ← قديم (بدون tenant)
Route::post('auth/smart-login', [AuthController::class, 'smartLogin']); // ← الدخول الذكي

// Protected auth routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::post('auth/change-password', [AuthController::class, 'changePassword']);
});

// Protected patient routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::apiResource('patients', PatientController::class);
    Route::get('patients/search/phone/{phone}', [PatientController::class, 'searchByPhone'])->name('patients.search.phone');
    Route::get('patients/search/email/{email}', [PatientController::class, 'searchByEmail'])->name('patients.search.email');
    Route::put('patients/{id}/tooth-details', [PatientController::class, 'updateToothDetails'])->name('patients.update-tooth-details');
    
    // Patient Public Profile Management
    Route::get('patients/{id}/public-profile', [PatientPublicProfileController::class, 'getPublicProfile'])->name('patients.public-profile');
    Route::post('patients/{id}/public-profile/enable', [PatientPublicProfileController::class, 'enablePublicProfile'])->name('patients.public-profile.enable');
    Route::post('patients/{id}/public-profile/disable', [PatientPublicProfileController::class, 'disablePublicProfile'])->name('patients.public-profile.disable');
    Route::post('patients/{id}/public-profile/regenerate-token', [PatientPublicProfileController::class, 'regenerateToken'])->name('patients.public-profile.regenerate-token');
    Route::get('patients/{id}/qr-code', [PatientPublicProfileController::class, 'getQrCodeData'])->name('patients.qr-code');
});

// Protected case routes (JWT required)
Route::middleware('jwt')->group(function () {
    // Standard CRUD operations
    Route::apiResource('cases', CaseController::class);
});

// Protected case category routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::apiResource('case-categories', CaseCategoryController::class);
});

// Protected reservation routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::apiResource('reservations', ReservationController::class);
});

// Protected recipe routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::apiResource('recipes', RecipeController::class);
});

// Protected bill routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::apiResource('bills', BillController::class);
    Route::patch('bills/{id}/mark-paid', [BillController::class, 'markAsPaid'])->name('bills.mark-paid');
    Route::patch('bills/{id}/mark-unpaid', [BillController::class, 'markAsUnpaid'])->name('bills.mark-unpaid');
    Route::get('bills/patient/{patientId}', [BillController::class, 'byPatient'])->name('bills.by-patient');
    Route::get('bills/statistics/summary', [BillController::class, 'statistics'])->name('bills.statistics');
});

// Protected clinic expense category routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::apiResource('clinic-expense-categories', ClinicExpenseCategoryController::class);
    Route::get('clinic-expense-categories-active', [ClinicExpenseCategoryController::class, 'active'])->name('clinic-expense-categories.active');
});

// Protected clinic expense routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::apiResource('clinic-expenses', ClinicExpenseController::class);
    Route::patch('clinic-expenses/{id}/mark-paid', [ClinicExpenseController::class, 'markAsPaid'])->name('clinic-expenses.mark-paid');
    Route::patch('clinic-expenses/{id}/mark-unpaid', [ClinicExpenseController::class, 'markAsUnpaid'])->name('clinic-expenses.mark-unpaid');
    Route::get('clinic-expenses-statistics', [ClinicExpenseController::class, 'statistics'])->name('clinic-expenses.statistics');
    Route::get('clinic-expenses-unpaid', [ClinicExpenseController::class, 'unpaid'])->name('clinic-expenses.unpaid');
    Route::get('clinic-expenses-by-date-range', [ClinicExpenseController::class, 'byDateRange'])->name('clinic-expenses.by-date-range');
});

// Protected doctor routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::apiResource('doctors', DoctorController::class);
    Route::get('doctors-active', [DoctorController::class, 'active'])->name('doctors.active');
    Route::get('doctors/clinic/{clinicId}', [DoctorController::class, 'byClinic'])->name('doctors.by-clinic');
    Route::get('doctors/search/email/{email}', [DoctorController::class, 'searchByEmail'])->name('doctors.search.email');
    Route::get('doctors/search/phone/{phone}', [DoctorController::class, 'searchByPhone'])->name('doctors.search.phone');
});

// Protected note routes (JWT required)
Route::middleware('jwt')->group(function () {
    Route::apiResource('notes', NoteController::class);
    Route::get('notes/{noteableType}/{noteableId}', [NoteController::class, 'byNoteable'])->name('notes.by-noteable');
});

// ============================================
// SETTING DEFINITIONS ROUTES (Super Admin Only)
// Super Admin defines what settings exist for all clinics
// ============================================
Route::middleware('jwt')->prefix('setting-definitions')->group(function () {
    Route::get('/', [SettingDefinitionController::class, 'index'])->name('setting-definitions.index');
    Route::post('/', [SettingDefinitionController::class, 'store'])->name('setting-definitions.store');
    Route::get('/categories', [SettingDefinitionController::class, 'categories'])->name('setting-definitions.categories');
    Route::get('/types', [SettingDefinitionController::class, 'types'])->name('setting-definitions.types');
    Route::post('/sync-all', [SettingDefinitionController::class, 'syncAll'])->name('setting-definitions.sync-all');
    Route::get('/{id}', [SettingDefinitionController::class, 'show'])->name('setting-definitions.show');
    Route::put('/{id}', [SettingDefinitionController::class, 'update'])->name('setting-definitions.update');
    Route::delete('/{id}', [SettingDefinitionController::class, 'destroy'])->name('setting-definitions.destroy');
});

// ============================================
// CLINIC SETTINGS ROUTES (JWT required)
// Doctors can UPDATE their clinic settings values
// ============================================
Route::middleware('jwt')->prefix('clinic-settings')->group(function () {
    Route::get('/', [ClinicSettingController::class, 'index'])->name('clinic-settings.index');
    Route::get('/{key}', [ClinicSettingController::class, 'show'])->name('clinic-settings.show');
    Route::put('/{key}', [ClinicSettingController::class, 'update'])->name('clinic-settings.update');
    Route::post('/bulk-update', [ClinicSettingController::class, 'updateBulk'])->name('clinic-settings.bulk-update');
    Route::post('/upload-logo', [ClinicSettingController::class, 'uploadLogo'])->name('clinic-settings.upload-logo');
});

// Protected image routes (JWT required)
Route::middleware('jwt')->group(function () {
    // Custom routes must come BEFORE apiResource
    Route::get('images/by-imageable', [ImageController::class, 'getByImageable'])->name('images.by-imageable');
    Route::get('images/statistics/summary', [ImageController::class, 'statistics'])->name('images.statistics');
    Route::patch('images/{id}/order', [ImageController::class, 'updateOrder'])->name('images.update-order');
    
    // Standard CRUD operations
    Route::apiResource('images', ImageController::class);
});

// ============================================
// SECRETARY MANAGEMENT ROUTES (JWT required)
// Only clinic_super_doctor can access these
// ============================================
Route::middleware('jwt')->prefix('secretaries')->group(function () {
    Route::get('/available-permissions', [SecretaryController::class, 'availablePermissions'])->name('secretaries.available-permissions');
    Route::get('/', [SecretaryController::class, 'index'])->name('secretaries.index');
    Route::post('/', [SecretaryController::class, 'store'])->name('secretaries.store');
    Route::get('/{secretary}', [SecretaryController::class, 'show'])->name('secretaries.show');
    Route::put('/{secretary}', [SecretaryController::class, 'update'])->name('secretaries.update');
    Route::delete('/{secretary}', [SecretaryController::class, 'destroy'])->name('secretaries.destroy');
    Route::patch('/{secretary}/permissions', [SecretaryController::class, 'updatePermissions'])->name('secretaries.update-permissions');
    Route::patch('/{secretary}/toggle-status', [SecretaryController::class, 'toggleStatus'])->name('secretaries.toggle-status');
});

// ============================================
// REPORTS & ANALYTICS ROUTES (JWT required)
// ============================================
Route::middleware('jwt')->prefix('reports')->group(function () {
    
    // Dashboard Overview
    Route::get('dashboard/overview', [DashboardReportController::class, 'overview'])->name('reports.dashboard.overview');
    Route::get('dashboard/today', [DashboardReportController::class, 'today'])->name('reports.dashboard.today');
    
    // Patient Reports
    Route::prefix('patients')->group(function () {
        Route::get('summary', [PatientReportController::class, 'summary'])->name('reports.patients.summary');
        Route::get('by-source', [PatientReportController::class, 'bySource'])->name('reports.patients.by-source');
        Route::get('by-doctor', [PatientReportController::class, 'byDoctor'])->name('reports.patients.by-doctor');
        Route::get('trend', [PatientReportController::class, 'trend'])->name('reports.patients.trend');
        Route::get('age-distribution', [PatientReportController::class, 'ageDistribution'])->name('reports.patients.age-distribution');
    });
    
    // Case Reports
    Route::prefix('cases')->group(function () {
        Route::get('summary', [CaseReportController::class, 'summary'])->name('reports.cases.summary');
        Route::get('by-category', [CaseReportController::class, 'byCategory'])->name('reports.cases.by-category');
        Route::get('by-status', [CaseReportController::class, 'byStatus'])->name('reports.cases.by-status');
        Route::get('by-doctor', [CaseReportController::class, 'byDoctor'])->name('reports.cases.by-doctor');
        Route::get('trend', [CaseReportController::class, 'trend'])->name('reports.cases.trend');
    });
    
    // Reservation Reports
    Route::prefix('reservations')->group(function () {
        Route::get('summary', [ReservationReportController::class, 'summary'])->name('reports.reservations.summary');
        Route::get('by-status', [ReservationReportController::class, 'byStatus'])->name('reports.reservations.by-status');
        Route::get('by-doctor', [ReservationReportController::class, 'byDoctor'])->name('reports.reservations.by-doctor');
        Route::get('trend', [ReservationReportController::class, 'trend'])->name('reports.reservations.trend');
    });
    
    // Financial Reports
    Route::prefix('financial')->group(function () {
        // Bills/Revenue
        Route::get('bills/summary', [FinancialReportController::class, 'billsSummary'])->name('reports.financial.bills-summary');
        Route::get('revenue/by-doctor', [FinancialReportController::class, 'revenueByDoctor'])->name('reports.financial.revenue-by-doctor');
        Route::get('revenue/trend', [FinancialReportController::class, 'revenueTrend'])->name('reports.financial.revenue-trend');
        Route::get('bills/by-payment-status', [FinancialReportController::class, 'billsByPaymentStatus'])->name('reports.financial.bills-by-payment-status');
        
        // Expenses
        Route::get('expenses/summary', [FinancialReportController::class, 'expensesSummary'])->name('reports.financial.expenses-summary');
        Route::get('expenses/by-category', [FinancialReportController::class, 'expensesByCategory'])->name('reports.financial.expenses-by-category');
        Route::get('expenses/trend', [FinancialReportController::class, 'expensesTrend'])->name('reports.financial.expenses-trend');
        
        // Profit/Loss
        Route::get('profit-loss', [FinancialReportController::class, 'profitLoss'])->name('reports.financial.profit-loss');
        Route::get('profit-loss/trend', [FinancialReportController::class, 'profitLossTrend'])->name('reports.financial.profit-loss-trend');
        
        // Doctor Performance
        Route::get('doctor-performance', [FinancialReportController::class, 'doctorPerformance'])->name('reports.financial.doctor-performance');
    });
    
    // Legacy bill report (kept for backward compatibility)
    Route::get('bills', [BillReportController::class, 'index'])->name('reports.bills.legacy');
});
