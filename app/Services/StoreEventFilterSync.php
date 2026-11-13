<?php

namespace App\Services;

use App\Models\{Attribute, Category, User, Offer, Event};

class StoreEventFilterSync
{
    public function initializeStore(User $store): void
    {
        if ($store->role !== 'organization' || empty($store->categories)) return;

        $filters = $store->store_filters ?? [];
        foreach (Attribute::forType('store')->with('values')->get() as $attribute) {
            $categoryIds = array_map('intval', $attribute->category_ids ?: array_filter([$attribute->category_id]));
            $subcategoryIds = array_map('intval', $attribute->subcategory_ids ?: array_filter([$attribute->subcategory_id]));
            $categoryNames = $categoryIds ? Category::whereIn('id', $categoryIds)->pluck('name')->all() : [];
            if (!$this->matchesStore($store, $categoryIds, $subcategoryIds, $categoryNames)) continue;

            // Creation defaults select every value, including filters submitted empty.
            $filters = array_values(array_filter($filters, fn ($filter) =>
                (string) ($filter['attribute_id'] ?? '') !== (string) $attribute->id));
            $filters[] = ['attribute_id' => $attribute->id,
                'value_ids' => $attribute->values->sortBy('id')->pluck('id')->values()->all()];
        }
        $store->store_filters = $filters;
    }

    private function matchesStore(User $store, array $categoryIds, array $subcategoryIds, array $categoryNames): bool
    {
        $matchesCategory = !$categoryIds || collect($store->categories ?? [])->contains(function ($category) use ($categoryIds, $categoryNames) {
            $id = is_array($category) ? ($category['id'] ?? null) : $category;
            $name = is_array($category) ? ($category['name'] ?? null) : $category;
            return in_array((int) $id, $categoryIds, true) || in_array($name, $categoryNames, true);
        });
        $matchesSubcategory = !$subcategoryIds || array_intersect($subcategoryIds,
            array_map('intval', $store->subcategory_ids ?: array_filter([$store->subcategory_id])));
        return $matchesCategory && $matchesSubcategory;
    }

    public function sync(Attribute $attribute, bool $remove = false): void
    {
        $types = $attribute->types ?? [$attribute->type];
        $validValueIds = $remove ? [] : $attribute->values()->orderBy('id')->pluck('id')->all();
        foreach (['offer' => Offer::class, 'event' => Event::class] as $type => $model) {
            $this->syncPostSelections($model, $attribute, $validValueIds, $remove || !in_array($type, $types, true));
        }
        $remove = $remove || !in_array('store', $attribute->types ?? [$attribute->type], true);
        $valueIds = $remove ? [] : $attribute->values()->orderBy('id')->pluck('id')->all();
        $categoryIds = array_map('intval', $attribute->category_ids ?: array_filter([$attribute->category_id]));
        $subcategoryIds = array_map('intval', $attribute->subcategory_ids ?: array_filter([$attribute->subcategory_id]));
        $categoryNames = $categoryIds ? Category::whereIn('id', $categoryIds)->pluck('name')->all() : [];

        User::query()->where('role', 'organization')->lockForUpdate()->chunkById(100, function ($stores) use ($attribute, $remove, $valueIds, $categoryIds, $subcategoryIds, $categoryNames) {
            foreach ($stores as $store) {
                $removeFromStore = $remove || !$this->matchesStore($store, $categoryIds, $subcategoryIds, $categoryNames);
                $filters = $store->store_filters ?? [];
                $updated = [];
                $found = false;
                foreach ($filters as $filter) {
                    if ((string) ($filter['attribute_id'] ?? '') !== (string) $attribute->id) {
                        $updated[] = $filter;
                        continue;
                    }
                    if (!$removeFromStore && !$found) {
                        $selectedIds = array_values(array_intersect(
                            array_map('intval', $filter['value_ids'] ?? []),
                            $valueIds
                        ));
                        $updated[] = ['attribute_id' => $attribute->id, 'value_ids' => empty($filter['value_ids']) ? $valueIds : $selectedIds];
                    }
                    $found = true;
                }
                if (!$removeFromStore && !$found) {
                    $updated[] = ['attribute_id' => $attribute->id, 'value_ids' => $valueIds];
                }
                if ($updated !== $filters) {
                    $store->update(['store_filters' => $updated]);
                }
            }
        });
    }

    private function syncPostSelections(string $model, Attribute $attribute, array $validValueIds, bool $remove): void
    {
        // Include every status and deleted records so restoration cannot revive invalid filters.
        $model::withTrashed()->whereNotNull('attributes')->lockForUpdate()->chunkById(100, function ($posts) use ($attribute, $validValueIds, $remove) {
            foreach ($posts as $post) {
                $filters = $post->getAttribute('attributes') ?? [];
                $updated = [];
                foreach ($filters as $filter) {
                    if ((string) ($filter['attribute_id'] ?? '') !== (string) $attribute->id) {
                        $updated[] = $filter;
                        continue;
                    }
                    if ($remove) continue;
                    $filter['value_ids'] = array_values(array_intersect(
                        array_map('intval', $filter['value_ids'] ?? []),
                        $validValueIds
                    ));
                    if ($filter['value_ids']) $updated[] = $filter;
                }
                if ($updated !== $filters) $post->update(['attributes' => $updated]);
            }
        });
    }
}
