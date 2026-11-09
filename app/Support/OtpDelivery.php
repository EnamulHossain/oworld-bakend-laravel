<?php

namespace App\Support;

use Illuminate\Http\Request;

class OtpDelivery
{
    public static function preview(Request $request): bool
    {
        // A browser flag must never enable OTP disclosure on production.
        $preview = !app()->environment('production')
            && config('services.otp.test_mode', false)
            && !$request->boolean('sms_enabled', true);
        abort_if(!$request->boolean('sms_enabled', true) && !$preview, 422, 'OTP test mode is not enabled on this server.');
        return $preview;
    }
}
