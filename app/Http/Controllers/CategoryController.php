<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * عرض جميع التصنيفات (عام)
     */
    public function index(Request $request)
    {
        $query = Category::withCount('products');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return response()->json(
            $query->orderBy('name')->get()
        );
    }

    /**
     * إنشاء تصنيف جديد (Admin فقط)
     */
    public function store(Request $request)
    {
      $data = $request->validate([
    'name'        => 'required|string|max:255|unique:categories,name',
    'slug'        => 'nullable|string|max:255|unique:categories,slug',
    'description' => 'nullable|string',
    'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
    'icon'        => 'nullable|string|max:255', // تمت إضافة هذا السطر
]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($data);

        return response()->json($category, 201);
    }

    /**
     * تحديث تصنيف (Admin فقط)
     */
    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
    'name'        => 'required|string|max:255|unique:categories,name',
    'slug'        => 'nullable|string|max:255|unique:categories,slug',
    'description' => 'nullable|string',
    'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
    'icon'        => 'nullable|string|max:255', // تمت إضافة هذا السطر
]);

        if ($request->hasFile('image')) {
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        return response()->json($category);
    }

    /**
     * حذف تصنيف (Admin فقط)
     */
    public function destroy(Category $category)
    {
        // 🔴 منع الحذف إذا التصنيف مرتبط بمنتجات حالية (نفس منطق منع حذف المنتج المرتبط بطلبات)
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف التصنيف لأنه مرتبط بمنتجات حالية'
            ], 409);
        }

        if ($category->image && Storage::disk('public')->exists($category->image)) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return response()->json([
            'message' => 'تم حذف التصنيف بنجاح'
        ]);
    }
}
