<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\BulkUploadController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Simple API key authentication middleware
Route::middleware(['api.key', 'throttle:api'])->group(function () {

    // Coupons
    Route::apiResource('coupons', CouponController::class);
    Route::post('coupons/{coupon}/toggle-status', [CouponController::class, 'toggleStatus']);
    Route::post('coupons/{coupon}/verify', [CouponController::class, 'verify']);
    Route::get('coupons/statistics', [CouponController::class, 'statistics']);
    Route::post('coupons/bulk-sync', [CouponController::class, 'bulkSync']);
    
    // Stores
    Route::apiResource('stores', StoreController::class);
    Route::get('stores/{store}/coupons', [StoreController::class, 'coupons']);
    
    // Bulk Upload
    Route::post('bulk-upload/coupons', [BulkUploadController::class, 'uploadCoupons']);
    Route::get('bulk-upload/template', [BulkUploadController::class, 'downloadTemplate']);
    Route::post('bulk-upload/validate', [BulkUploadController::class, 'validateFile']);
    Route::get('bulk-upload/history', [BulkUploadController::class, 'uploadHistory']);
    Route::get('bulk-upload/stores', [BulkUploadController::class, 'getStores']);
    Route::get('bulk-upload/categories', [BulkUploadController::class, 'getCategories']);
    
    // Categories
    Route::apiResource('categories', CategoryController::class);
    
    // Blog Posts
    Route::apiResource('blog-posts', BlogController::class);
    Route::post('blog-posts/{blogPost}/upload-image', [BlogController::class, 'uploadImage']);
    
    // Analytics
    Route::get('analytics/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('analytics/coupons', [AnalyticsController::class, 'coupons']);
    Route::get('analytics/stores', [AnalyticsController::class, 'stores']);
    Route::get('analytics/trends', [AnalyticsController::class, 'trends']);
});

// Public health check (no authentication required)
Route::get('health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'Arabic Coupon Admin API',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString(),
    ]);
});

// API documentation route
Route::get('/', function () {
    return response()->json([
        'name' => 'Arabic Coupon Admin API',
        'version' => '1.0.0',
        'description' => 'Admin API for Arabic coupon aggregator application',
        'endpoints' => [
            'coupons' => '/api/coupons',
            'stores' => '/api/stores',
            'bulk-upload' => '/api/bulk-upload',
            'categories' => '/api/categories',
            'blog-posts' => '/api/blog-posts',
            'analytics' => '/api/analytics',
        ],
        'authentication' => 'API Key required in X-API-Key header',
        'documentation' => '/api/docs',
    ]);
});
