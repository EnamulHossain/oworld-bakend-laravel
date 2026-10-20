<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PhoneVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'user', 403, 'Phone confirmation is only available for user accounts.');
        $data = $request->validate(['phone' => ['required', 'string', 'regex:/^\+8801[3-9]\d{8}$/']]);
        $phone = $data['phone'];
        return Cache::lock('verified_phone:'.hash('sha256', $phone), 15)->block(3, function () use ($phone, $user) {
            if (User::where('id', '!=', $user->id)->whereIn('phone', [$phone, substr($phone, 1), substr($phone, 3)])->exists()) {
                throw ValidationException::withMessages(['phone' => 'This phone number is already registered to another account.']);
            }
            DB::transaction(function () use ($phone, $user) {
                $user->phone = $phone;
                $user->save();
                $user->isphoneverified = true;
                $user->save();
            });
            return response()->json(['message' => 'Phone number confirmed.',
                'phone' => $user->phone, 'isphoneverified' => true]);
        });
    }
}
