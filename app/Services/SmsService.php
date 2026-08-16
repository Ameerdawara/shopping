<?php

namespace App\Services;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $url;
    protected string $apiKey;

    public function __construct()
    {
        $this->url    = config('services.rasel.url');
        $this->apiKey = config('services.rasel.api_key');
    }

    /**
     * إرسال رمز التحقق عبر Rasel SMS API (messages/send - free_text)
     * Public signature unchanged — AuthController requires no changes.
     */
    public function sendOtp(string $rawPhone, string $code): bool
    {
        $phone = PhoneNumber::normalize($rawPhone);

        if (!$phone) {
            Log::error('SmsService: Invalid phone number format', ['input' => $rawPhone]);
            return false;
        }

        $message = "رمز التحقق الخاص بك في مودرن ماركت هو: {$code}";

        $payload = [
            'to'          => $phone,           // string, not array — confirmed
            'channel'     => 'local_sms',
            'messageType' => 'free_text',      // confirmed via live cURL example
            'content'     => [
                'text' => $message,
            ],
        ];

        $maskedPhone = substr($phone, 0, 5) . str_repeat('*', max(strlen($phone) - 7, 0)) . substr($phone, -2);

        Log::channel('sms')->info('SmsService: Attempting Send', [
            'provider' => 'rasel', 'endpoint' => $this->url,
            'recipient' => $maskedPhone, 'message_type' => 'free_text', 'channel' => 'local_sms',
        ]);

        try {
            $response = Http::withHeaders([
                    'X-API-Key'    => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->timeout(15)
                ->post($this->url, $payload);

            $httpStatus = $response->status();

            $responseBody = null;
            $jsonError    = null;
            try {
                $responseBody = $response->json();
            } catch (\Throwable $e) {
                $jsonError = $e->getMessage();
            }

            Log::channel('sms')->info('SmsService: Response Received', [
                'provider' => 'rasel', 'endpoint' => $this->url, 'recipient' => $maskedPhone,
                'message_type' => 'free_text', 'http_status' => $httpStatus,
                'response' => $responseBody, 'json_error' => $jsonError,
            ]);

            if ($jsonError !== null || !is_array($responseBody)) {
                Log::error('SmsService: Malformed/unexpected JSON response', [
                    'provider' => 'rasel', 'http_status' => $httpStatus,
                    'raw_body' => $response->body(), 'recipient' => $maskedPhone,
                ]);
                return false;
            }

            // Confirmed Rasel batch response shape:
            // { "ok": bool, "successCount": int, "failCount": int,
            //   "results": [ { "to": "...", "status": int, "ok": bool, "body": {...} } ] }
            $topLevelOk  = array_key_exists('ok', $responseBody) ? (bool) $responseBody['ok'] : null;
            $firstResult = $responseBody['results'][0] ?? null;
            $resultOk    = is_array($firstResult) && array_key_exists('ok', $firstResult)
                ? (bool) $firstResult['ok']
                : null;

            if ($topLevelOk === true && $resultOk === true) {
                return true;
            }

            Log::error('SmsService: API Error', [
                'provider' => 'rasel', 'http_status' => $httpStatus,
                'response' => $responseBody, 'recipient' => $maskedPhone,
            ]);

            return false;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('SmsService: Network/Connection Error (timeout or unreachable)', [
                'provider' => 'rasel', 'endpoint' => $this->url,
                'error' => $e->getMessage(), 'recipient' => $maskedPhone,
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('SmsService: Unexpected Exception', [
                'provider' => 'rasel', 'endpoint' => $this->url,
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'recipient' => $maskedPhone,
            ]);
            return false;
        }
    }
}
