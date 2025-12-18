<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\NotificationController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

Route::get('offers', [OfferController::class, 'index']);
Route::get('offers/{offer}', [OfferController::class, 'show']);

/*
|--------------------------------------------------------------------------
|  Authenticated User Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('carts', CardController::class);
    Route::get('users/{userId}/cart', [CardController::class, 'getUserCart']);
    Route::delete('users/{userId}/cart/clear', [CardController::class, 'clearCart']);
    Route::get('users/{userId}/cart/total', [CardController::class, 'calculateTotal']);

    Route::get('carts/{cart}/items', [CartItemController::class, 'index']);
    Route::post('carts/{cart}/items', [CartItemController::class, 'store']);
    //تعديل منتج واحد مثلا نقص زيادة من سلة 
    Route::put('carts/{cart}/items/{item}', [CartItemController::class, 'update']);
    // حذف منتج من السلة 
    Route::delete('carts/{cart}/items/{item}', [CartItemController::class, 'destroy']);

    
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/user/{userId}', [OrderController::class, 'getUserOrders']);

    Route::get('order-items/order/{orderId}', [OrderItemController::class, 'getItemsByOrder']);

    Route::apiResource('reviews', ReviewController::class)
        ->only(['store', 'update', 'destroy']);

    Route::get('reviews/product/{productId}', [ReviewController::class, 'getReviewsByProduct']);
    Route::get('reviews/user/{userId}', [ReviewController::class, 'getReviewsByUser']);

    Route::apiResource('profiles', ProfileController::class)->only([
        'show', 'update'
    ]);

    Route::apiResource('notifications', NotificationController::class)
        ->only(['index', 'show', 'destroy']);
});

/*
|--------------------------------------------------------------------------
|    Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin'])->group(function () {

    Route::apiResource('products', ProductController::class)
        ->except(['index', 'show']);

    Route::apiResource('offers', OfferController::class)
        ->except(['index', 'show']);

    Route::apiResource('orders', OrderController::class)
        ->except(['store']);

    Route::get('orders/status/{status}', [OrderController::class, 'getOrdersByStatus']);
    Route::get(
        'orders/reports/monthly/{month}/{year}',
        [OrderController::class, 'getMonthlySalesReport']
    );

    Route::apiResource('order-items', OrderItemController::class)
        ->only(['destroy']);
});
