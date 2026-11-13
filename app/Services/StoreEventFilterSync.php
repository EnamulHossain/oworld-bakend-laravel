<?php

namespace App\Services;

use App\Models\{Attribute, Category, User, Offer, Event};
use Illuminate\Support\Facades\DB;

class StoreEventFilterSync
{
    public function initializeStore(User $store): void
    {
        if ($store->role !== 'organization' || empty($store->categories)) return;

        $store->store_filters = $this->applyStoreDefaults($store, $this->storeDefaults());
    }

    private function storeDefaults(): array
    {
        $names = Category::pluck('name', 'id')->all();
        return Attribute::query()->with('values')->orderBy('id')->get()->map(function ($attribute) use ($names) {
            $categoryIds = array_map('intval', $attribute->category_ids ?: array_filter([$attribute->category_id]));
            $subcategoryIds = array_map('intval', $attribute->subcategory_ids ?: array_filter([$attribute->subcategory_id]));
            return ['id' => $attribute->id, 'categoryIds' => $categoryIds, 'subcategoryIds' => $subcategoryIds,
                'categoryNames' => array_values(array_intersect_key($names, array_flip($categoryIds))),
                'valueIds' => $attribute->values->sortBy('id')->pluck('id')->values()->all()];
        })->all();
    }

    private function applyStoreDefaults(User $store, array $defaults, bool $preserveChoices = false): array
    {
        $filters = [];
        foreach ($store->store_filters ?? [] as $index => $filter) {
            $filters[(string) ($filter['attribute_id'] ?? "unknown:{$index}")] = $filter;
        }
        foreach ($defaults as $definition) {
            if (!$this->matchesStore($store, $definition['categoryIds'], $definition['subcategoryIds'], $definition['categoryNames'])) continue;
            $key = (string) $definition['id'];
            $existing = $filters[$key] ?? null;
            if ($preserveChoices && !empty($existing['value_ids'])) continue;
            $filters[$key] = ['attribute_id' => $definition['id'], 'value_ids' => $definition['valueIds']];
        }
        return array_values($filters);
    }

    public function repairStores(): int
    {
        $defaults = $this->storeDefaults();
        $updated = 0;
        User::where('role', 'organization')->chunkById(100, function ($stores) use ($defaults, &$updated) {
            foreach ($stores as $store) {
                DB::transaction(function () use ($store, $defaults, &$updated) {
                    $locked = User::whereKey($store->id)->lockForUpdate()->first();
                    $filters = $this->applyStoreDefaults($locked, $defaults, true);
                    if ($filters !== ($locked->store_filters ?? [])) {
                        $locked->update(['store_filters' => $filters]);
                        $updated++;
                    }
                });
            }
        });
        return $updated;
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

    public function sync(Attribute $attribute, bool $remove = false, ?array $previousValueIds = null): void
    {
        $types = $attribute->types ?? [$attribute->type];
        $validValueIds = $remove ? [] : $attribute->values()->orderBy('id')->pluck('id')->all();
        foreach (['offer' => Offer::class, 'event' => Event::class] as $type => $model) {
            $this->syncPostSelections($model, $attribute, $validValueIds, $remove || !in_array($type, $types, true));
        }
        $addedValueIds = $previousValueIds === null ? [] : array_values(array_diff($validValueIds, $previousValueIds));
        $this->syncStores($attribute, $remove, $addedValueIds);
    }

    public function syncStores(Attribute $attribute, bool $remove = false, array $addedValueIds = []): void
    {
        // Store profiles hold the choices available to their offers and events too.
        $valueIds = $remove ? [] : $attribute->values()->orderBy('id')->pluck('id')->all();
        $categoryIds = array_map('intval', $attribute->category_ids ?: array_filter([$attribute->category_id]));
        $subcategoryIds = array_map('intval', $attribute->subcategory_ids ?: array_filter([$attribute->subcategory_id]));
        $categoryNames = $categoryIds ? Category::whereIn('id', $categoryIds)->pluck('name')->all() : [];

        User::query()->where('role', 'organization')->lockForUpdate()->chunkById(100, function ($stores) use ($attribute, $remove, $valueIds, $addedValueIds, $categoryIds, $subcategoryIds, $categoryNames) {
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
                            $valueIds,
                            array_merge(array_map('intval', $filter['value_ids'] ?? []), $addedValueIds)
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
