<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
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

    public function store(StoreOrderRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::create([
            ...$request->validated(),
            'user_id' => $user->id,
        ]);

        $cart = $user->cart;
        if ($cart && $cart->cartItem->count() > 0) {
            foreach ($cart->cartItem as $item) {
                $order->orderItems()->create([
                    'product_id' => $item->product_id,
                    'price'      => $item->price,
                    'quantity'   => $item->quantity,
                ]);
            }
            $cart->cartItem()->delete();
        }

        return response()->json([
            'message' => 'Order created successfully and cart converted to order items',
            'data'    => $order->load('orderItems'),
        ], 201);
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
