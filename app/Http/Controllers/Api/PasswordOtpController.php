<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GenNetSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordOtpController extends Controller
{
    private function phone(Request $request): string
    {
        $data = $request->validate(['phone' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/']]);
        $digits = ltrim($data['phone'], '+');
        return str_starts_with($digits, '88') ? $digits : '88'.$digits;
    }

    private function account(string $phone): User
    {
        $users = User::whereIn('phone', [$phone, '+'.$phone, substr($phone, 2)])->get();
        if ($users->count() !== 1) {
            throw ValidationException::withMessages(['phone' => $users->isEmpty()
                ? 'No account is registered with this phone number.'
                : 'This phone number belongs to multiple accounts. Please contact support.']);
        }
        return $users->first();
    }

    public function send(Request $request, GenNetSmsService $sms)
    {
        $phone = $this->phone($request);
        $user = $this->account($phone);
        $key = 'password_otp:'.hash('sha256', $phone);
        return Cache::lock($key.':lock', 30)->block(3, function () use ($key, $phone, $user, $sms) {
            if (Cache::has($key.':cooldown')) {
                throw ValidationException::withMessages(['phone' => 'Please wait 60 seconds before requesting another code.']);
            }
            $otp = (string) random_int(100000, 999999);
            try {
                $sms->send($phone, "Your O’World verification code is {$otp}. Valid for 5 minutes. Do not share this code with anyone.\n\nHappy exploring!\nO’World", Str::upper(Str::random(20)));
            } catch (\Throwable $exception) {
                // Provider exceptions can contain credentials or the OTP; never expose them.
                return response()->json(['message' => 'Could not send the verification code. Please try again later.'], 503);
            }
            Cache::put($key, ['hash' => Hash::make($otp), 'user_id' => $user->id, 'password_hash' => $user->password,
                'attempts' => 0, 'expires_at' => now()->addMinutes(5)->timestamp], 300);
            Cache::put($key.':cooldown', true, 60);
            return response()->json(['message' => 'Verification code sent.', 'retry_after' => 60]);
        });
    }

    public function verify(Request $request)
    {
        $phone = $this->phone($request);
        $data = $request->validate(['otp' => ['required', 'digits:6']]);
        $key = 'password_otp:'.hash('sha256', $phone);
        return Cache::lock('password_otp:'.hash('sha256', $phone).':lock', 10)->block(3, function () use ($key, $phone, $data) {
            $record = Cache::get($key);
            if (!$record || $record['expires_at'] <= now()->timestamp || $record['attempts'] >= 5) {
                throw ValidationException::withMessages(['otp' => 'The code has expired or too many attempts were made. Request a new code.']);
            }
            if (!Hash::check($data['otp'], $record['hash'])) {
                $record['attempts']++;
                Cache::put($key, $record, max(1, $record['expires_at'] - now()->timestamp));
                throw ValidationException::withMessages(['otp' => 'The verification code is incorrect.']);
            }
            Cache::forget($key);
            $token = Str::random(64);
            Cache::put('password_reset:'.hash('sha256', $token), [
                'phone' => $phone, 'user_id' => $record['user_id'], 'password_hash' => $record['password_hash'],
            ], 600);
            return response()->json(['reset_token' => $token]);
        });
    }

    public function reset(Request $request)
    {
        $phone = $this->phone($request);
        $data = $request->validate([
            'reset_token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'confirmed', 'min:6', 'max:128'],
        ]);
        $key = 'password_reset:'.hash('sha256', $data['reset_token']);
        return Cache::lock('password_otp:'.hash('sha256', $phone).':lock', 30)->block(3, function () use ($key, $phone, $data) {
            $record = Cache::get($key);
            $user = $this->account($phone);
            if (!$record || $record['phone'] !== $phone || $record['user_id'] !== $user->id
                || !hash_equals($record['password_hash'], $user->password)) {
                throw ValidationException::withMessages(['reset_token' => 'Password reset verification has expired. Please verify your phone again.']);
            }
            DB::transaction(function () use ($user, $data) {
                $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            });
            Cache::forget($key);
            Cache::forget('password_otp:'.hash('sha256', $phone));
            return response()->json(['message' => 'Password updated. Log in with your new password.']);
        });
    }
}
