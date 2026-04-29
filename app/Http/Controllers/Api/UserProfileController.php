<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsUser;
use App\Http\Controllers\Controller;
use App\Models\CouponDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UserProfileController extends Controller
{
    use FormatsUser;

    public function show(Request $request)
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    public function coupons(Request $request)
    {
        $user = $request->user();

        $items = CouponDetail::query()
            ->with([
                'couponMaster:id,name,image,description,campaign_type,organization_id,status,start_date,start_time,end_date,end_time,usage_limit_per_user',
                'couponMaster.organization:id,organization_name,username',
                'tier:id,coupon_id,label,discount_type,discount_value,max_discount_amount,min_order_amount,referral_required_count',
            ])
            ->where(function ($query) use ($user) {
                $query->where('claimed_by_user_id', $user->id)
                    ->orWhere('user_id', $user->id);
            })
            ->orderByDesc('claimed_at')
            ->orderByDesc('used_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (CouponDetail $detail) {
                $coupon = $detail->couponMaster;
                $tier = $detail->tier;

                return [
                    'id' => $detail->id,
                    'campaign_id' => $coupon?->id,
                    'campaign_name' => $coupon?->name,
                    'image' => $coupon?->image,
                    'description' => $coupon?->description,
                    'campaign_type' => $coupon?->campaign_type,
                    'code' => $detail->coupon,
                    'offer_id' => $detail->offer_id,
                    'event_id' => $detail->event_id,
                    'organization_name' => $coupon?->organization?->organization_name
                        ?: $coupon?->organization?->username,
                    'status' => $coupon?->status,
                    'tier_label' => $tier?->label,
                    'discount_type' => $detail->discount_type,
                    'discount_value' => $detail->discount_value !== null ? (float) $detail->discount_value : null,
                    'max_discount_amount' => $detail->max_discount_amount !== null ? (float) $detail->max_discount_amount : null,
                    'min_order_amount' => $detail->min_order_amount !== null ? (float) $detail->min_order_amount : null,
                    'claimed_at' => optional($detail->claimed_at)?->toISOString(),
                    'used_at' => optional($detail->used_at)?->toISOString(),
                    'is_used' => (bool) $detail->is_used,
                    'start_date' => $coupon?->start_date?->format('Y-m-d'),
                    'end_date' => $coupon?->end_date?->format('Y-m-d'),
                ];
            })
            ->values();

        return response()->json([
            'coupons' => $items,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => [
                'nullable',
                'string',
                'min:3',
                'max:50',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'full_name' => ['nullable', 'string', 'max:120'],
            'about' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        foreach (['username', 'full_name', 'about', 'phone'] as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field] ?? null;
            }
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->formatUser($user),
        ]);
    }

    public function updateAvatar(Request $request)
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar && !str_starts_with($user->avatar, 'http') && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar = $path;
        $user->save();

        return response()->json([
            'message' => 'Avatar updated successfully.',
            'user' => $this->formatUser($user),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(6)],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 422);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return response()->json(['message' => 'Password updated successfully.']);
    }
}
