<?php
// routes/test_sms.php - Access via: https://api.tasswek.com/test-sms-hostinger
// DELETE THIS FILE/ROUTE AFTER TESTING!

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

Route::get('/test-sms-hostinger', function () {
    $url      = config('services.smsgate.url');
    $user     = config('services.smsgate.username');
    $pass     = config('services.smsgate.password');
    $testPhone = '+9639XXXXXXXXX'; // REPLACE WITH YOUR REAL PHONE IN E.164

    Log::info('Hostinger Test: Starting cURL check');

    try {
        $response = Http::withBasicAuth($user, $pass)
            ->timeout(15)
            ->post($url, [
                'textMessage'  => ['text' => 'Hostinger Connectivity Test'],
                'phoneNumbers' => [$testPhone],
                'withDeliveryReport' => false,
            ]);

        $status = $response->status();
        $body   = $response->json();

        Log::info('Hostinger Test: Result', ['status' => $status, 'body' => $body]);

        return response()->json([
            'hostinger_curl' => 'OK',
            'gateway_status' => $status,
            'gateway_body'   => $body,
            'ssl_verify'     => 'OK (Default CA Bundle Used)',
        ]);
    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        Log::error('Hostinger Test: Connection Failed', ['error' => $e->getMessage()]);
        return response()->json([
            'hostinger_curl' => 'FAILED',
            'error'          => $e->getMessage(),
            'hint'           => 'Hostinger Firewall blocking outbound 443 OR php.ini curl.cainfo missing. Contact Support.',
        ], 500);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
