<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GenNetSmsService
{
    public function send(string $msisdn, string $message, string $reference): void
    {
        $domain = rtrim((string) config('services.gennet.domain'), '/');
        $token = (string) config('services.gennet.api_token');
        $sid = (string) config('services.gennet.sid');

        if ($domain === '' || $token === '' || $sid === '') {
            throw new RuntimeException('The SMS service is not configured.');
        }

        $response = Http::asJson()
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 300)
            ->post("{$domain}/api/v3/send-sms", [
                'api_token' => $token,
                'sid' => $sid,
                'msisdn' => $msisdn,
                'sms' => $message,
                'csms_id' => $reference,
            ]);

        $data = $response->json();
        if (!$response->successful() || strtoupper((string) ($data['status'] ?? '')) !== 'SUCCESS') {
            throw new RuntimeException((string) ($data['error_message'] ?? 'The SMS provider rejected the request.'));
        }
    }
}
