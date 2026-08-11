<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsGateService
{
    protected string $url;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->url      = config('services.smsgate.url');
        $this->username = config('services.smsgate.username');
        $this->password = config('services.smsgate.password');
    }

    /**
     * إرسال رمز التحقق عبر بوابة SMSGate المحلية (Android app HTTP API)
     */
    public function sendOtp(string $phone, string $code): bool
    {
        $message = "رمز التحقق الخاص بك في مودرن ماركت هو: {$code}";

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(10)
                ->post($this->url, [
                    'textMessage'  => ['text' => $message],
                    'phoneNumbers' => [$phone],
                    // إيقاف تقارير التسليم: هذا هو الحل الموثّق رسمياً لخطأ
                    // RESULT_ERROR_GENERIC_FAILURE في حال كانت الشريحة/الرصيد/الشبكة سليمة
                    'withDeliveryReport' => false,
                ]);

            if ($response->successful()) {
                Log::info("SmsGateService: OTP sent successfully to {$phone}");
                return true;
            }

            Log::error("SmsGateService: failed to send OTP to {$phone}. Status: {$response->status()} Body: {$response->body()}");
            return false;
        } catch (\Throwable $e) {
            Log::error("SmsGateService: exception while sending OTP to {$phone}: " . $e->getMessage());
            return false;
        }
    }
}