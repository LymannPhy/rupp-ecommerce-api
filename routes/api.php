<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\ProductController;

// Public Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/resend-verification-code', [AuthController::class, 'resendVerificationCode']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/request-password-reset', [AuthController::class, 'requestPasswordReset']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Media Uploader(Not require user authentication)
Route::prefix('images')->group(function () {
    Route::post('/upload-single', [ImageUploadController::class, 'uploadSingle']);
    Route::post('/upload-multiple', [ImageUploadController::class, 'uploadMultiple']);
});


Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    // User CRUD Routes (Only authenticated users with role "user and admin")
    Route::prefix('users')->group(function () {
        Route::get('/current-user', [UserController::class, 'getCurrentUser']);
        Route::post('/change-password', [UserController::class, 'changePassword']);
    });
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{uuid}', [ProductController::class, 'show']);
    });
});


// Protected Routes (Require Sanctum Authentication)
Route::middleware(['auth:sanctum', 'role:user'])->group(function () {

});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'getAllUsers']);
        Route::get('{uuid}', [UserController::class, 'getUserByUuid']);
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']); 
        Route::post('/', [CategoryController::class, 'store']); 
        Route::get('/{uuid}', [CategoryController::class, 'show']);
        Route::put('/{uuid}', [CategoryController::class, 'update']);
        Route::delete('/{uuid}', [CategoryController::class, 'destroy']);
    });

    Route::prefix('discounts')->group(function () {
        Route::get('/', [DiscountController::class, 'index']);
        Route::post('/', [DiscountController::class, 'store']);
        Route::get('/{uuid}', [DiscountController::class, 'show']);
        Route::put('/{uuid}', [DiscountController::class, 'update']);
        Route::delete('/{uuid}', [DiscountController::class, 'destroy']);
    });

    Route::prefix('coupons')->group(function () {
        Route::post('/', [CouponController::class, 'store']);
    });

    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']); 
        Route::put('/{uuid}', [ProductController::class, 'update']);
        Route::delete('/{uuid}', [ProductController::class, 'destroy']);
    });
    
});

