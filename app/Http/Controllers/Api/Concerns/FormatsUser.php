<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;

trait FormatsUser
{
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'fullName' => $user->full_name,
            'about' => $user->about,
            'email' => $user->email,
            'role' => $user->role,
            'organizationName' => $user->organization_name,
            'business_type' => $user->business_type,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'created_at' => $user->created_at,
        ];
    }
}
