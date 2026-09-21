<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    /**
     * عرض كل الزبائن (role = user) مع عدد طلباتهم.
     * الحماية موجودة على مستوى الراوت عبر middleware ['auth:sanctum','admin'].
     */
    public function index(Request $request)
    {
        try {
            // ملاحظة: تم حذف عمود address من هنا لأنه غير موجود فعلياً
            // بجدول profiles على قاعدة البيانات — كان يسبب خطأ
            // "Unknown column 'address'" (500) عند كل طلب لهذا الراوت
            $query = User::where('role', 'user')
                ->withCount('orders')
                ->with('profile:id,user_id,phone');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $customers = $query->orderBy('created_at', 'desc')->get();

            return response()->json(['data' => $customers]);
        } catch (\Throwable $e) {
            Log::error('CustomerController@index failed: ' . $e->getMessage());
            return response()->json(['message' => 'خطأ في جلب قائمة الزبائن'], 500);
        }
    }

    /**
     * حذف حساب زبون نهائياً.
     *
     * يُمنع الحذف إذا كان للزبون طلبات سابقة — حفاظاً على سجل الطلبات
     * (order_items.order_id -> orders.user_id) وتجنباً لخطأ قيد
     * المفتاح الأجنبي (foreign key constraint) الذي يحدث لو حاولنا
     * حذف مستخدم لديه طلبات مرتبطة به. نفس منطق الحماية المستخدم أصلاً
     * في ProductController::destroy() للمنتجات المرتبطة بطلبات معلّقة.
     */
    public function destroy($id)
    {
        $user = User::where('role', 'user')->find($id);

        if (!$user) {
            return response()->json(['message' => 'الزبون غير موجود'], 404);
        }

        if ($user->orders()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف هذا الزبون لأن لديه طلبات مرتبطة بحسابه'
            ], 409);
        }

        try {
            DB::transaction(function () use ($user) {
                // إزالة أي بيانات مرتبطة أولاً حتى لا يفشل الحذف بسبب
                // قيود المفاتيح الأجنبية (cart_items -> carts -> users)
                $cart = $user->carts;
                if ($cart) {
                    $cart->cartItem()->delete();
                    $cart->delete();
                }

                $user->profile()->delete();
                $user->reviews()->delete();
                $user->notifications()->delete();
                $user->tokens()->delete();

                $user->delete();
            });

            return response()->json(['message' => 'تم حذف حساب الزبون بنجاح']);
        } catch (\Throwable $e) {
            Log::error('CustomerController@destroy failed: ' . $e->getMessage());
            return response()->json(['message' => 'تعذر حذف الزبون، حاول مرة أخرى'], 500);
        }
    }
}
