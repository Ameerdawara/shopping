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
     * ✅ الحقول أدناه مطابقة لتوثيق /v1/invoices الرسمي (صفحة "إنشاء فاتورة"
     * بلوحة تحكم شام كاش): amount (number), currency (SYP|USD|EUR),
     * walletAddress (مطلوب), webhookUrl (اختياري), expiresInMinutes
     * (اختياري)، metadata (اختياري - object). الرد يحتوي invoiceId الذي
     * يُستخدم لاحقاً بالتحقق (verify) والـ webhook.
     *
     * ⚠️ walletAddress هون لازم يكون معرّف محفظتنا نحن عند شام كاش (UUID أو
     * hex32 - راجع getWallets())، وليس رقم هاتف أو نص حر يدخله الأدمن.
     * إرسال قيمة غير موجودة فعلياً عند شام كاش هو سبب خطأ
     * "NOT_FOUND: المحفظة غير موجودة".
     *
     * ⚠️ webhookUrl: لو ما انبعت، شام كاش ما رح يقدر يبلّغنا تلقائياً لما
     * يصير الدفع (يعني الـ webhook عندنا بـ PaymentController ما رح
     * يُستدعى أبداً، والاعتماد بيصير كلياً على التأكيد اليدوي من الزبون).
     * لذلك إذا الكونترولر ما مرّر webhookUrl صراحة، نستخدم رابط الـ webhook
     * الفعلي عندنا تلقائياً بدل ما نتركه فاضي.
     */
    public function createInvoice(float $amount, string $currency, string $walletAddress, array $metadata = [], ?string $webhookUrl = null, ?int $expiresInMinutes = null): array
    {
        $webhookUrl = $webhookUrl ?: url('/api/webhooks/shamcash');

        // المحاولة الأولى: amount كرقم (number) كما يذكر التوثيق الرسمي حرفياً.
        $payload  = $this->buildInvoicePayload($amount, $currency, $walletAddress, $metadata, $webhookUrl, $expiresInMinutes, asString: false);
        $response = $this->postInvoice($payload);

        // ⚠️ سبق ولاحظنا فعلياً (وقت تجربة سابقة) رد خطأ "Expected string,
        // received number" من نفس الـ API لنفس الحقل amount، بالتناقض مع
        // التوثيق. بدل ما "نقرر" مسبقاً أي نوع صحيح ونخاطر نكسر الطلبات لو
        // تغيّر سلوك الـ API، نجرّب أولاً بالشكل الموثّق (number)، وإذا
        // فشل الطلب تحديداً بسبب amount، نعيد المحاولة مرة واحدة فقط
        // بإرسالها كنص (string).
        if (! $response->successful() && $this->looksLikeAmountTypeError($response)) {
            Log::warning('ShamCash: فشل إنشاء الفاتورة بـ amount كرقم، إعادة المحاولة كنص (string)', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            $payload  = $this->buildInvoicePayload($amount, $currency, $walletAddress, $metadata, $webhookUrl, $expiresInMinutes, asString: true);
            $response = $this->postInvoice($payload);
        }

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

    private function buildInvoicePayload(float $amount, string $currency, string $walletAddress, array $metadata, string $webhookUrl, ?int $expiresInMinutes, bool $asString): array
    {
        $payload = [
            'amount'        => $asString ? (string) $amount : $amount,
            'currency'      => $currency,
            'walletAddress' => $walletAddress,
            'webhookUrl'    => $webhookUrl,
        ];

        if (! empty($metadata)) {
            $payload['metadata'] = $metadata;
        }
        if ($expiresInMinutes) {
            $payload['expiresInMinutes'] = $expiresInMinutes;
        }

        return $payload;
    }

    private function postInvoice(array $payload): \Illuminate\Http\Client\Response
    {
        // ⚠️ لا نستخدم retry() هون - POST /v1/invoices عملية غير idempotent
        // (كل استدعاء ناجح ينشئ فاتورة جديدة). لو الطلب فعلياً وصل ونُفّذ
        // عند شام كاش لكن الرد ضاع عندنا بسبب انقطاع مؤقت بالاتصال، إعادة
        // المحاولة تلقائياً كانت رح تنشئ فاتورة ثانية مكررة لنفس الطلب دون
        // علمنا. نكتفي بمهلة أقصر (بدل الافتراضي 30 ثانية) ونترك التعامل
        // مع الفشل (استثناء) لـ createShamCashInvoice في الكونترولر، اللي
        // بيلغي الطلب ويطلب من الزبون يعيد المحاولة يدوياً بدل تكرار تلقائي.
        return Http::withHeaders($this->headers())
            ->timeout(10)
            ->connectTimeout(5)
            ->post("{$this->baseUrl}/v1/invoices", $payload);
    }

    /**
     * فحص بسيط: هل رسالة الخطأ الراجعة من شام كاش تتكلم تحديداً عن نوع
     * حقل amount (زودز/فاليديشن مثل "Expected string, received number"
     * أو العكس)؟ نستخدمه فقط لتقرير هل نعيد محاولة إنشاء الفاتورة بنوع
     * مختلف لـ amount، وليس لأي خطأ آخر (401/422 لأسباب ثانية...).
     */
    private function looksLikeAmountTypeError(\Illuminate\Http\Client\Response $response): bool
    {
        $body = strtolower((string) $response->body());

        return str_contains($body, 'amount') && (
            str_contains($body, 'expected string') ||
            str_contains($body, 'expected number') ||
            str_contains($body, 'invalid_type')
        );
    }

    /**
     * جلب محافظنا المسجّلة عند شام كاش (GET /v1/wallets).
     *
     * هاد هو المصدر الصحيح لقيمة walletAddress المستخدمة في createInvoice.
     * لا تستخدم قيمة يكتبها الأدمن يدوياً (مثل رقم هاتف) كـ walletAddress -
     * شام كاش يتحقق أنها معرّف محفظة حقيقي (UUID/hex32) عندهم.
     *
     * ✅ الحقل الصحيح مؤكد من رد فعلي هو walletAddress (وليس id/address) -
     * راجع منطق الاختيار في PaymentController::createShamCashInvoice.
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
