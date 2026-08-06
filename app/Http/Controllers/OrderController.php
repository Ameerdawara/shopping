<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use App\Models\ExchangeRate;

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
     * إنشاء طلب بالدفع كاش (Cash on Delivery)
     * يلتقط IP و User-Agent للتدقيق
     */
    public function store(Request $request)
    {
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

        $order = Order::create([
            'user_id'           => $user->id,
            'total_price'       => $totalPrice,
            'currency'          => $currency,
            'payment_method'    => 'cash',
            'status'            => 'pending',
            'is_paid'           => $data['is_paid'] ?? false,
            'shipping_address'  => $data['shipping_address'] ?? 'عنوان غير محدد',
            'ip_address'        => $request->ip(),
            'user_agent'        => $request->userAgent(),
        ]);

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

        return response()->json([
            'message' => 'تم إنشاء الطلب بنجاح',
            'order'   => $order->load('orderItem.product')
        ], 201);
    }

    public function updateOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status'           => 'sometimes|string|in:pending,pending_approval,processing,cancelled,completed',
            'shipping_address' => 'sometimes|string',
            'delivered_at'     => 'nullable|date',
        ]);

        $order->update($validated);

        return response()->json([
            'message' => 'تم تحديث الطلب بنجاح',
            'data'    => $order->fresh()
        ]);
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return response()->json(['message' => 'تم حذف الطلب بنجاح']);
    }

    public function getUserOrders(Request $request)
    {
        $orders = Order::with(['orderItem.product'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }

    public function getOrdersByStatus($status)
    {
        $orders = Order::with(['user', 'orderItem.product'])
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders, 200);
    }

    public function getMonthlySalesReport($month, $year)
    {
        $monthlySales = Order::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('total_price');

        return response()->json([
            'month'        => $month,
            'year'         => $year,
            'total_sales'  => $monthlySales
        ], 200);
    }

    /**
     * جلب جميع الطلبات للأدمن مع العلاقات الكاملة للـ Commercial Ledger
     * يتضمن: user, profile, orderItems مع products, payment_proof
     */
    public function getOrdersToAdmin()
    {
        $orders = Order::with([
            'user:id,name,email',
            'user.profile:id,user_id,phone,address',
            'orderItem.product:id,name,price,image_url',
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        // التأكد من فك تشفير payment_proof إذا كان string
        $orders->transform(function ($order) {
            if ($order->payment_proof && is_string($order->payment_proof)) {
                $order->payment_proof = json_decode($order->payment_proof, true);
            }
            return $order;
        });

        return response()->json(['data' => $orders]);
    }
}
