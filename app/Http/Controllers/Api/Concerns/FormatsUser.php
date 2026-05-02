<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

trait FormatsUser
{
    protected function formatAvatar(?string $avatar): ?string
    {
        if (!$avatar) {
            return null;
        }

        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return $avatar;
        }

        return Storage::url($avatar);
    }

    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'fullName' => $user->full_name,
            'dob' => $user->dob,
            'gender' => $user->gender,
            'about' => $user->about,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status ?? 'active',
            'organizationName' => $user->organization_name,
            'business_type' => $user->business_type,
            'phone' => $user->phone,
            'avatar' => $this->formatAvatar($user->avatar),
            'referralCode' => $user->referral_code,
            'referredByUserId' => $user->referred_by_user_id,
            'referralCount' => (int) ($user->referralsMade()->where('status', 'completed')->count()),
            'created_at' => $user->created_at,
        ];
    }
}
