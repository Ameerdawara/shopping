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
     * إرسال رمز التحقق عبر Rasel SMS API
     * Public signature is unchanged — AuthController requires no changes.
     */
    public function sendOtp(string $rawPhone, string $code): bool
    {
        // 1. Normalize to E.164 using existing logic — unchanged
        $phone = PhoneNumber::normalize($rawPhone);

        if (!$phone) {
            Log::error('SmsService: Invalid phone number format', [
                'input' => $rawPhone,
            ]);
            return false;
        }

        // Rasel's E.164 example keeps the "+" — do not strip it
        $message = "رمز التحقق الخاص بك في مودرن ماركت هو: {$code}";

        // -----------------------------------------------------------
        // RASEL-SPECIFIC REQUEST BODY
        // ⚠️ Field names below are taken from what you described from
        // your dashboard docs. I could NOT independently verify these
        // against Rasel's live technical docs (raselsms.com/api-docs
        // blocks automated access). Confirm against your dashboard and
        // adjust ONLY this $payload array if any field name differs.
        // -----------------------------------------------------------
        $payload = [
            'to'          => [$phone],
            'channel'     => 'local_sms',
            'messageType' => 'otp',
            'content'     => $message, // ⚠️ unverified field name — see note H below
        ];

        $maskedPhone = substr($phone, 0, 5) . str_repeat('*', max(strlen($phone) - 7, 0)) . substr($phone, -2);

        Log::channel('sms')->info('SmsService: Attempting Send', [
            'provider'     => 'rasel',
            'endpoint'     => $this->url,
            'recipient'    => $maskedPhone,
            'message_type' => 'otp',
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

            // Response may not be valid JSON — guard against that
            $responseBody = null;
            $jsonError    = null;
            try {
                $responseBody = $response->json();
            } catch (\Throwable $e) {
                $jsonError = $e->getMessage();
            }

            Log::channel('sms')->info('SmsService: Response Received', [
                'provider'     => 'rasel',
                'endpoint'     => $this->url,
                'recipient'    => $maskedPhone,
                'message_type' => 'otp',
                'http_status'  => $httpStatus,
                'response'     => $responseBody,
                'json_error'   => $jsonError,
            ]);

            if ($jsonError !== null || !is_array($responseBody)) {
                Log::error('SmsService: Malformed/unexpected JSON response', [
                    'provider'    => 'rasel',
                    'http_status' => $httpStatus,
                    'raw_body'    => $response->body(),
                    'recipient'   => $maskedPhone,
                ]);
                return false;
            }

            // -----------------------------------------------------------
            // SUCCESS DETECTION
            // ⚠️ I don't have Rasel's confirmed success response shape.
            // Primary signal: HTTP 2xx. Secondary guard: explicit error
            // flags some APIs return even on HTTP 200 (e.g. "success":
            // false, "error": "...", "status": "failed"/"error").
            // After your first real test call, send me the actual JSON
            // body and I'll replace this with an exact check.
            // -----------------------------------------------------------
            $looksLikeExplicitFailure =
                (array_key_exists('success', $responseBody) && $responseBody['success'] === false)
                || (array_key_exists('error', $responseBody) && !empty($responseBody['error']))
                || (isset($responseBody['status']) && in_array(strtolower((string) $responseBody['status']), ['failed', 'error', 'rejected'], true));

            if ($response->successful() && !$looksLikeExplicitFailure) {
                return true;
            }

            Log::error('SmsService: API Error', [
                'provider'     => 'rasel',
                'http_status'  => $httpStatus,
                'response'     => $responseBody,
                'recipient'    => $maskedPhone,
            ]);

            return false;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('SmsService: Network/Connection Error (timeout or unreachable)', [
                'provider'  => 'rasel',
                'endpoint'  => $this->url,
                'error'     => $e->getMessage(),
                'recipient' => $maskedPhone,
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('SmsService: Unexpected Exception', [
                'provider'  => 'rasel',
                'endpoint'  => $this->url,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'recipient' => $maskedPhone,
            ]);
            return false;
        }
    }
}
