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

        $allowedSizes = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'required|string',
            'price'       => 'required|numeric|min:0',

            'images'      => 'required|array|min:1',
            'images.*'    => 'image|mimes:jpg,jpeg,png,webp|max:2048',

            'sizes'              => 'required|array|min:1',
            'sizes.*.size'       => ['required', 'string', Rule::in($allowedSizes)],
            'sizes.*.price'      => 'nullable|numeric|min:0',
            'sizes.*.stock'      => 'required|integer|min:0',
        ]);

        // إنشاء المنتج
        $product = Product::create([
            'name'        => $data['name'],
            'description' => $data['description'],
            'price'       => $data['price'],
            'buyCount'    => 0
        ]);

        // رفع الصور
        foreach ($data['images'] as $image) {
            $path = $image->store('products', 'public');

            $product->images()->create([
                'image' => $path
            ]);
        }

        // إضافة المقاسات
        foreach ($data['sizes'] as $size) {
            $product->sizes()->create($size);
        }

        return response()->json(
            $product->load(['images', 'sizes']),
            201
        );
    }

    /**
     * عرض منتج واحد مع الصور والمقاسات
     */
    public function show(Product $product)
    {
        return response()->json($product->load(['images', 'sizes']));
    }

    /**
     * تحديث منتج (Admin فقط)
     */
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $allowedSizes = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price'       => 'sometimes|numeric|min:0',
            'buyCount'    => 'sometimes|integer|min:0',

            'sizes'              => 'sometimes|array|min:1',
            'sizes.*.size'       => ['required', 'string', Rule::in($allowedSizes)],
            'sizes.*.price'      => 'nullable|numeric|min:0',
            'sizes.*.stock'      => 'required|integer|min:0',
        ]);

        // تحديث بيانات المنتج
        $product->update($data);

        // تحديث المقاسات إذا أرسلت
        if (!empty($data['sizes'])) {
            $product->sizes()->delete(); // حذف القديم
            foreach ($data['sizes'] as $size) {
                $product->sizes()->create($size);
            }
        }

        return response()->json($product->load('sizes'));
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
