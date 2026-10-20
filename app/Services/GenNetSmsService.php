<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class GenNetSmsService
{
    public function send(string $msisdn, string $message, string $reference): void
    {
        $domain = rtrim((string) config('services.gennet.domain'), '/');
        $token = (string) config('services.gennet.api_token');
        $sid = (string) config('services.gennet.sid');

        if ($domain === '' || $token === '' || $sid === '') {
            Log::warning('Gennet SMS configuration is incomplete', [
                'domain_missing' => $domain === '', 'token_missing' => $token === '', 'sid_missing' => $sid === '',
            ]);
            throw new RuntimeException('The SMS service is not configured.');
        }

        $caBundle = config('services.gennet.ca_bundle') ?: true;
        if ($caBundle !== true && (!is_string($caBundle) || !is_readable($caBundle))) {
            Log::warning('Gennet SMS certificate bundle is missing or unreadable');
            throw new RuntimeException('The SMS certificate bundle is unavailable.');
        }

        try {
            $response = Http::asJson()
            ->withOptions(['verify' => $caBundle])
            ->acceptJson()
            ->timeout(15)
            ->post("{$domain}/api/v3/send-sms", [
                'api_token' => $token,
                'sid' => $sid,
                'msisdn' => $msisdn,
                'sms' => $message,
                'csms_id' => $reference,
            ]);
        } catch (ConnectionException $exception) {
            // Exception messages may include the request body; log only the cURL error number.
            preg_match('/cURL error (\d+)/', $exception->getMessage(), $matches);
            Log::warning('Gennet SMS connection failed', ['curl_error' => $matches[1] ?? null]);
            throw new RuntimeException('The SMS gateway connection failed.');
        }

        $data = $response->json();
        if (!$response->successful() || strtoupper((string) ($data['status'] ?? '')) !== 'SUCCESS'
            || strtoupper((string) ($data['smsinfo'][0]['sms_status'] ?? '')) !== 'SUCCESS') {
            $smsStatus = strtoupper((string) ($data['smsinfo'][0]['sms_status'] ?? ''));
            Log::warning('Gennet SMS request rejected', [
                'http_status' => $response->status(),
                'provider_code' => is_numeric($data['status_code'] ?? null) ? (int) $data['status_code'] : null,
                'sms_status' => in_array($smsStatus, ['SUCCESS', 'INVALID', 'DUPLICATE'], true) ? $smsStatus : 'UNKNOWN',
            ]);
            throw new RuntimeException('The SMS provider rejected the request.');
        }
    }
}
