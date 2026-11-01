<?php

namespace App\Services;

use App\Models\{Attribute, User, Offer, Event};

class StoreEventFilterSync
{
    public function sync(Attribute $attribute, bool $remove = false): void
    {
        $types = $attribute->types ?? [$attribute->type];
        $validValueIds = $remove ? [] : $attribute->values()->orderBy('id')->pluck('id')->all();
        foreach (['offer' => Offer::class, 'event' => Event::class] as $type => $model) {
            $this->syncPostSelections($model, $attribute, $validValueIds, $remove || !in_array($type, $types, true));
        }
        $remove = $remove || !in_array('store', $attribute->types ?? [$attribute->type], true);
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
                        $selectedIds = array_values(array_intersect(
                            array_map('intval', $filter['value_ids'] ?? []),
                            $valueIds
                        ));
                        $updated[] = ['attribute_id' => $attribute->id, 'value_ids' => $selectedIds];
                    }
                    $found = true;
                }
                if (!$remove && !$found) {
                    $updated[] = ['attribute_id' => $attribute->id, 'value_ids' => []];
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
