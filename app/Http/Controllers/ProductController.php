<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * عرض جميع المنتجات مع الصور والمقاسات
     */
    public function index()
    {
        return response()->json(Product::with(['images', 'sizes'])->get());
    }

    /**
     * إنشاء منتج مع صور ومقاسات
     */
   public function store(Request $request)
{
    $this->authorize('create', Product::class);

    $data = $request->validate([
        'name'        => 'required|string|max:255',
        'description' => 'required|string',
        'price'       => 'required|numeric|min:0',
        'category'    => 'required|string|max:255',

        // الصور
        'images'   => 'required|array|min:1',
        'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',

        // المقاسات
        'sizes'        => 'required|array|min:1',
        'sizes.*.size' => 'required|string|max:50',
    ]);

    $product = Product::create([
        'name'        => $data['name'],
        'description' => $data['description'],
        'price'       => $data['price'],
        'category'    => $data['category'],
        'buyCount'    => 0,
    ]);

    /* حفظ الصور */
    foreach ($request->file('images') as $image) {
        $path = $image->store('products', 'public');

        $product->images()->create([
            'image' => $path,
        ]);
    }

    /* حفظ المقاسات */
    foreach ($data['sizes'] as $size) {
        $product->sizes()->create([
            'size' => $size['size'],
        ]);
    }

    return response()->json(
        $product->load(['images', 'sizes']),
        201
    );
}

    /**
     * تحديث منتج (Admin فقط)
     */
   public function update(Request $request, Product $product)
{
    $this->authorize('update', $product);

    $data = $request->validate([
        'name'        => 'sometimes|string|max:255',
        'description' => 'sometimes|string',
        'price'       => 'sometimes|numeric|min:0',
        'category'    => 'sometimes|string|max:255',
        'buyCount'    => 'sometimes|integer|min:0',

        // الصور (اختياري)
        'images'   => 'sometimes|array|min:1',
        'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',

        // المقاسات (اختياري)
        'sizes'        => 'sometimes|array|min:1',
        'sizes.*.size' => 'required|string|max:50',
    ]);

    /* تحديث بيانات المنتج الأساسية */
    $product->update(
        collect($data)->except(['images', 'sizes'])->toArray()
    );

    /* تحديث المقاسات إذا أُرسلت */
    if (isset($data['sizes'])) {
        $product->sizes()->delete();

        foreach ($data['sizes'] as $size) {
            $product->sizes()->create([
                'size' => $size['size'],
            ]);
        }
    }

    return response()->json(
        $product->load(['images', 'sizes'])
    );
}

    /**
     * حذف منتج (Admin فقط)
     */
    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }
}
