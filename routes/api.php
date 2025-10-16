<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::prefix('app/v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        
        // Driver routes
        Route::middleware('is.driver')->group(function () {
            Route::post('/update-driver', [AuthController::class, 'updateDriver']);
            Route::get('/driver-jobsheets', [AuthController::class, 'getDriverJobsheets']);
        });

        // Guide routes
        Route::middleware('is.guide')->group(function () {
            Route::post('/update-guide', [AuthController::class, 'updateGuide']);
            Route::get('/guide-jobsheets', [AuthController::class, 'getGuideJobsheets']);
        });

        // Guest routes
        Route::middleware('is.guest')->group(function () {
            Route::post('/update-guest', [AuthController::class, 'updateGuest']);
            Route::get('/guest-bookings', [AuthController::class, 'getGuestBookings']);
        });

        // Common routes (accessible by all authenticated users)
        Route::post('/update-jobsheet-status', [AuthController::class, 'updateJobsheetStatus']);
        
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});

