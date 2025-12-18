<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    /**
     * عرض عناصر السلة
     */
   public function index($cartId)
{
    $cart = Cart::with('cartItem.product')->findOrFail($cartId);
    $this->authorize('view', $cart);

    return response()->json($cart);
}

    /**
     * إضافة منتج إلى السلة
     */
    public function store(Request $request, $cartId)
    {
        $cart = Cart::findOrFail($cartId);
        $this->authorize('update', $cart);

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'sometimes|integer|min:1'
        ]);

        // هل المنتج موجود مسبقًا؟
        $item = CartItem::where('cart_id', $cartId)
            ->where('product_id', $data['product_id'])
            ->first();

        if ($item) {
            $product = Product::findOrFail($data['product_id']);

            $item->update([
                'quantity'   => $item->quantity + ($data['quantity'] ?? 1),
                'unit_price' => $product->price,
            ]);
        } else {
            $product = Product::findOrFail($data['product_id']);
            $item = CartItem::create([
                'cart_id'    => $cartId,
                'product_id' => $data['product_id'],
                'quantity'   => $data['quantity'] ?? 1,
                'unit_price' => $product->price ?? 0
            ]);
        }

        return response()->json($item, 201);
    }

    /**
     * تحديث كمية منتج
     */
    public function update(Request $request, $cartId, $itemId)
    {
        $cart = Cart::findOrFail($cartId);
        $this->authorize('update', $cart);

        $item = CartItem::where('cart_id', $cartId)
            ->findOrFail($itemId);

        $data = $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $item->update($data);

        return response()->json($item);
    }

    /**
     * حذف منتج من السلة
     */
    public function destroy($cartId, $itemId)
    {
        $cart = Cart::findOrFail($cartId);
        $this->authorize('update', $cart);

        $item = CartItem::where('cart_id', $cartId)
            ->findOrFail($itemId);

        $item->delete();

        return response()->json([
            'message' => 'Item removed from cart'
        ]);
    }
}
