<?php

namespace App\Services;

use App\Models\{Attribute, User};

class StoreEventFilterSync
{
    public function sync(Attribute $attribute, bool $remove = false): void
    {
        $valueIds = $remove ? [] : $attribute->values()->orderBy('id')->pluck('id')->all();

        User::query()->where('role', 'organization')->lockForUpdate()->chunkById(100, function ($stores) use ($attribute, $remove, $valueIds) {
            foreach ($stores as $store) {
                $filters = $store->store_filters ?? [];
                $updated = [];
                $found = false;
                foreach ($filters as $filter) {
                    if ((string) ($filter['attribute_id'] ?? '') !== (string) $attribute->id) {
                        $updated[] = $filter;
                        continue;
                    }
                    if (!$remove && !$found) {
                        $updated[] = ['attribute_id' => $attribute->id, 'value_ids' => $valueIds];
                    }
                    $found = true;
                }
                if (!$remove && !$found) {
                    $updated[] = ['attribute_id' => $attribute->id, 'value_ids' => $valueIds];
                }
                if ($updated !== $filters) {
                    $store->update(['store_filters' => $updated]);
                }
            }
        });
    }
}
