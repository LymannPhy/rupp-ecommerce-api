<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

// Public Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/resend-verification-code', [AuthController::class, 'resendVerificationCode']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/request-password-reset', [AuthController::class, 'requestPasswordReset']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected Routes (Require Sanctum Authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/current-user', [UserController::class, 'getCurrentUser']);


    // User CRUD Routes
    Route::prefix('users')->group(function () {
        Route::get('/{uuid}', [UserController::class, 'getUserByUuid']);
    });
    
});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('users', [UserController::class, 'getAllUsers']);
});

