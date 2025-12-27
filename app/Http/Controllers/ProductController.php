<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
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
        return ProductResource::collection(
            Product::with(['images', 'sizes'])->get()
        );
    }
    /**
 * عرض منتج واحد حسب الـ ID
 */
public function show(Product $product)
{
    return new ProductResource(
        $product->load(['images', 'sizes'])
    );
}

    /**
     * إنشاء منتج مع صور ومقاسات
     */
    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            // بيانات المنتج
            'name'        => 'required|string|max:255',
            'description' => 'required|string',
            'price'       => 'required|numeric|min:0',
            'category'    => 'required|string|max:255',
            'brand'       => 'nullable|string|max:255',

            // الصور + اللون
            'images'               => 'required|array|min:1',
            'images.*.file'        => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'images.*.color'       => 'required|string|max:50',

            // المقاسات
            'sizes'                => 'nullable|array|min:1',
            'sizes.*.size'         => 'nullable|string|max:50',
        ]);

        // إنشاء المنتج
        $product = Product::create([
            'name'        => $data['name'],
            'description' => $data['description'],
            'price'       => $data['price'],
            'category'    => $data['category'],
            'brand'       => $data['brand'] ?? 'N/A',
            'buyCount'    => 0,
        ]);

        // حفظ الصور مع اللون
        foreach ($data['images'] as $img) {
            $path = $img['file']->store('products', 'public');

            $product->images()->create([
                'image' => $path,
                'color' => $img['color'],
            ]);
        }

        // حفظ المقاسات
        if (!empty($data['sizes'])) {
            foreach ($data['sizes'] as $size) {
                $product->sizes()->create([
                    'size' => $size['size'],
                ]);
            }
        }


        return new ProductResource(
            $product->load(['images', 'sizes'])
        );
    }
    /**
     * تحديث منتج (Admin فقط)
     */
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            // بيانات المنتج
            'name'        => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price'       => 'sometimes|numeric|min:0',
            'category'    => 'sometimes|string|max:255',
            'brand'       => 'sometimes|string|max:255',
            'buyCount'    => 'sometimes|integer|min:0',

            // الصور + اللون (اختياري)
            'images'               => 'sometimes|array|min:1',
            'images.*.file'        => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'images.*.color'       => 'required|string|max:50',

            // المقاسات (اختياري)
            'sizes'                => 'sometimes|array|min:1',
            'sizes.*.size'         => 'required|string|max:50',
        ]);

        /* تحديث بيانات المنتج الأساسية */
        $product->update(
            collect($data)->except(['images', 'sizes'])->toArray()
        );

        /* تحديث المقاسات */
        if (isset($data['sizes'])) {
            $product->sizes()->delete();

            foreach ($data['sizes'] as $size) {
                $product->sizes()->create([
                    'size' => $size['size'],
                ]);
            }
        }


        return new ProductResource(
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
    // ProductController.php
    public function byCategory($category)
    {
        return ProductResource::collection(
            Product::with('images')
                ->where('category', $category)
                ->get()
        );
    }
}
