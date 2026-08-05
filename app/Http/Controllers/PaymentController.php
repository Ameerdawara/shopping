<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Services\ShamCashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function __construct(protected ShamCashService $shamCash)
    {
    }

    /**
     * إعدادات الدفع العامة (Public) - تستخدمها صفحة الدفع لعرض
     * رابط/عنوان محفظة شام كاش وصورة QR الخاصة بها.
     */
    public function settings()
    {
        return response()->json([
            'shamcash' => [
                'qr_url'         => PaymentSetting::get('shamcash_qr_url'),
                'wallet_address' => PaymentSetting::get('shamcash_wallet_address'),
            ],
        ]);
    }

    /**
     * تحديث إعدادات شام كاش (صورة QR + عنوان/رابط المحفظة) - Admin فقط.
     * (USDT لم تُضَف هون بناءً على طلبك، رح نضيفها لاحقاً)
     */
    public function updateShamCashSettings(Request $request)
    {
        $data = $request->validate([
            'wallet_address' => 'required|string',
            'qr_image'       => 'nullable|image|max:4096',
        ]);

        PaymentSetting::set('shamcash_wallet_address', $data['wallet_address']);

        if ($request->hasFile('qr_image')) {
            $path = $request->file('qr_image')->store('qr', 'public');
            PaymentSetting::set('shamcash_qr_url', Storage::url($path));
        }

        return response()->json([
            'message' => 'تم تحديث إعدادات شام كاش بنجاح',
            'data'    => [
                'qr_url'         => PaymentSetting::get('shamcash_qr_url'),
                'wallet_address' => PaymentSetting::get('shamcash_wallet_address'),
            ],
        ]);
    }

    /**
     * إنشاء طلب جديد + فاتورة دفع عبر شام كاش.
     *
     * الخطوات:
     * 1) إنشاء Order بحالة pending وغير مدفوع، بعملة الزبون المختارة.
     * 2) حساب المجموع بالليرة (عملة تخزين المنتجات) ثم تحويله للعملة
     *    المختارة إذا كانت USD.
     * 3) إنشاء فاتورة عند شام كاش وربطها بالطلب عبر invoice_number.
     * 4) إرجاع رابط فتح التطبيق للفرونت + عنوان المحفظة كبديل يدوي.
     */
    public function createShamCashInvoice(Request $request)
    {
        $data = $request->validate([
            'shipping_address' => 'nullable|string',
            'currency'         => 'required|in:SYP,USD',
        ]);

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

        // TODO: تأكد من اتجاه سعر الصرف عندك (كم ليرة = 1 دولار؟).
        // هون افترضنا أن rate = عدد الليرات السورية مقابل 1 دولار،
        // فبالتالي القسمة تحول من ليرة إلى دولار. إذا كان الاتجاه
        // عكسي عندك، بدّل القسمة لضرب.
        $amount = $data['currency'] === 'USD'
            ? round($totalSyp / max($rate, 0.0001), 2)
            : round($totalSyp, 2);

        // عنوان المحفظة يُقرأ من إعدادات لوحة التحكم (يحدّثها المدير من
        // صفحة payImages.html) وليس من .env، حتى يقدر المدير يغيّره من
        // الواجهة مباشرة بدون الحاجة للوصول للسيرفر.
        $walletAddress = PaymentSetting::get('shamcash_wallet_address')
            ?? config('services.shamcash.wallet_address');

        if (empty($walletAddress)) {
            return response()->json([
                'message' => 'لم يتم ضبط عنوان محفظة شام كاش بعد، الرجاء التواصل مع الإدارة',
            ], 422);
        }

        $order = Order::create([
            'user_id'          => $user->id,
            'total_price'      => $totalSyp,
            'currency'         => $data['currency'],
            'payment_method'   => 'shamcash',
            'status'           => 'pending',
            'is_paid'          => false,
            'shipping_address' => $data['shipping_address'] ?? 'عنوان غير محدد',
        ]);

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

        $invoiceNumber = 'INV-' . now()->format('Ymd') . '-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);

        try {
            $invoiceResponse = $this->shamCash->createInvoice(
                $invoiceNumber,
                $amount,
                $data['currency'],
                $walletAddress,
                "طلب رقم {$order->id}"
            );
        } catch (\Throwable $e) {
            Log::error('ShamCash invoice creation failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            $order->update(['status' => 'cancelled']);

            return response()->json(['message' => 'تعذر إنشاء فاتورة الدفع، حاول مرة أخرى'], 502);
        }

        $order->update(['invoice_number' => $invoiceNumber]);

        Payment::create([
            'order_id'       => $order->id,
            'method'         => 'shamcash',
            'invoice_number' => $invoiceNumber,
            'amount'         => $amount,
            'currency'       => $data['currency'],
            'status'         => 'pending',
            'raw_payload'    => $invoiceResponse,
        ]);

        // تفريغ السلة بعد إنشاء الطلب والفاتورة بنجاح
        $cart->cartItem()->delete();

        return response()->json([
            'order_id'       => $order->id,
            'invoice_number' => $invoiceNumber,
            'amount'         => $amount,
            'currency'       => $data['currency'],
            // TODO: إذا رجّع API حقل جاهز (paymentUrl/checkoutUrl) استخدمه
            // بدل buildDeepLink، هو الأدق والأضمن لفتح التطبيق فعلياً.
            'deep_link'      => $invoiceResponse['paymentUrl'] ?? $this->shamCash->buildDeepLink($invoiceNumber),
            'wallet_address' => PaymentSetting::get('shamcash_wallet_address'),
        ], 201);
    }

    /**
     * يستخدمه الفرونت للـ polling بعد رجوع المستخدم من تطبيق شام كاش،
     * لمعرفة إذا تأكد الدفع (عبر الـ webhook) أم لا بعد.
     */
    public function checkOrderStatus($orderId)
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'order_id' => $order->id,
            'status'   => $order->status,
            'is_paid'  => (bool) $order->is_paid,
        ]);
    }

    /**
     * Webhook شام كاش - Public (يُستدعى من سيرفر شام كاش مباشرة، بدون
     * auth:sanctum لأن الزبون نفسه لا يستدعيه).
     *
     * الأمان: لا نثق بمحتوى الطلب أبداً. بمجرد استلامه نتحقق من حالة
     * الفاتورة عبر /v1/invoices/{invoiceNumber}/verify باستخدام مفتاحنا
     * السري (server-to-server)، ونعتمد فقط على نتيجة verify لتحديد
     * هل الطلب "مقبول" فعلاً أم لا.
     *
     * TODO: إذا عندك اسم header للتوقيع (مثل X-ShamCash-Signature) من
     * التوثيق الكامل، أضف تحقق hash_hmac هون كطبقة حماية إضافية قبل
     * استدعاء verifyInvoice.
     */
    public function shamCashWebhook(Request $request)
    {
        $payload = $request->all();
        $invoiceNumber = $payload['invoiceNumber'] ?? null;

        if (! $invoiceNumber) {
            return response()->json(['message' => 'invoiceNumber مفقود'], 422);
        }

        $payment = Payment::where('invoice_number', $invoiceNumber)->first();
        if (! $payment) {
            Log::warning('ShamCash webhook: فاتورة غير معروفة', ['invoiceNumber' => $invoiceNumber]);

            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);
        }

        // فاتورة منتهية الصلاحية
        if (($payload['event'] ?? null) === 'invoice.expired') {
            $payment->update(['status' => 'expired']);
            $payment->order?->update(['status' => 'cancelled']);

            return response()->json(['message' => 'تم تسجيل انتهاء صلاحية الفاتورة']);
        }

        try {
            $verified = $this->shamCash->verifyInvoice($invoiceNumber);
        } catch (\Throwable $e) {
            Log::error('ShamCash webhook: فشل التحقق من الفاتورة', [
                'invoiceNumber' => $invoiceNumber,
                'error'         => $e->getMessage(),
            ]);

            return response()->json(['message' => 'تعذر التحقق'], 502);
        }

        // TODO: تأكد من اسم حقل الحالة الفعلي في رد /verify (افترضنا status)
        $status = $verified['status'] ?? null;

        if ($status !== 'paid') {
            return response()->json(['message' => 'الحالة غير مؤكدة كمدفوعة بعد من سيرفر شام كاش']);
        }

        $order = $payment->order;

        $payment->update([
            'status'          => 'paid',
            'transaction_ref' => $verified['transactionRef'] ?? ($payload['transactionRef'] ?? null),
            'counterparty'    => $verified['counterparty'] ?? ($payload['counterparty'] ?? null),
            'paid_at'         => now(),
            'raw_payload'     => $verified,
        ]);

        $order->update([
            'is_paid'                  => true,
            'status'                   => 'processing', // = الطلب "مقبول" ودخل قيد التجهيز
            'shamcash_transaction_ref' => $payment->transaction_ref,
            'paid_at'                  => now(),
        ]);

        return response()->json(['message' => 'تم تأكيد الدفع وقبول الطلب بنجاح']);
    }
}
