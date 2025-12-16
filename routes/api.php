<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\OfferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register',[AuthController::class,'register']);
Route::post('/login',[AuthController::class,'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::apiResource('offers', OfferController::class);

Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('carts', CardController::class);

    Route::get('/users/{userId}/cart', [CardController::class, 'getUserCart']);
    Route::delete('/users/{userId}/cart/clear', [CardController::class, 'clearCart']);
    Route::get('/users/{userId}/cart/total', [CardController::class, 'calculateTotal']);
    });

