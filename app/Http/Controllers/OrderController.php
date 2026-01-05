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
        $user = Auth::user();

        $cart = Cart::where('user_id', $user->id)
            ->with('cartItem')
            ->first();

        if (!$cart || $cart->cartItem->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty'
            ], 400);
        }

        $totalPrice = 0;

        // ✅ حساب السعر من السلة (بعد الخصم)
        foreach ($cart->cartItem as $item) {
            $totalPrice += $item->unit_price * $item->quantity;
        }

        $order = Order::create([
            'user_id'          => $user->id,
            'total_price'      => $totalPrice,
            'status'           => 'pending',
            'shipping_address' => $request->input('shipping_address', 'عنوان غير محدد'),
        ]);

        foreach ($cart->cartItem as $item) {

            $orderItemData = [
                'product_id' => $item->product_id,
                'quantity'   => $item->quantity,
                'price'      => $item->unit_price, // ✅ السعر بعد الخصم
                'color'      => $item->color,
            ];

            if (!empty($item->size)) {
                $orderItemData['size'] = $item->size;
            }

            $order->orderItem()->create($orderItemData);
        }

        // تفريغ السلة
        $cart->cartItem()->delete();

        return response()->json([
            'message' => 'Order created successfully',
            'order'   => $order
        ], 201);
    }






    public function updateOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,processing,cancelled',
            'shipping_address' => 'sometimes|string',
            'delivered_at' => 'nullable|date',
        ]);


        $order->update($validated);

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

    public function getUserOrders()
    {

        $user = Auth::user();
        $orders = Order::where('user_id', $user->id)->get();

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
    public function getOrdersToAdmin()
    {
        $orders = Order::with([
            'user:id,name',
            'user.profile:id,user_id,phone',
            'orderItem.product:id,name'
        ])->get();

        return response()->json([
            'data' => $orders
        ]);
    }
}
