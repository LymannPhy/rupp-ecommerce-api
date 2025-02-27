<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductFeedbackController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WishlistController;
use App\Http\Middleware\JwtMiddleware;


// Public Auth Routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/resend-verification-code', [AuthController::class, 'resendVerificationCode']);
Route::post('/request-password-reset', [AuthController::class, 'requestPasswordReset']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);


// Media Uploader(Not require user authentication)
Route::prefix('images')->group(function () {
    Route::post('/upload-single', [ImageUploadController::class, 'uploadSingle']);
    Route::post('/upload-multiple', [ImageUploadController::class, 'uploadMultiple']);
});


// Public Route
Route::prefix('blogs')->group(function () {
    Route::get('/top', [BlogController::class, 'getTopBlogs']);
    Route::get('/{uuid}', [BlogController::class, 'show']); 
    Route::get('/', [BlogController::class, 'index']); 
});

Route::prefix('contact-us')->group(function () {
    Route::post('/', [ContactUsController::class, 'store']); 
});

Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/discounted', [ProductController::class, 'getDiscountedProducts']);
    Route::get('/popular-products', [ProductController::class, 'getPopularProducts']);
    Route::get('/recommended', [ProductController::class, 'recommended']);
    Route::get('/preorders', [ProductController::class, 'getPreorderProducts']);
    Route::get('/{uuid}', [ProductController::class, 'show']);
});

Route::prefix('feedbacks')->group(function () {
    Route::get('/promoted-feedbacks', [FeedbackController::class, 'getPromotedFeedbacks']);
});


//Protected Route
Route::middleware([JwtMiddleware::class])->group(function () {
    //User Authentication
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    // User CRUD Routes (Only authenticated users with role "user and admin")
    Route::prefix('users')->group(function () {
        Route::get('/current-user', [UserController::class, 'getCurrentUser']);
        Route::post('/change-password', [UserController::class, 'changePassword']);
        Route::patch('/update-profile', [UserController::class, 'updateProfile']);
    });

    Route::prefix('orders')->group(function () {
        Route::post('/confirm_order', [OrderController::class, 'confirmOrder']);
        Route::post('/get-total-amount', [OrderController::class, 'getOrderSummary']);
        Route::get('/invoice/{orderUuid}', [OrderController::class, 'generateInvoicePDF']);
    });

    Route::prefix('payments')->group(function () {
        Route::post('/check-payment', [PaymentController::class, 'checkPayment']);
    });

    Route::prefix('carts')->group(function () {
        Route::get('/items', [CartController::class, 'getCartItems']);
        Route::post('/add', [CartController::class, 'addToCart']);
        Route::delete('/remove', [CartController::class, 'removeFromCart']); 
        Route::patch('/update-quantity', [CartController::class, 'updateCartQuantity']);
    });

    Route::prefix('wishlists')->group(function () {
        Route::post('/add', [WishlistController::class, 'addToWishlist']); 
        Route::get('/', [WishlistController::class, 'getWishlist']); 
        Route::delete('/remove', [WishlistController::class, 'removeFromWishlist']);
    });

    Route::prefix('bookmarks')->group(function () {
        Route::post('/', [BookmarkController::class, 'store']); 
        Route::get('/', [BookmarkController::class, 'index']); 
        Route::delete('/{uuid}', [BookmarkController::class, 'destroy']);
    });

    Route::prefix('product-feedbacks')->group(function () {
        Route::post('/submit', [ProductFeedbackController::class, 'store']);
    });

    Route::prefix('blogs')->group(function () { 
        Route::post('/{uuid}/like', [BlogController::class, 'likeBlog']); 
        Route::post('/{uuid}/comment', [BlogController::class, 'commentOnBlog']);
        Route::get('/{uuid}/comments', [BlogController::class, 'getBlogComments']);
        Route::delete('/comment/{uuid}', [BlogController::class, 'deleteComment']);
    });
    
});


Route::middleware([JwtMiddleware::class, 'role:admin'])->group(function () {
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
        Route::post('/subcategories', [CategoryController::class, 'createSubcategory']);
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

    Route::prefix('blogs')->group(function () {
        Route::post('/', [BlogController::class, 'store']); 
        Route::put('/{uuid}', [BlogController::class, 'update']);
        Route::delete('/{uuid}', [BlogController::class, 'destroy']);
    });

    Route::prefix('feedbacks')->group(function () {
        Route::get('/', [FeedbackController::class, 'getAllFeedbacks']); 
        Route::delete('/{uuid}', [FeedbackController::class, 'deleteFeedback']);
        Route::patch('/{uuid}/status', [FeedbackController::class, 'updateFeedbackStatus']);
    });

    Route::prefix('contact-us')->group(function () {
        Route::get('/', [ContactUsController::class, 'index']); 
        Route::post('/respond/{uuid}', [ContactUsController::class, 'respondToUser']);
    });

    Route::prefix('product-feedbacks')->group(function () {
        Route::get('/all', [ProductFeedbackController::class, 'getAllFeedbacks']);
        Route::delete('/soft-delete/{uuid}', [ProductFeedbackController::class, 'softDelete']);
    });

    Route::prefix('suppliers')->group(function () {
        Route::post('/create', [SupplierController::class, 'store']);
        Route::put('/update/{uuid}', [SupplierController::class, 'update']);
        Route::delete('/delete/{uuid}', [SupplierController::class, 'delete']);
    });
    
    
});

