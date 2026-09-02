<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ExchangeRate;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            // نتأكد أن المنتج موجود وغير محذوف (soft delete) قبل السماح بإضافته للسلة
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'quantity'   => 'sometimes|integer|min:1',
            'color'      => 'nullable|string',
            'size'       => 'nullable|string'
        ]);

        // جلب المنتج
        $product = Product::findOrFail($data['product_id']);

        // 🔥 البحث عن العرض (offer) الخاص بالمنتج
        $offer = Offer::where('product_id', $product->id)
            ->first();

        // حساب السعر الأساسي بعد الخصم إذا وُجد عرض (بالعملة الأساسية)
        $basePrice = $product->price_syp;
        if ($offer && !empty($offer->discount_percentage) && $offer->discount_percentage > 0) {
            $basePrice = $basePrice - ($basePrice * ($offer->discount_percentage / 100));
        }

        // ملاحظة: $product->price مخزّن أصلاً بالليرة السورية (SYP)، لذلك لا نضربه
        // بسعر الصرف هنا. سعر الصرف نخزّنه فقط كسجل تدقيق (audit) لمعرفة
        // السعر المعتمد وقت إضافة المنتج للسلة، دون أن يؤثر على قيمة unit_price.
        $exchangeRate = ExchangeRate::current();
        $price = round($basePrice, 2);

        // البحث عن عنصر مطابق (product + color + size)
        $item = CartItem::where('cart_id', $cartId)
            ->where('product_id', $data['product_id'])
            ->where('color', $data['color'] ?? null)
            ->where('size', $data['size'] ?? null)
            ->first();

        if ($item) {
            // تحديث الكمية والسعر (نعيد حساب السعر بآخر سعر صرف عند كل إضافة)
            $item->update([
                'quantity'      => $item->quantity + ($data['quantity'] ?? 1),
                'unit_price'    => $price,
                'exchange_rate' => $exchangeRate,
            ]);
        } else {
            // إنشاء عنصر جديد
            $item = CartItem::create([
                'cart_id'       => $cartId,
                'product_id'    => $data['product_id'],
                'quantity'      => $data['quantity'] ?? 1,
                'unit_price'    => $price,
                'exchange_rate' => $exchangeRate,
                'color'         => $data['color'] ?? null,
                'size'          => $data['size'] ?? null,
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

        // تحديث الكمية فقط - السعر وسعر الصرف يبقيان كما أُضيفا أول مرة
        // (إذا بدك تحديث السعر لآخر سعر صرف عند كل تعديل كمية، خبرني وبضيفها)
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
