<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Models\Cart;


class CardController extends Controller
{

    public function index()
    {
        $this->authorize('viewAny', Cart::class);

        return response()->json(Cart::all());
    }

 
    public function show(Cart $cart)
    {
        $this->authorize('view', $cart);

        return response()->json($cart);
    }


    public function store(StoreCardRequest $request)
    {
        $this->authorize('create', Cart::class);

        $cart = Cart::create($request->validated());

        return response()->json([
            'message' => 'Card created successfully',
            'data' => $cart
        ], 201);
    }

    public function destroy(Cart $cart)
    {
        $this->authorize('delete', $cart);

        $cart->delete();

        return response()->json([
            'message' => 'Card deleted successfully'
        ]);
    }

    

    public function update(UpdateCardRequest $request, Cart $card)
    {
        $this->authorize('update', $card);

        $card->update($request->validated());

        return response()->json([
            'message' => 'Card updated successfully',
            'data' => $card,
        ]);
    }

 
    public function getUserCart($userId)
    {
        $cart = Cart::where('user_id', $userId)->firstOrFail();

        $this->authorize('view', $cart);

        return response()->json($cart);
    }

 
    public function clearCart($userId)
    {
        $cart = Cart::where('user_id', $userId)->firstOrFail();

        $this->authorize('update', $cart);

        $cart->cartItem()->delete();

        return response()->json([
            'message' => 'Cart cleared successfully'
        ]);
    }


    public function calculateTotal($userId)
    {
        $cart = Cart::where('user_id', $userId)
            ->with('cartItem')
            ->firstOrFail();

        $this->authorize('view', $cart);

        $total = $cart->cartItem->sum(fn ($item) =>
            $item->quantity * $item->unit_price
        );

        return response()->json([
            'total_price' => $total
        ]);
    }
}
