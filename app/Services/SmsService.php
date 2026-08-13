<?php

namespace App\Services;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $url;
    protected string $apiKey;
    protected string $senderId;

    public function __construct()
    {
        $this->url      = config('services.eazysendsms.url');
        $this->apiKey   = config('services.eazysendsms.api_key');
        $this->senderId = config('services.eazysendsms.sender_id');
    }

    /**
     * إرسال رمز التحقق عبر EazySendSMS REST API v1
     */
    public function sendOtp(string $rawPhone, string $code): bool
    {
        // 1. تطبيع الرقم إلى E.164 (نفس منطق PhoneNumber المستخدم سابقاً)
        $phone = PhoneNumber::normalize($rawPhone);

        if (!$phone) {
            Log::error('SmsService: Invalid phone number format', [
                'input' => $rawPhone,
            ]);
            return false;
        }

        // EazySendSMS يتوقع الرقم بدون علامة + (مثال: 963999239151)
        $toNumber = ltrim($phone, '+');

        $message = "رمز التحقق الخاص بك في مودرن ماركت هو: {$code}";

        $payload = [
            'from' => $this->senderId,
            'to'   => $toNumber,
            'text' => $message,
            'type' => '1', // Unicode - إلزامي للنصوص العربية
        ];

        Log::channel('sms')->info('SmsService: Attempting Send', [
            'endpoint' => $this->url,
            'payload'  => $payload,
        ]);

        try {
            $response = Http::withHeaders([
                    'apikey'       => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->timeout(15)
                ->post($this->url, $payload);

            $responseBody = $response->json();
            $status       = $response->status();
            $apiStatus    = $responseBody['status'] ?? null;

            Log::channel('sms')->info('SmsService: Response Received', [
                'http_status' => $status,
                'response'    => $responseBody,
                'sent_to'     => $toNumber,
            ]);

            // نجاح الإرسال يتطلب: HTTP ناجح + status == 'OK' من EazySendSMS
            if ($response->successful() && $apiStatus === 'OK') {
                return true;
            }

            Log::error('SmsService: API Error', [
                'http_status' => $status,
                'response'    => $responseBody,
                'phone'       => $toNumber,
            ]);

            return false;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('SmsService: Network/Connection Error', [
                'error' => $e->getMessage(),
                'phone' => $toNumber,
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('SmsService: Unexpected Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'phone' => $toNumber,
            ]);
            return false;
        }
    }
}
