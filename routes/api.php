<?php

use App\Http\Controllers\AdController;
use App\Http\Controllers\AddImageController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\NotificationController;
use App\Models\Cart;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/qr-images', function () {
    return response()->json([
        'shamcash_qr' => asset('storage/qr/shamcash.jpg'),
        'usdt_qr'     => asset('storage/qr/usdt.jpg'),
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// OTP Verification Flow
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

Route::get('products', [ProductController::class, 'index']);
Route::get('products/category/{category}', [ProductController::class, 'byCategory']);
Route::get('products/{productId}', [ProductController::class, 'show']);

Route::get('categories', [CategoryController::class, 'index']);

Route::get('offers', [OfferController::class, 'index']);
Route::get('offers/{offer}', [OfferController::class, 'show']);

Route::get('ads', [AdController::class, 'index']);
Route::get('reviews/product/{productId}', [ReviewController::class, 'getReviewsByProduct']);

// سعر الصرف الحالي (عام)
Route::get('/exchange-rate', [ExchangeRateController::class, 'show']);

// إعدادات الدفع العامة (للعملاء في صفحة الدفع)
Route::get('/payment-settings', [PaymentController::class, 'settings']);

/*
|--------------------------------------------------------------------------
| Authenticated User Routes (محمية بـ auth:sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Cart Routes
    Route::get('/my-cart', function (Request $request) {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        return response()->json($cart->load('cartItem.product'));
    });

    Route::get('/cart', [CardController::class, 'myCart']);
    Route::get('/cart/total', [CardController::class, 'myCartTotal']);
    Route::delete('/cart/clear', [CardController::class, 'clearMyCart']);
    Route::apiResource('carts', CardController::class);

    Route::get('carts/{cart}/items', [CartItemController::class, 'index']);
    Route::post('carts/{cart}/items', [CartItemController::class, 'store']);
    Route::put('carts/{cart}/items/{item}', [CartItemController::class, 'update']);
    Route::delete('carts/{cart}/items/{item}', [CartItemController::class, 'destroy']);

    // Orders - Cash Orders فقط (الطلبات اليدوية عبر PaymentController)
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/user', [OrderController::class, 'getUserOrders']);
    Route::put('orders/{id}/status', [OrderController::class, 'updateOrder']);

    // Manual Payment Flow (Sham Cash / USDT)
    Route::post('/orders/manual', [PaymentController::class, 'createManualOrder']);
    Route::post('/orders/{orderId}/payment-proof', [PaymentController::class, 'submitPaymentProof']);
    Route::get('/orders/{orderId}/status', [PaymentController::class, 'checkOrderStatus']);

    Route::get('order-items/order/{orderId}', [OrderItemController::class, 'getItemsByOrder']);

    // Reviews
    Route::apiResource('reviews', ReviewController::class)->only(['store', 'update', 'destroy']);
    Route::get('reviews/user/{userId}', [ReviewController::class, 'getReviewsByUser']);

    // Profile
    Route::get('profile', [ProfileController::class, 'me']);
    Route::put('profile', [ProfileController::class, 'updateMe']);

    // Notifications
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::apiResource('notifications', NotificationController::class)->only(['index', 'show', 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes (محمية بـ auth:sanctum + admin middleware)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('upload-qr', [AddImageController::class, 'uploadQR']);

    Route::apiResource('products', ProductController::class)->except(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('offers', OfferController::class)->except(['index', 'show']);
    Route::apiResource('orders', OrderController::class)->except(['store']);

    Route::get('orders/status/{status}', [OrderController::class, 'getOrdersByStatus']);
    Route::get('orders/reports/monthly/{month}/{year}', [OrderController::class, 'getMonthlySalesReport']);

    Route::apiResource('order-items', OrderItemController::class)->only(['destroy']);
    Route::apiResource('ads', AdController::class)->except(['index']);

    // Exchange Rate
    Route::post('/exchange-rate', [ExchangeRateController::class, 'update']);
    Route::get('/exchange-rate/history', [ExchangeRateController::class, 'history']);

    // Payment Settings (Admin)
    Route::post('/admin/payment-settings/shamcash', [PaymentController::class, 'updateShamCashSettings']);
    Route::post('/admin/payment-settings/usdt', [PaymentController::class, 'updateUsdtSettings']);

    // Manual Payment Approval (أدمن)
    Route::get('/admin/orders/pending-approval', [PaymentController::class, 'getPendingApprovalOrders']);
    Route::put('/admin/orders/{orderId}/approve', [PaymentController::class, 'approveOrder']);
    Route::put('/admin/orders/{orderId}/reject', [PaymentController::class, 'rejectOrder']);

    // Admin: جميع الطلبات للـ Commercial Ledger
    Route::get('/admin/orders', [OrderController::class, 'getOrdersToAdmin']);
});

/*
|--------------------------------------------------------------------------
| Utility Routes
|--------------------------------------------------------------------------
*/
Route::get('/run-link', function () {
    \Illuminate\Support\Facades\Artisan::call('storage:link');
    return 'Storage link created successfully!';
});
