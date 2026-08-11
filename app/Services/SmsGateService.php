<?php

namespace App\Services;

use App\Support\PhoneNumber;
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

    public function sendOtp(string $rawPhone, string $code): bool
    {
        // 1. NORMALIZE TO E.164 (Critical for Android SmsManager)
        $phone = PhoneNumber::normalize($rawPhone);

        if (!$phone) {
            Log::error('SmsGateService: Invalid phone number format', [
                'input' => $rawPhone,
                'message' => 'Phone number could not be normalized to E.164. Check PhoneNumber::COUNTRY_CODE/LENGTH config.'
            ]);
            return false;
        }

        $message = "رمز التحقق الخاص بك في مودرن ماركت هو: {$code}";

        // 2. PAYLOAD - Match SMS Gate API Spec exactly
        $payload = [
            'textMessage'        => ['text' => $message],
            'phoneNumbers'       => [$phone], // MUST be array of E.164 strings
            'withDeliveryReport' => false,    // Prevents Generic Failure on many Android versions
            // 'simSlot' => 1, // Uncomment if device is Dual SIM and you know the slot (1 or 2)
        ];

        // 3. DEBUG LOG: Log EXACT payload sent to SMS Gate Cloud
        Log::channel('sms')->info('SmsGateService: Attempting Send', [
            'endpoint' => $this->url,
            'payload'  => $payload, // Contains the formatted +963... number
        ]);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(15) // Increase timeout slightly for cloud relay
                ->acceptJson()
                ->post($this->url, $payload);

            // 4. LOG RAW RESPONSE (Success or Fail)
            $responseBody = $response->json(); // Safer than body()
            $status = $response->status();

            Log::channel('sms')->info('SmsGateService: Response Received', [
                'status' => $status,
                'response' => $responseBody,
                'sent_to' => $phone,
            ]);

            if ($response->successful()) {
                // SMS Gate Cloud accepted the request.
                // Note: This DOES NOT guarantee delivery to handset, only queuing on Android device.
                return true;
            }

            // Handle Specific SMS Gate Errors (e.g., device offline, quota)
            $errorMsg = $responseBody['message'] ?? 'Unknown SMS Gate Error';
            Log::error('SmsGateService: API Error', [
                'status' => $status,
                'error' => $errorMsg,
                'phone' => $phone,
            ]);

            return false;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('SmsGateService: Network/Connection Error (Hostinger -> SMS Gate Cloud)', [
                'error' => $e->getMessage(),
                'phone' => $phone,
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('SmsGateService: Unexpected Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'phone' => $phone,
            ]);
            return false;
        }
    }
}
