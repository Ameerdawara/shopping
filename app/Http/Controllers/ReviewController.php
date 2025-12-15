<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;

class ReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum'); // يحمي كل الدوال
    }

    // عرض كل التقييمات
    public function index()
    {
        return response()->json(Review::all(), 200);
    }

    // عرض تقييم محدد
    public function show(Review $review)
    {
        return response()->json($review, 200);
    }

    // إنشاء تقييم جديد
    public function store(StoreReviewRequest $request)
    {
        $review = Review::create($request->validated());

        return response()->json([
            'message' => 'Review created successfully',
            'data' => $review,
        ], 201);
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        $this->authorize('update', $review); // للتحقق من Policy إذا أردت

        $review->update($request->validated());

        return response()->json([
            'message' => 'Review updated successfully',
            'data' => $review,
        ], 200);
    }

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review); 

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully',
        ], 200);
    }

    // عرض التقييمات الخاصة بمنتج معين
    public function getReviewsByProduct($productId)
    {
        $reviews = Review::where('product_id', $productId)->get();

        return response()->json($reviews, 200);
    }

    // عرض التقييمات الخاصة بمستخدم معين
    public function getReviewsByUser($userId)
    {
        $reviews = Review::where('user_id', $userId)->get();

        return response()->json($reviews, 200);
    }
}
