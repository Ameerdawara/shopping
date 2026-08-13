<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\ExchangeRate;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        return response()->json(Order::all(), 200);
    }

    public function show(Order $order)
    {
        return response()->json($order->load(['user', 'orderItem.product']), 200);
    }

    /**
     * إنشاء طلب كاش (Cash on Delivery)
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $data = $request->validate([
                'shipping_address' => 'nullable|string',
                'currency'         => 'sometimes|in:SYP,USD',
                'is_paid'          => 'sometimes|boolean',
            ]);

            $cart = Cart::where('user_id', $user->id)
                ->with('cartItem')
                ->first();

            if (!$cart || $cart->cartItem->isEmpty()) {
                return response()->json(['message' => 'السلة فارغة'], 400);
            }

            $totalPrice = 0;
            foreach ($cart->cartItem as $item) {
                $totalPrice += $item->unit_price * $item->quantity;
            }

            $currency = $data['currency'] ?? 'SYP';

            $orderData = [
                'user_id'          => $user->id,
                'total_price'      => $totalPrice, // دائماً مخزن بالليرة السورية (عملة أساس للمحاسبة الداخلية)
                'currency'         => $currency,
                'status'           => 'pending',
                'is_paid'          => $data['is_paid'] ?? false,
                'shipping_address' => $data['shipping_address'] ?? 'عنوان غير محدد',
            ];

            $columns = Schema::getColumnListing('orders');
            if (in_array('payment_method', $columns)) $orderData['payment_method'] = 'cash';
            if (in_array('ip_address', $columns)) $orderData['ip_address'] = $request->ip();
            if (in_array('user_agent', $columns)) $orderData['user_agent'] = $request->userAgent();

            // FIXED: تخزين سعر الصرف وقت إنشاء الطلب عندما تكون العملة USD، حتى يمكن
            // لاحقاً عرض المبلغ بالدولار الصحيح في سجل الطلبات بدل عرضه دائماً بالليرة السورية.
            if (in_array('exchange_rate', $columns)) {
                $orderData['exchange_rate'] = $currency === 'USD' ? ExchangeRate::current() : null;
            }

            $order = Order::create($orderData);

            foreach ($cart->cartItem as $item) {
                $orderItemData = [
                    'order_id'   => $order->id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'price'      => $item->unit_price,
                    'color'      => $item->color,
                ];
                if (!empty($item->size)) {
                    $orderItemData['size'] = $item->size;
                }
                $order->orderItem()->create($orderItemData);
            }

            $cart->cartItem()->delete();

            // إشعار الأدمن بوجود طلب جديد
            NotificationController::notifyAdmins(
                'طلب جديد #' . $order->id,
                'طلب جديد بقيمة ' . number_format($totalPrice) . ' ل.س من ' . ($user->name ?? 'عميل'),
                'order',
                $order->id,
                '/Orders/Orders.html?order=' . $order->id
            );

            return response()->json([
                'message' => 'تم إنشاء الطلب بنجاح',
                'order'   => $order->load('orderItem.product')
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Order creation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'خطأ في إنشاء الطلب: ' . $e->getMessage()], 500);
        }
    }

    public function updateOrder(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);

            $validated = $request->validate([
                'status'           => 'sometimes|string|in:pending,pending_approval,processing,cancelled,completed',
                'shipping_address' => 'sometimes|string',
                'delivered_at'     => 'nullable|date',
            ]);

            $order->update($validated);

            if (isset($validated['status'])) {
                NotificationController::notifyUser(
                    $order->user_id,
                    'تحديث حالة الطلب #' . $order->id,
                    'أصبحت حالة طلبك الآن: ' . $validated['status'],
                    'order_status',
                    $order->id,
                    '/Orders/MyOrders.html?order=' . $order->id
                );
            }

            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'data'    => $order->fresh()
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'الطلب غير موجود'], 404);
        } catch (\Throwable $e) {
            Log::error('Order update failed: ' . $e->getMessage());
            return response()->json(['message' => 'خطأ في التحديث: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return response()->json(['message' => 'تم حذف الطلب بنجاح']);
    }

    public function getUserOrders(Request $request)
    {
        try {
            $orders = Order::with(['orderItem.product'])
                ->where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Safe JSON decode for payment_proof (قد يصل مُشفّراً مرتين من مسار الدفع اليدوي)
            $orders->transform(function ($order) {
                if ($order->payment_proof && is_string($order->payment_proof)) {
                    try {
                        $order->payment_proof = json_decode($order->payment_proof, true);
                    } catch (\Throwable $e) {
                        $order->payment_proof = null;
                    }
                }
                return $order;
            });

            return response()->json($orders);
        } catch (\Throwable $e) {
            Log::error('getUserOrders failed: ' . $e->getMessage());
            return response()->json(['message' => 'خطأ في جلب الطلبات'], 500);
        }
    }

    public function getOrdersByStatus($status)
    {
        try {
            $orders = Order::with(['user', 'orderItem.product'])
                ->where('status', $status)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($orders, 200);
        } catch (\Throwable $e) {
            Log::error('getOrdersByStatus failed: ' . $e->getMessage());
            return response()->json(['message' => 'خطأ في جلب الطلبات'], 500);
        }
    }

    public function getMonthlySalesReport($month, $year)
    {
        try {
            $monthlySales = Order::whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->sum('total_price');

            return response()->json([
                'month'        => $month,
                'year'         => $year,
                'total_sales'  => $monthlySales
            ], 200);
        } catch (\Throwable $e) {
            Log::error('getMonthlySalesReport failed: ' . $e->getMessage());
            return response()->json(['message' => 'خطأ في التقرير'], 500);
        }
    }

    /**
     * جلب جميع الطلبات للأدمن - FIXED: Load product images relationship for image_url accessor
     */
    public function getOrdersToAdmin()
    {
        try {
            $orders = Order::with([
                'user:id,name,email',
                'user.profile:id,user_id,phone,email,name,total_purchases',
                // FIXED: Load product.images relationship so image_url accessor works
                'orderItem.product.images',
            ])
                ->orderBy('created_at', 'desc')
                ->get();

            // Safe JSON decode for payment_proof
            $orders->transform(function ($order) {
                if ($order->payment_proof && is_string($order->payment_proof)) {
                    try {
                        $order->payment_proof = json_decode($order->payment_proof, true);
                    } catch (\Throwable $e) {
                        $order->payment_proof = null;
                    }
                }
                // Ensure relations exist (prevent null errors in frontend)
                if (!$order->user) {
                    $order->setRelation('user', (object)['name' => 'مستخدم محذوف', 'email' => '', 'profile' => null]);
                }
                if ($order->user && !$order->user->profile) {
                    $order->user->setRelation('profile', (object)['phone' => 'غير متوفر', 'email' => null, 'name' => null, 'total_purchases' => 0]);
                }
                return $order;
            });

            return response()->json(['data' => $orders]);

        } catch (\Throwable $e) {
            Log::error('getOrdersToAdmin CRASHED: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'sql'   => $e->getPrevious()?->getMessage() ?? 'N/A'
            ]);

            return response()->json([
                'data' => [],
                'error' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
