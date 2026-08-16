<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * إعدادات الدفع العامة (Public) - تعرض للعملاء في صفحة الدفع
     */
    public function settings()
    {
        return response()->json([
            'shamcash' => [
                'qr_url'         => PaymentSetting::get('shamcash_qr_url'),
                'wallet_address' => PaymentSetting::get('shamcash_wallet_address'),
                'wallet_label'   => PaymentSetting::get('shamcash_wallet_label', 'رقم المحفظة/الهاتف'),
            ],
            'usdt' => [
                'qr_url'         => PaymentSetting::get('usdt_qr_url'),
                'wallet_address' => PaymentSetting::get('usdt_wallet_address'),
                'network'        => PaymentSetting::get('usdt_network', 'TRC20'),
                'wallet_label'   => PaymentSetting::get('usdt_wallet_label', 'عنوان المحفظة'),
            ],
        ]);
    }

    /**
     * تحديث إعدادات شام كاش (Admin فقط)
     */
    public function updateShamCashSettings(Request $request)
    {
        $data = $request->validate([
            'wallet_address' => 'required|string',
            'wallet_label'   => 'nullable|string|max:100',
            'qr_image'       => 'nullable|image|max:4096',
        ]);

        PaymentSetting::set('shamcash_wallet_address', $data['wallet_address']);
        PaymentSetting::set('shamcash_wallet_label', $data['wallet_label'] ?? 'رقم المحفظة/الهاتف');

        if ($request->hasFile('qr_image')) {
            $path = $request->file('qr_image')->store('qr', 'public');
            PaymentSetting::set('shamcash_qr_url', Storage::url($path));
        }

        return response()->json([
            'message' => 'تم تحديث إعدادات شام كاش بنجاح',
            'data'    => [
                'qr_url'         => PaymentSetting::get('shamcash_qr_url'),
                'wallet_address' => PaymentSetting::get('shamcash_wallet_address'),
                'wallet_label'   => PaymentSetting::get('shamcash_wallet_label'),
            ],
        ]);
    }

    /**
     * تحديث إعدادات USDT (Admin فقط)
     */
    public function updateUsdtSettings(Request $request)
    {
        $data = $request->validate([
            'wallet_address' => 'required|string',
            'wallet_label'   => 'nullable|string|max:100',
            'network'        => 'nullable|string|in:TRC20,ERC20,BEP20',
            'qr_image'       => 'nullable|image|max:4096',
        ]);

        PaymentSetting::set('usdt_wallet_address', $data['wallet_address']);
        PaymentSetting::set('usdt_wallet_label', $data['wallet_label'] ?? 'عنوان المحفظة');
        PaymentSetting::set('usdt_network', $data['network'] ?? 'TRC20');

        if ($request->hasFile('qr_image')) {
            $path = $request->file('qr_image')->store('qr', 'public');
            PaymentSetting::set('usdt_qr_url', Storage::url($path));
        }

        return response()->json([
            'message' => 'تم تحديث إعدادات USDT بنجاح',
            'data'    => [
                'qr_url'         => PaymentSetting::get('usdt_qr_url'),
                'wallet_address' => PaymentSetting::get('usdt_wallet_address'),
                'network'        => PaymentSetting::get('usdt_network'),
                'wallet_label'   => PaymentSetting::get('usdt_wallet_label'),
            ],
        ]);
    }

    /**
     * إنشاء طلب جديد مع طريقة دفع يدوية (Sham Cash أو USDT أو كاش)
     * يحل محل createShamCashInvoice القديم
     */
    public function createManualOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|string',
            'currency'         => 'required|in:SYP,USD',
            'payment_method'   => 'required|in:cash,shamcash,usdt',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $user = Auth::user();
        $cart = Cart::where('user_id', $user->id)->with('cartItem')->first();

        if (! $cart || $cart->cartItem->isEmpty()) {
            return response()->json(['message' => 'السلة فارغة'], 400);
        }

        $totalSyp = 0;
        foreach ($cart->cartItem as $item) {
            $totalSyp += $item->unit_price * $item->quantity;
        }

        $rate = ExchangeRate::current();
        $amount = $request->currency === 'USD'
            ? round($totalSyp / max($rate, 0.0001), 2)
            : round($totalSyp, 2);

        // تحديد الحالة الأولية بناءً على طريقة الدفع
        $initialStatus = in_array($request->payment_method, ['shamcash', 'usdt'])
            ? 'pending_approval'
            : 'pending';

        $orderData = [
            'user_id'           => $user->id,
            'total_price'       => $totalSyp, // دائماً مخزن بالليرة السورية (عملة أساس للمحاسبة الداخلية)
            'currency'          => $request->currency,
            'payment_method'    => $request->payment_method,
            'status'            => $initialStatus,
            'is_paid'           => false,
            'shipping_address'  => $request->shipping_address,
            'transaction_id'    => null,
            'sender_name'       => null,
            'payment_proof'     => null,
        ];

        // FIXED: تخزين سعر الصرف وقت إنشاء الطلب (إن وُجد العمود) حتى يمكن لاحقاً عرض
        // المبلغ بالعملة الصحيحة (USD) بدل عرضه دائماً بالليرة السورية في سجل الطلبات.
        if (Schema::hasColumn('orders', 'exchange_rate')) {
            $orderData['exchange_rate'] = $request->currency === 'USD' ? $rate : null;
        }

        $order = Order::create($orderData);

        foreach ($cart->cartItem as $item) {
            $orderItemData = [
                'order_id'   => $order->id,
                'product_id' => $item->product_id,
                'quantity'   => $item->quantity,
                'price'      => $item->unit_price,
                'color'      => $item->color,
            ];

            if (! empty($item->size)) {
                $orderItemData['size'] = $item->size;
            }

            $order->orderItem()->create($orderItemData);
        }

        // تفريغ السلة
        $cart->cartItem()->delete();

        // إشعار الأدمن بوجود طلب جديد يحتاج مراجعة (خاصة الدفع اليدوي)
        NotificationController::notifyAdmins(
            'طلب جديد #' . $order->id,
            'طلب جديد بقيمة ' . number_format($amount) . ' ' . $request->currency . ' عبر ' . $request->payment_method,
            'order',
            $order->id,
            '/Orders/Orders.html?order=' . $order->id
        );

        // إشعار الزبون نفسه بضرورة إكمال بيانات الدفع (لأن رقم العملية واسم المرسل فارغان الآن)
        if (in_array($request->payment_method, ['shamcash', 'usdt'])) {
            NotificationController::notifyUser(
                $user->id,
                'أكمل بيانات الدفع لطلبك #' . $order->id,
                'طلبك بانتظار إرسال رقم العملية واسم المرسل لتأكيد الدفع ومتابعة المعالجة.',
                'payment_incomplete',
                $order->id,
                '/Profile/MyOrders.html?order=' . $order->id
            );
        }

        // إرجاع معلومات الدفع للفرونت
        $paymentInfo = $this->getPaymentMethodInfo($request->payment_method);

        return response()->json([
            'order_id'     => $order->id,
            'amount'       => $amount,
            'currency'     => $request->currency,
            'status'       => $order->status,
            'payment_info' => $paymentInfo,
            'message'      => $initialStatus === 'pending_approval'
                ? 'تم إنشاء الطلب، يرجى إتمام الدفع وإرسال إثبات الدفع للمراجعة'
                : 'تم إنشاء الطلب بنجاح، سيتم التواصل معكم للتوصيل',
        ], 201);
    }

    /**
     * تقديم إثبات الدفع اليدوي من قبل العميل
     * (يُستخدم أيضاً لإكمال بيانات الدفع لاحقاً من صفحة "طلباتي" في حال انقطع
     * العميل عن إكمال الخطوة الثانية بعد إنشاء الطلب مباشرة - انظر ملاحظة FIXED أدناه)
     */
    public function submitPaymentProof(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'sender_name'     => 'required|string|max:255',
            'transaction_id'  => 'required|string|max:255',
            'proof_image'     => 'nullable|image|max:4096', // اختياري: صورة إيصال
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $order = Order::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // التحقق أن الطلب في حالة انتظار موافقة
        if ($order->status !== 'pending_approval') {
            return response()->json([
                'message' => 'هذا الطلب لا يقبل إثبات دفع حالياً'
            ], 422);
        }

        // التحقق أن طريقة الدفع تدعم الإثبات اليدوي
        if (! in_array($order->payment_method, ['shamcash', 'usdt'])) {
            return response()->json([
                'message' => 'طريقة الدفع هذه لا تتطلب إثبات دفع'
            ], 422);
        }

        $proofData = [
            'sender_name'    => $request->sender_name,
            'transaction_id' => $request->transaction_id,
            'submitted_at'   => now()->toISOString(),
        ];

        // حفظ صورة الإيصال إن وجدت
        if ($request->hasFile('proof_image')) {
            $path = $request->file('proof_image')->store('payment_proofs', 'public');
            $proofData['proof_image_url'] = Storage::url($path);
        }

        $order->update([
            'sender_name'     => $request->sender_name,
            'transaction_id'  => $request->transaction_id,
            'payment_proof'   => json_encode($proofData),
            'status'          => 'pending_approval', // يبقى كما هو للأمان
        ]);

        // طمأنة الزبون أن البيانات وصلت وقيد المراجعة
        NotificationController::notifyUser(
            $order->user_id,
            'تم استلام إثبات الدفع لطلبك #' . $order->id,
            'إثبات الدفع قيد المراجعة من قبل الإدارة، سنعلمك فور تأكيد الطلب.',
            'proof_submitted',
            $order->id,
            '/Profile/MyOrders.html?order=' . $order->id
        );

        // تنبيه الأدمن أن هذا الطلب أصبح جاهزاً فعلياً للمراجعة (البيانات مكتملة الآن)
        NotificationController::notifyAdmins(
            'إثبات دفع جاهز للمراجعة #' . $order->id,
            'أرسل الزبون بيانات الدفع للطلب #' . $order->id . ' وهو الآن بانتظار المراجعة والموافقة.',
            'order',
            $order->id,
            '/Orders/Orders.html?order=' . $order->id
        );

        return response()->json([
            'message' => 'تم إرسال إثبات الدفع بنجاح، سيتم مراجعته من قبل الإدارة خلال وقت قصير',
            'order'   => $order->fresh(),
        ]);
    }

    /**
     * الحصول على حالة الطلب (للـ polling)
     */
    public function checkOrderStatus($orderId)
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'order_id'     => $order->id,
            'status'       => $order->status,
            'is_paid'      => (bool) $order->is_paid,
            'payment_method' => $order->payment_method,
            // FIXED: نُعلم الفرونت إن كانت بيانات الإثبات (اسم المرسل/رقم العملية) ناقصة بعد،
            // ليتم استئناف نموذج الإدخال تلقائياً عند تحديث الصفحة بدل ضياع الطلب بلا بيانات.
            'needs_proof'  => $order->status === 'pending_approval'
                && in_array($order->payment_method, ['shamcash', 'usdt'])
                && (empty($order->transaction_id) || empty($order->sender_name)),
        ]);
    }

    /**
     * Admin: الحصول على الطلبات المعلقة للموافقة
     */
    public function getPendingApprovalOrders()
    {
        $orders = Order::with([
            'user:id,name',
            'user.profile:id,user_id,phone',
            'orderItem.product:id,name'
        ])
        ->where('status', 'pending_approval')
        ->orderBy('created_at', 'asc')
        ->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * Admin: الموافقة على طلب (تأكيد الدفع)
     */
    public function approveOrder(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->status !== 'pending_approval') {
            return response()->json(['message' => 'الطلب ليس في حالة انتظار موافقة'], 422);
        }

        $order->update([
            'status'     => 'processing',
            'is_paid'    => true,
            'paid_at'    => now(),
        ]);

        NotificationController::notifyUser(
            $order->user_id,
            'تم قبول طلبك #' . $order->id,
            'تم تأكيد الدفع وجاري تجهيز طلبك للتوصيل',
            'order_approved',
            $order->id,
            '/Profile/MyOrders.html?order=' . $order->id
        );

        return response()->json([
            'message' => 'تم قبول الطلب وتأكيد الدفع بنجاح',
            'order'   => $order->fresh(),
        ]);
    }

    /**
     * Admin: رفض طلب (إلغاء الدفع)
     */
    public function rejectOrder(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($orderId);

        if ($order->status !== 'pending_approval') {
            return response()->json(['message' => 'الطلب ليس في حالة انتظار موافقة'], 422);
        }

        $order->update([
            'status' => 'cancelled',
            'payment_proof' => json_encode(array_merge(
                json_decode($order->payment_proof ?? '{}', true),
                ['rejected_at' => now()->toISOString(), 'rejection_reason' => $request->rejection_reason]
            )),
        ]);

        NotificationController::notifyUser(
            $order->user_id,
            'تم رفض طلبك #' . $order->id,
            $request->rejection_reason ?: 'لم يتم قبول إثبات الدفع، يرجى التواصل مع الدعم',
            'order_rejected',
            $order->id,
            '/Profile/MyOrders.html?order=' . $order->id
        );

        return response()->json([
            'message' => 'تم رفض الطلب وإلغاؤه',
            'order'   => $order->fresh(),
        ]);
    }

    /**
     * دالة مساعدة لجلب معلومات طريقة الدفع
     */
    private function getPaymentMethodInfo(string $method): array
    {
        return match ($method) {
            'shamcash' => [
                'qr_url'         => PaymentSetting::get('shamcash_qr_url'),
                'wallet_address' => PaymentSetting::get('shamcash_wallet_address'),
                'wallet_label'   => PaymentSetting::get('shamcash_wallet_label', 'رقم المحفظة/الهاتف'),
                'instructions'   => 'يرجى تحويل المبلغ إلى المحفظة أعلاه عبر تطبيق شام كاش، ثم أدخل رقم العملية واسم المرسل أدناه.',
            ],
            'usdt' => [
                'qr_url'         => PaymentSetting::get('usdt_qr_url'),
                'wallet_address' => PaymentSetting::get('usdt_wallet_address'),
                'network'        => PaymentSetting::get('usdt_network', 'TRC20'),
                'wallet_label'   => PaymentSetting::get('usdt_wallet_label', 'عنوان المحفظة'),
                'instructions'   => 'يرجى تحويل المبلغ بعملة USDT على شبكة ' . PaymentSetting::get('usdt_network', 'TRC20') . ' إلى العنوان أعلاه، ثم أدخل رقم المعاملة (TxID) واسم المرسل أدناه.',
            ],
            default => [],
        };
    }
}
