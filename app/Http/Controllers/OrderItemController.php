<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Http\Requests\StoreOrderItemRequest;
use App\Http\Requests\UpdateOrderItemRequest;

class OrderItemController extends Controller
{
    public function index()
    {
        return response()->json(OrderItem::all(), 200);
    }

    public function show(OrderItem $orderItem)
    {
        return response()->json($orderItem, 200);
    }

    public function store(StoreOrderItemRequest $request)
    {
        $orderItem = OrderItem::create($request->validated());

        return response()->json([
            'message' => 'Order item created successfully',
            'data'    => $orderItem,
        ], 201);
    }

    public function update(UpdateOrderItemRequest $request, OrderItem $orderItem)
    {
        $orderItem->update($request->validated());

        return response()->json([
            'message' => 'Order item updated successfully',
            'data'    => $orderItem,
        ], 200);
    }

    public function destroy(OrderItem $orderItem)
    {
        $orderItem->delete();

        return response()->json([
            'message' => 'Order item deleted successfully',
        ], 200);
    }

    public function getItemsByOrder($orderId)
    {
        $orderItems = OrderItem::where('order_id', $orderId)->get();

        return response()->json($orderItems, 200);
    }

 


}
