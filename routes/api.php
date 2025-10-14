<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::prefix('app/v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/update-guest', [AuthController::class, 'updateGuest']);
        Route::post('/update-driver', [AuthController::class, 'updateDriver']);
        Route::post('/update-guide', [AuthController::class, 'updateGuide']);
        Route::post('/update-jobsheet-status', [AuthController::class, 'updateJobsheetStatus']);
        
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});

