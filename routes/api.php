<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PatientController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
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
});

