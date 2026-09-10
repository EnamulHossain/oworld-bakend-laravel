<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GenNetSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignupOtpController extends Controller
{
    public function send(Request $request, GenNetSmsService $sms)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/']]);
        $phone = $this->normalizePhone($data['phone']);
        $otpKey = 'signup_otp:'.hash('sha256', $phone);
        $cooldownKey = $otpKey.':cooldown';

        if (Cache::has($cooldownKey)) {
            throw ValidationException::withMessages(['phone' => 'Please wait before requesting another OTP.']);
        }

        $otp = (string) random_int(100000, 999999);
        $reference = 'OTP'.now()->format('ymdHis').Str::upper(Str::random(4));
        $sms->send($phone, "Your oWorld verification code is {$otp}. It expires in 5 minutes. Do not share this code.", $reference);

        Cache::put($otpKey, ['hash' => hash('sha256', $otp), 'attempts' => 0], now()->addMinutes(5));
        Cache::put($cooldownKey, true, now()->addSeconds(60));

        return response()->json(['success' => true, 'message' => 'A verification code has been sent to your phone.']);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'otp' => ['required', 'digits:6'],
        ]);
        $phone = $this->normalizePhone($data['phone']);
        $otpKey = 'signup_otp:'.hash('sha256', $phone);
        $record = Cache::get($otpKey);

        if (!$record || ($record['attempts'] ?? 0) >= 5) {
            throw ValidationException::withMessages(['otp' => 'The verification code has expired. Please request a new one.']);
        }
        if (!hash_equals($record['hash'], hash('sha256', $data['otp']))) {
            $record['attempts'] = ($record['attempts'] ?? 0) + 1;
            Cache::put($otpKey, $record, now()->addMinutes(5));
            throw ValidationException::withMessages(['otp' => 'The verification code is incorrect.']);
        }

        Cache::forget($otpKey);
        $verificationToken = Str::random(64);
        Cache::put('signup_otp_verified:'.hash('sha256', $verificationToken), $phone, now()->addMinutes(10));

        return response()->json(['success' => true, 'verification_token' => $verificationToken]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        return str_starts_with($digits, '88') ? $digits : '88'.$digits;
    }
}
