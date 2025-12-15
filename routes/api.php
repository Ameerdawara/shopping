<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductController as ControllersProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Route::apiResource('offers', OfferController::class);

Route::get('getAllOffer', [OfferController::class, 'index']);
Route::get('showOneOffer/{id}', [OfferController::class, 'show']);
Route::post('addOffer', [OfferController::class, 'store']);
Route::put('updateOffer/{id}', [OfferController::class, 'update']);
Route::delete('deleteOffer/{id}', [OfferController::class, 'destroy']);

//cart Item route
Route::get('carts/{cart}/items', [CartItemController::class, 'index']);
Route::post('carts/{cart}/items', [CartItemController::class, 'store']);
Route::put('carts/{cart}/items/{item}', [CartItemController::class, 'update']);
Route::delete('carts/{cart}/items/{item}', [CartItemController::class, 'destroy']);


// 🔓 عرض المنتجات (عام)
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {

    // ➕ إضافة منتج (مع صور)
    Route::post('products', [ProductController::class, 'store']);

    // ✏️ تحديث منتج
    Route::put('products/{product}', [ProductController::class, 'update']);

    // 🗑️ حذف منتج
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
});