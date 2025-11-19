<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TourController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\GuideController;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('v1')->group(function () {

    // Tours
    Route::get('/tours', [TourController::class, 'index']);
    Route::get('/tours/featured', [TourController::class, 'featured']);
    Route::get('/tours/popular', [TourController::class, 'popular']);
    Route::get('/tours/{slug}', [TourController::class, 'show']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/popular', [CategoryController::class, 'popular']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);

    // Guides
    Route::get('/guides', [GuideController::class, 'index']);
    Route::get('/guides/{id}', [GuideController::class, 'show']);

    // Reviews
    Route::get('/tours/{tourId}/reviews', [ReviewController::class, 'index']);

    // Payment webhook (public for gateway callbacks)
    Route::post('/payments/webhook', [PaymentController::class, 'webhook']);
});

// Protected routes
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // User Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::delete('/profile', [ProfileController::class, 'deleteAccount']);
    Route::get('/profile/dashboard', [ProfileController::class, 'dashboard']);
    Route::get('/profile/bookings', [ProfileController::class, 'bookingHistory']);

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/statistics', [BookingController::class, 'statistics']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{id}/confirm', [BookingController::class, 'confirm']);
    Route::post('/bookings/{id}/complete', [BookingController::class, 'complete']);

    // Favorites
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{tourId}/toggle', [FavoriteController::class, 'toggle']);
    Route::get('/favorites/{tourId}/check', [FavoriteController::class, 'check']);
    Route::delete('/favorites/{tourId}', [FavoriteController::class, 'destroy']);

    // Reviews
    Route::get('/my-reviews', [ReviewController::class, 'myReviews']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'myPayments']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
    Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
    Route::post('/payments/{id}/confirm', [PaymentController::class, 'confirm']);
    Route::post('/payments/{id}/failed', [PaymentController::class, 'failed']);

    // Guide routes
    Route::prefix('guide')->group(function () {
        Route::post('/register', [GuideController::class, 'register']);
        Route::put('/profile', [GuideController::class, 'update']);
        Route::get('/dashboard', [GuideController::class, 'dashboard']);
        Route::get('/tours', [GuideController::class, 'myTours']);
        Route::get('/bookings', [GuideController::class, 'myBookings']);

        // Tour management for guides
        Route::post('/tours', [TourController::class, 'store']);
        Route::put('/tours/{id}', [TourController::class, 'update']);
        Route::delete('/tours/{id}', [TourController::class, 'destroy']);
    });

    // Admin routes
    Route::prefix('admin')->middleware(['role:admin'])->group(function () {

        // Category management
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // Guide management
        Route::get('/guides/pending', [GuideController::class, 'pending']);
        Route::post('/guides/{id}/verify', [GuideController::class, 'verify']);

        // Review management
        Route::get('/reviews/pending', [ReviewController::class, 'pending']);
        Route::post('/reviews/{id}/approve', [ReviewController::class, 'approve']);

        // Payment management
        Route::post('/payments/{id}/refund', [PaymentController::class, 'refund']);

        // Tour management
        Route::put('/tours/{id}', [TourController::class, 'update']);
        Route::delete('/tours/{id}', [TourController::class, 'destroy']);
    });
});

// Fallback route
Route::fallback(function () {
    return response()->json([
        'message' => 'Route not found'
    ], 404);
});
