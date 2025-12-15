<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);


    Route::apiResource('offers', OfferController::class);

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('carts', CardController::class);
        Route::get('/users/{userId}/cart', [CardController::class, 'getUserCart']);
        Route::delete('/users/{userId}/cart/clear', [CardController::class, 'clearCart']);
        Route::get('/users/{userId}/cart/total', [CardController::class, 'calculateTotal']);
    });

    Route::apiResource('orders', OrderController::class);
    Route::delete('orders/{order}', [OrderController::class, 'destroy']);
    Route::get('orders/user/{userId}', [OrderController::class, 'getUserOrders']);
    Route::get('orders/status/{status}', [OrderController::class, 'getOrdersByStatus']);
    Route::get('orders/reports/monthly/{month}/{year}', [
        OrderController::class,
        'getMonthlySalesReport'
    ]);

    Route::apiResource('order-items', OrderItemController::class);
    Route::delete('order-items/{id}', [OrderItemController::class, 'destroy']);
    Route::get('order-items/order/{orderId}', [OrderItemController::class, 'getItemsByOrder']);


    Route::apiResource('profiles', ProfileController::class);

    Route::apiResource('reviews', ReviewController::class);
    Route::delete('reviews/{id}', [ReviewController::class, 'destroy']);
    Route::get('reviews/product/{productId}', [ReviewController::class, 'getReviewsByProduct']);
    Route::get('reviews/user/{userId}', [ReviewController::class, 'getReviewsByUser']);

    Route::apiResource('notifications', NotificationController::class);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
});
