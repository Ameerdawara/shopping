<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
  use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class ProductController extends Controller
{
    /**
     * عرض جميع المنتجات مع الصور والمقاسات
     */
    public function index(Request $request)
    {
        $query = Product::with(['images', 'sizes', 'activeOffer', 'category']);

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        return ProductResource::collection(
            $query->limit(10)->get()
        );
    }

    /**
     * عرض منتج واحد حسب الـ ID
     */
   public function show($productId)
{
    // جلب المنتج مع العلاقات المطلوبة
    $product = Product::with(['images', 'sizes', 'category'])->find($productId);

    if (!$product) {
        return response()->json([
            'message' => 'المنتج غير موجود'
        ], 404);
    }

    return new ProductResource($product);
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
            // التصنيف: يقبل category_id (الطريقة الجديدة) أو category كنص (توافق مع عملاء API القدامى)
            'category_id' => 'nullable|exists:categories,id',
            'category'    => 'required_without:category_id|nullable|string|max:255',
            'brand'       => 'nullable|string|max:255',

            // الصور + اللون
            'images'               => 'required|array|min:1',
            'images.*.file'        => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'images.*.color'       => 'required|string|max:50',

            // المقاسات
            'sizes'                => 'nullable|array|min:1',
            'sizes.*.size'         => 'nullable|string|max:50',
        ]);

        $category = $this->resolveCategory($data);

        // إنشاء المنتج
        $product = Product::create([
            'name'        => $data['name'],
            'description' => $data['description'],
            'price'       => $data['price'],
            'category_id' => $category?->id,
            'category'    => $category?->name, // يبقى العمود النصي متزامناً لأي كود قديم ما زال يقرأه مباشرة
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
            $product->load(['images', 'sizes', 'category'])
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
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'category'    => 'sometimes|nullable|string|max:255',
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
        $updatePayload = collect($data)->except(['images', 'sizes', 'category', 'category_id'])->toArray();

        // نحدّث التصنيف فقط إذا تم إرساله فعلاً في الطلب، لتجنب مسحه بالخطأ عند تحديث حقول أخرى
        if (array_key_exists('category_id', $data) || array_key_exists('category', $data)) {
            $category = $this->resolveCategory($data);
            $updatePayload['category_id'] = $category?->id;
            $updatePayload['category']    = $category?->name;
        }

        $product->update($updatePayload);

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
            $product->load(['images', 'sizes', 'category'])
        );
    }
    /**
     * حذف منتج (Admin فقط)
     */


public function destroy(Product $product)
{
    $this->authorize('delete', $product);

    // 🔴 منع الحذف إذا المنتج مرتبط بطلبات غير منتهية
    $hasActiveOrders = $product->orderItems()
        ->whereHas('order', function ($q) {
            $q->where('status', 'pending');
        })
        ->exists();

    if ($hasActiveOrders) {
        return response()->json([
            'message' => 'لا يمكن حذف المنتج لأنه مرتبط بطلبات قيد الانتظار'
        ], 409);
    }

    DB::transaction(function () use ($product) {
        $product->orderItems()
            ->whereHas('order', fn ($q) =>
                $q->whereIn('status', ['cancelled', 'processing'])
            )
            ->delete();
        $product->delete();

        if ($product->image && Storage::exists($product->image)) {
            Storage::delete($product->image);
        }
    });

    return response()->json([
        'message' => 'تم حذف المنتج بنجاح'
    ]);
}


    // ProductController.php
    public function byCategory($category)
    {
        $categoryModel = is_numeric($category)
            ? Category::find($category)
            : Category::where('slug', $category)->first();

        if (!$categoryModel) {
            return response()->json([
                'message' => 'التصنيف غير موجود'
            ], 404);
        }

        return ProductResource::collection(
            Product::with(['images', 'activeOffer', 'category'])
                ->where('category_id', $categoryModel->id)
                ->get()
        );
    }

    /**
     * يحوّل بيانات التصنيف القادمة من الطلب (category_id الجديد أو category النصي القديم)
     * إلى موديل Category واحد، منشئاً تصنيفاً جديداً تلقائياً إذا كان النص غير موجود بعد.
     */
    private function resolveCategory(array $data): ?Category
    {
        if (!empty($data['category_id'])) {
            return Category::find($data['category_id']);
        }

        if (!empty($data['category'])) {
            return Category::firstOrCreate(
                ['name' => $data['category']],
                ['slug' => Str::slug($data['category'])]
            );
        }

        return null;
    }
}
