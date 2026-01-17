<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseCategoryController;
use App\Http\Controllers\ClinicExpenseController;
use App\Http\Controllers\ClinicExpenseCategoryController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

// Public auth routes (no authentication required)
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

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
