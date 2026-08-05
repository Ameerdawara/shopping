<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShamCashService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.shamcash.base_url'), '/');
        $this->apiKey  = (string) config('services.shamcash.api_key');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
        ];
    }

    /**
     * إنشاء فاتورة دفع جديدة في شام كاش.
     *
     * ✅ الحقول أدناه مطابقة لتوثيق /v1/invoices الفعلي (لقطة الشاشة "إنشاء
     * فاتورة"): amount, currency, walletAddress, webhookUrl, expiresInMinutes,
     * metadata. لا يوجد حقل invoiceNumber في التوثيق - شام كاش هو من يولّد
     * invoiceId ويرجعه في الـ response، نحن لا نرسله. أي رقم/مرجع خاص فينا
     * (order id) نمرره داخل metadata فقط للتتبّع.
     *
     * ⚠️ walletAddress هون لازم يكون معرّف محفظتنا نحن عند شام كاش (UUID أو
     * hex32 - راجع getWallets())، وليس رقم هاتف أو نص حر يدخله الأدمن.
     * إرسال قيمة غير موجودة فعلياً عند شام كاش هو سبب خطأ
     * "NOT_FOUND: المحفظة غير موجودة".
     */
    public function createInvoice(float $amount, string $currency, string $walletAddress, array $metadata = [], ?string $webhookUrl = null, ?int $expiresInMinutes = null): array
    {
        $payload = [
            // ⚠️ API شام كاش يتوقع amount كنص (string) وليس رقم،
            // حسب رسالة الخطأ الفعلية "Expected string, received number"
            // (رغم أن التوثيق المعروض يذكرها كـ number - اعتمدنا سلوك الـ API الفعلي)
            'amount'        => (string) $amount,
            'currency'      => $currency,
            'walletAddress' => $walletAddress,
        ];

        if (! empty($metadata)) {
            $payload['metadata'] = $metadata;
        }
        if ($webhookUrl) {
            $payload['webhookUrl'] = $webhookUrl;
        }
        if ($expiresInMinutes) {
            $payload['expiresInMinutes'] = $expiresInMinutes;
        }

        // مهلة أقصر (بدل الافتراضي 30 ثانية) + إعادة محاولة تلقائية عند مشاكل
// الاتصال فقط - لأن الاستضافة الحالية عندها انقطاع متقطع في الاتصالات
// الصادرة (نفس الطلب نجح أحياناً وتعلّق أحياناً أخرى دون تغيير بالبيانات)
$response = Http::withHeaders($this->headers())
    ->timeout(10)
    ->connectTimeout(5)
    ->retry(2, 300, function ($exception) {
        // أعد المحاولة فقط عند مشاكل شبكة/اتصال، وليس عند أخطاء منطقية
        // من شام كاش نفسه (401/422...) لأنها لن تتغير بالإعادة
        return $exception instanceof \Illuminate\Http\Client\ConnectionException;
    })
    ->post("{$this->baseUrl}/v1/invoices", $payload);

        if (! $response->successful()) {
            Log::error('ShamCash: فشل إنشاء الفاتورة', [
                'status'  => $response->status(),
                'body'    => $response->body(),
                'payload' => $payload,
            ]);
            throw new \RuntimeException('تعذر إنشاء فاتورة الدفع عبر شام كاش');
        }

        return $response->json();
    }

    /**
     * جلب محافظنا المسجّلة عند شام كاش (GET /v1/wallets).
     *
     * هاد هو المصدر الصحيح لقيمة walletAddress المستخدمة في createInvoice.
     * لا تستخدم قيمة يكتبها الأدمن يدوياً (مثل رقم هاتف) كـ walletAddress -
     * شام كاش يتحقق أنها معرّف محفظة حقيقي (UUID/hex32) عندهم.
     *
     * TODO: عدّل اسم الحقل ($wallet['walletAddress'] أو $wallet['id'] أو
     * $wallet['address']) حسب الشكل الفعلي لعنصر المحفظة في الـ response،
     * لأنه لم يصلني بالضبط - فقط شكل جدول التوثيق.
     */
    public function getWallets(): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/v1/wallets");

        if (! $response->successful()) {
            Log::error('ShamCash: فشل جلب المحافظ', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('تعذر جلب محافظ شام كاش');
        }

        return $response->json();
    }

    public function getInvoice(string $invoiceNumber): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/v1/invoices/{$invoiceNumber}");

        if (! $response->successful()) {
            throw new \RuntimeException('تعذر جلب بيانات الفاتورة من شام كاش');
        }

        return $response->json();
    }

    /**
     * التحقق من حالة الفاتورة مباشرة من سيرفر شام كاش (server-to-server).
     *
     * هذا أهم إجراء أمني في كل التكامل: أي طرف يقدر يرسل POST مزوّر
     * لرابط الـ webhook عندنا ويدّعي أن الفاتورة "مدفوعة"، لكن ما حدا
     * غير شام كاش يقدر يرجّع "paid" فعلياً من نقطة /verify باستخدام
     * مفتاحنا السري. لذلك webhook عندنا لا يثق بمحتواه، ويتحقق دائماً
     * عبر هذه الدالة قبل ما يعتبر أي طلب مدفوعاً.
     */
   public function verifyInvoice(string $invoiceNumber, string $tranId): array
{
    $response = Http::withHeaders($this->headers())
        ->post("{$this->baseUrl}/v1/invoices/{$invoiceNumber}/verify", [
            'tran_id' => $tranId,
        ]);

    if (! $response->successful()) {
        Log::error('ShamCash: فشل التحقق من الفاتورة', [
            'invoiceNumber' => $invoiceNumber,
            'status'        => $response->status(),
            'body'          => $response->body(),
        ]);
        throw new \RuntimeException('تعذر التحقق من الفاتورة عبر شام كاش');
    }

    return $response->json();
}

    /**
     * رابط فتح تطبيق شام كاش مباشرة لإتمام الدفع.
     *
     * TODO: إذا كان response الخاص بـ createInvoice يحتوي على حقل جاهز
     * مثل paymentUrl أو checkoutUrl أو deepLink، استخدمه مباشرة من
     * الـ controller بدل هذه الدالة (راجع PaymentController).
     * الصيغة أدناه افتراضية فقط لحين تأكيدها من توثيقك.
     */
    public function buildDeepLink(string $invoiceNumber): string
    {
        return "shamcash://pay?invoice={$invoiceNumber}";
    }
}
