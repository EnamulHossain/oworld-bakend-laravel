<?php

namespace App\Services;

use App\Models\{Category, Organization, User};

class StoreOnboardingSync
{
    public function ensure(User $store): ?Organization
    {
        if ($store->role !== 'organization') return null;
        // Preserve existing reviews and intentionally deleted organization records.
        $existing = Organization::withTrashed()->where('user_id', $store->id)->first();
        if ($existing) return $existing;
        $category = collect($store->categories ?? [])->first();
        $categoryId = is_numeric($category) ? Category::whereKey($category)->value('id') : Category::where('name', $category)->value('id');
        return Organization::firstOrCreate(['user_id' => $store->id], [
            'name' => $store->organization_name ?: $store->username,
            'business_type' => $store->business_type,
            'category_id' => $categoryId,
            'email' => $store->email, 'phone' => $store->phone,
            'status' => $store->is_verified ? 'active' : 'pending_approval',
            'verification_status' => $store->is_verified ? 'approved' : 'not_submitted',
            'is_verified' => (bool) $store->is_verified,
        ]);
    }
}
