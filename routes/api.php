<?php

use App\Http\Controllers\AdController;
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

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

Route::get('offers', [OfferController::class, 'index']);
Route::get('offers/{offer}', [OfferController::class, 'show']);

Route::get('ads', [AdController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Authenticated User Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |----------------------------------
    | NEW (Token-based Cart Routes)
    |----------------------------------
    */
    Route::get('/cart', [CardController::class, 'myCart']);
    Route::get('/cart/total', [CardController::class, 'myCartTotal']);
    Route::delete('/cart/clear', [CardController::class, 'clearMyCart']);

    /*
    |----------------------------------
    | OLD Routes (keep as-is)
    |----------------------------------
    */
    Route::apiResource('carts', CardController::class);

    /*
    | Cart Items
    */
    Route::get('carts/{cart}/items', [CartItemController::class, 'index']);
    Route::post('carts/{cart}/items', [CartItemController::class, 'store']);
    Route::put('carts/{cart}/items/{item}', [CartItemController::class, 'update']);
    Route::delete('carts/{cart}/items/{item}', [CartItemController::class, 'destroy']);

    /*
    | Orders
    */
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/user/{userId}', [OrderController::class, 'getUserOrders']);

    /*
    | Order Items
    */
    Route::get('order-items/order/{orderId}', [OrderItemController::class, 'getItemsByOrder']);

    /*
    | Reviews
    */
    Route::apiResource('reviews', ReviewController::class)
        ->only(['store', 'update', 'destroy']);

    Route::get('reviews/product/{productId}', [ReviewController::class, 'getReviewsByProduct']);
    Route::get('reviews/user/{userId}', [ReviewController::class, 'getReviewsByUser']);

    /*
    | Profile
    */
    Route::get('profile', [ProfileController::class, 'me']);
    Route::put('profile', [ProfileController::class, 'updateMe']);

    /*
    | Notifications
    */
    Route::apiResource('notifications', NotificationController::class)
        ->only(['index', 'show', 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes
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

    Route::apiResource('ads', AdController::class)
    ->except(['index']);
    Route::delete('ads/{ad}', [AdController::class, 'destroy']);
});
