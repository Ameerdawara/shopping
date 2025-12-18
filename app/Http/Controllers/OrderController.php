<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;



class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum'); // يحمي كل الدوال ويجعل auth()->user() موجود
    }

    public function index()
    {
        return response()->json(Order::all(), 200);
    }

    public function show(Order $order)
    {
        return response()->json($order, 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user(); // أو أي طريقة للحصول على المستخدم
        $cart = Cart::where('user_id', $user->id)->first();

        if (!$cart || $cart->cartItem->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $totalPrice = 0;

        foreach ($cart->cartItem as $item) {
            $productPrice = Product::find($item->product_id)->price;
            $totalPrice += $productPrice * $item->quantity;
        }

        $order = Order::create([
            'user_id' => $user->id,
            'total_price' => $totalPrice,
            'status' => 'pending',
            'shipping_address' => $request->input('shipping_address', 'عنوان غير محدد'),
        ]);

        // نقل العناصر من السلة إلى الطلب (مثال)
        foreach ($cart->cartItem as $item) {
            $order->orderItem()->create([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => Product::Find($item->product_id)->price,
            ]);
        }
    

        // مسح السلة بعد إنشاء الطلب
        $cart->cartItem()->delete();

        return response()->json(['message' => 'Order created', 'order' => $order]);
    }




    public function update(UpdateOrderRequest $request, Order $order)
    {
        $order->update($request->validated());

        return response()->json([
            'message' => 'Order updated successfully',
            'data' => $order
        ]);
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully'
        ]);
    }

    public function getUserOrders($userId)
    {
        $orders = Order::where('user_id', $userId)->get();

        return response()->json($orders, 200);
    }


    public function getOrdersByStatus($status)
    {
        $orders = Order::where('status', $status)->get();

        return response()->json($orders, 200);
    }


    public function getMonthlySalesReport($month, $year)
    {
        $monthlySales = Order::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('total_price');

        return response()->json([
            'month' => $month,
            'year' => $year,
            'total_sales' => $monthlySales
        ], 200);
    }
}
