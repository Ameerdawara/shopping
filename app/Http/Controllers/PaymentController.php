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
        $path = $request->file('qr_image')->store('qr', 'public'); // storage/app/public/qr
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

        // ⚠️ عنوان المحفظة المُستخدم فعلياً مع POST /v1/invoices لازم يكون
        // معرّف محفظتنا الحقيقي عند شام كاش (UUID/hex32)، وليس القيمة التي
        // يكتبها المدير يدوياً في payImages.html (رقم هاتف). القيمة اليدوية
        // كانت سبب خطأ "NOT_FOUND: المحفظة غير موجودة" في اللوغ. لذلك نجلب
        // محفظتنا الحقيقية مباشرة من شام كاش عبر GET /v1/wallets.
        try {
            $wallets = $this->shamCash->getWallets();
        } catch (\Throwable $e) {
            Log::error('ShamCash: تعذر جلب المحفظة قبل إنشاء الفاتورة', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'تعذر الاتصال بشام كاش حالياً، حاول لاحقاً',
            ], 502);
        }

        // TODO: عدّل حسب شكل الـ response الفعلي (قد تكون قائمة مباشرة أو
        // تحت مفتاح 'data'/'wallets').
        $walletList = $wallets['data'] ?? $wallets['wallets'] ?? $wallets;

        // ✅ مؤكد من رد فعلي: الحقل الصحيح هو walletAddress، وليس id.
        // لازم نختار محفظة status = active وعندها walletAddress فعلي،
        // مش أول عنصر بالقائمة (ممكن يكون "pending" وwalletAddress = null).
        $activeWallet = collect($walletList)->first(function ($w) {
            return ($w['status'] ?? null) === 'active' && ! empty($w['walletAddress']);
        });

        $walletAddress = $activeWallet['walletAddress'] ?? null;

        if (empty($walletAddress)) {
            Log::error('ShamCash: لم يتم العثور على محفظة نشطة صالحة', ['response' => $wallets]);
            return response()->json([
                'message' => 'لا توجد محفظة شام كاش نشطة حالياً، الرجاء التواصل مع الإدارة',
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

        try {
            $invoiceResponse = $this->shamCash->createInvoice(
                $amount,
                $data['currency'],
                $walletAddress,
                ['order_id' => $order->id, 'description' => "طلب رقم {$order->id}"]
            );
        } catch (\Throwable $e) {
            Log::error('ShamCash invoice creation failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            $order->update(['status' => 'cancelled']);

            return response()->json(['message' => 'تعذر إنشاء فاتورة الدفع، حاول مرة أخرى'], 502);
        }

        // ✅ invoiceNumber/invoiceId يجب أن يكون القيمة التي يرجعها شام كاش،
        // وليس قيمة نولّدها نحن - غير هيك الـ webhook والتحقق (verify) لاحقاً
        // ما رح يلاقوا الفاتورة عند شام كاش. عدّل اسم الحقل حسب الـ response
        // الفعلي (invoiceId شائع أكثر من invoiceNumber حسب توثيقك).
        $invoiceNumber = $invoiceResponse['invoiceId']
            ?? $invoiceResponse['invoiceNumber']
            ?? $invoiceResponse['id']
            ?? null;

        if (empty($invoiceNumber)) {
            Log::error('ShamCash: الاستجابة لا تحتوي على معرّف فاتورة', ['response' => $invoiceResponse]);
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
            'deep_link' => $invoiceResponse['paymentUrl']
                ?? $invoiceResponse['checkoutUrl']
                ?? $invoiceResponse['deepLink']
                ?? $this->shamCash->buildDeepLink($invoiceNumber),
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
