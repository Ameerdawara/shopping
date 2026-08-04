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
     * ⚠️ لم يصلني الشكل الدقيق لجسم الطلب (request body) ولا للـ response
     * الخاص بـ POST /v1/invoices من توثيقك الكامل. الحقول أدناه افتراض
     * منطقي بناءً على أسماء الحقول الظاهرة في مثال الـ webhook
     * (invoiceNumber, amount, currency...). راجع توثيقك وعدّل فقط
     * الأسطر المعلّمة TODO إذا كانت الأسماء مختلفة.
     */
    public function createInvoice(string $invoiceNumber, float $amount, string $currency, string $description = ''): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/v1/invoices", [
                // TODO: تأكد من أسماء هذه الحقول حسب التوثيق الرسمي عندك
                'invoiceNumber' => $invoiceNumber,
                // ⚠️ API شام كاش يتوقع amount كنص (string) وليس رقم،
                // حسب رسالة الخطأ الفعلية "Expected string, received number"
                'amount'        => (string) $amount,
                'currency'      => $currency,
                'description'   => $description,
                'walletAddress' => config('services.shamcash.wallet_address'),
            ]);

        if (! $response->successful()) {
            Log::error('ShamCash: فشل إنشاء الفاتورة', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('تعذر إنشاء فاتورة الدفع عبر شام كاش');
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
    public function verifyInvoice(string $invoiceNumber): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/v1/invoices/{$invoiceNumber}/verify");

        if (! $response->successful()) {
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
