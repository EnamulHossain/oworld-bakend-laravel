<?php

namespace App\Services;

use App\Models\{Area, Attribute, Category, Event, Offer, User};
use Illuminate\Support\Str;

/** Relationship-aware search shared by the public search entry point. */
class UniversalSearch
{
    public static function words(string $text): array
    {
        $text = mb_strtolower(strip_tags($text));
        $text = preg_replace_callback('/\p{Latin}+/u', fn ($match) => Str::ascii($match[0]), $text);
        return preg_split('/[^\p{L}\p{M}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function wordMatches(string $query, string $word, bool $typos = false): bool
    {
        if ($query === $word || str_starts_with($word, $query)) return true;
        if (!$typos || !preg_match('/^[a-z]{5,}$/', $query) || !preg_match('/^[a-z]+$/', $word)) return false;
        return levenshtein($query, $word) <= (strlen($query) >= 8 ? 2 : 1);
    }

    private function ids($record, string $single, string $multiple): array
    {
        return array_values(array_unique(array_filter(array_map('strval', array_merge(
            (array) ($record->$multiple ?? []), [$record->$single ?? null]
        )), fn ($id) => $id !== '')));
    }

    public function search(string $query, int $limit = 5): array
    {
        $terms = array_values(array_filter(self::words($query), fn ($word) => !in_array($word, ['in', 'at', 'near', 'the', 'a', 'an', 'and', 'of', 'for'], true)));
        if (!$terms) return [];

        $areas = Area::query()->get(['id', 'name'])->keyBy('id');
        $categories = Category::query()->where('status', 'active')->get(['id', 'name', 'short_name', 'parent_id'])->keyBy('id');
        $attributes = Attribute::query()->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', now('Asia/Dhaka')->toDateString()))
            ->where(fn ($q) => $q->where('auto_expires', false)->orWhereNull('auto_expires')->orWhereNull('end_date')->orWhereDate('end_date', '>=', now('Asia/Dhaka')->toDateString()))
            ->with('values')->get()->keyBy('id');

        // Resolve location phrases first: every word of a multiword area must occur together.
        $areaConstraints = [];
        $consumed = [];
        foreach ($areas as $area) {
            $words = self::words($area->name);
            foreach ($terms as $offset => $term) {
                $slice = array_slice($terms, $offset, count($words));
                if (!$words || count($slice) !== count($words)) continue;
                $matches = true;
                foreach ($words as $index => $word) {
                    if (!self::wordMatches($slice[$index], $word, true)) $matches = false;
                }
                if ($matches) {
                    $areaConstraints[(string) $area->id] = true;
                    foreach (array_keys($slice) as $index) $consumed[$offset + $index] = true;
                }
            }
        }
        $terms = array_values(array_filter($terms, fn ($term, $index) => !isset($consumed[$index]), ARRAY_FILTER_USE_BOTH));
        // Listing types are alternatives, not words every individual record must contain.
        $typeAliases = [
            'offer' => 'offer', 'offers' => 'offer',
            'event' => 'event', 'events' => 'event',
            'store' => 'organization', 'stores' => 'organization',
            'business' => 'organization', 'businesses' => 'organization',
            'directory' => 'organization', 'directories' => 'organization',
        ];
        $requestedTypes = [];
        $terms = array_values(array_filter($terms, function ($term) use ($typeAliases, &$requestedTypes) {
            if (!isset($typeAliases[$term])) return true;
            $requestedTypes[$typeAliases[$term]] = true;
            return false;
        }));
        $vocabulary = self::words($categories->map(fn ($category) => $category->name.' '.$category->short_name)->implode(' ').' '.
            $attributes->map(fn ($attribute) => $attribute->name.' '.$attribute->values->pluck('value')->implode(' '))->implode(' '));
        $terms = array_values(array_unique($terms));
        $structuredTerms = [];
        foreach ($terms as $term) {
            foreach ($vocabulary as $word) {
                if (self::wordMatches($term, $word, true)) {
                    $structuredTerms[$term] = true;
                    break;
                }
            }
        }

        $stores = User::query()->where('role', 'organization')->where('status', 'active')
            ->where(fn ($q) => $q->whereHas('organization', fn ($org) => $org->where('status', 'active')->where('verification_status', 'approved'))
                ->orWhere(fn ($legacy) => $legacy->whereDoesntHave('organization')->where('is_verified', true)))
            ->get(['id', 'parent_org_id', 'branch_mode', 'organization_name', 'username', 'categories', 'subcategory_id', 'subcategory_ids', 'area_id', 'store_filters', 'about', 'business_type', 'store_tags', 'avatar', 'profile_banner', 'address'])->keyBy('id');
        $results = [];
        $listingBusinesses = [];

        $add = function ($record, string $type, array $categoryIds, array $areaIds, array $selections, string $title, string $body, string $related = '', ?int $brandId = null, bool $relatedBusiness = false) use (&$results, $terms, $structuredTerms, $areaConstraints, $categories, $attributes, $areas, $stores, $requestedTypes) {
            if (!$relatedBusiness && $requestedTypes && !isset($requestedTypes[$type])) return;
            if ($areaConstraints && !array_intersect(array_keys($areaConstraints), $areaIds)) return;
            $labels = [];
            foreach ($categoryIds as $id) {
                // Older profiles can contain category names rather than numeric IDs.
                $category = $categories->get($id) ?? $categories->first(fn ($item) => mb_strtolower($item->name) === mb_strtolower($id));
                if (!$category) continue;
                $labels[] = $category->name.' '.$category->short_name;
                if ($category->parent_id && $categories->has($category->parent_id)) $labels[] = $categories[$category->parent_id]->name;
            }
            foreach ($selections as $selection) {
                $attribute = $attributes->get($selection['attribute_id'] ?? null);
                if (!$attribute || !in_array($type === 'organization' ? 'store' : $type, $attribute->types ?? [$attribute->type], true)) continue;
                $selected = array_map('strval', (array) ($selection['value_ids'] ?? []));
                $values = $attribute->values->filter(fn ($value) => in_array((string) $value->id, $selected, true));
                if ($values->isNotEmpty()) $labels[] = $attribute->name.' '.$values->pluck('value')->implode(' ');
            }
            $titleWords = self::words($title);
            $structuredWords = self::words(implode(' ', $labels));
            $otherWords = self::words($related.' '.$body);
            $typeWords = $type === 'organization' ? ['business', 'businesses', 'store', 'stores'] : [$type, $type.'s'];
            $score = 0;
            $matchedTerms = 0;
            foreach ($relatedBusiness ? [] : $terms as $term) {
                $weight = 0;
                foreach ([$titleWords, $structuredWords, $typeWords, $otherWords] as $index => $words) {
                    // Known filters/categories require assignments, not incidental description claims.
                    if ($index === 3 && isset($structuredTerms[$term])) continue;
                    foreach ($words as $word) {
                        if (self::wordMatches($term, $word, $index < 3)) {
                            $weight = max($weight, [100, 60, 50, 20][$index] + ($term === $word ? 10 : 0));
                        }
                    }
                }
                if ($weight) $matchedTerms++;
                $score += $weight;
            }
            if (!$relatedBusiness && $terms && !$matchedTerms) return;
            $location = collect($areaIds)->map(fn ($id) => $areas->get($id)?->name)->filter()->implode(', ');
            $results[] = [
                'type' => $type, 'id' => $record->id, 'title' => $title,
                'description' => Str::limit(strip_tags($body), 160), 'subtitle' => $location,
                'image' => $type === 'organization' ? ($record->avatar ?: $record->profile_banner) : $record->thumbnail,
                'categories' => collect($categoryIds)->map(fn ($id) => $categories->get($id)?->name)->filter()->unique()->values()->all(),
                'tags' => $type === 'organization' ? ($record->store_tags ?? []) : [],
                'verified' => $type === 'organization',
                'store_name' => $type === 'organization' ? '' : (($stores->get($record->organization_id)?->organization_name ?: $stores->get($record->organization_id)?->username) ?? ''),
                'start_date' => $type === 'event' ? $record->starting_date : ($type === 'offer' ? $record->start_date : null),
                'is_recurring' => $type === 'offer' ? $record->is_recurring : false,
                'recurring_days' => $type === 'offer' ? $record->recurring_days : [],
                'recurring_start_date' => $type === 'offer' ? $record->recurring_start_date : null,
                'recurring_end_date' => $type === 'offer' ? $record->recurring_end_date : null,
                'end_date' => $type !== 'organization' ? $record->end_date : null,
                'discount_type' => $type === 'offer' ? $record->discount_type : null,
                'discount_value' => $type === 'offer' ? $record->discount_value : null,
                '_score' => $score, '_brand' => $brandId, '_matches' => $matchedTerms,
            ];
        };

        foreach ($stores as $store) {
            $add($store, 'organization', array_merge(array_map('strval', $store->categories ?? []), $this->ids($store, 'subcategory_id', 'subcategory_ids')),
                array_filter(array_merge([(string) $store->area_id], $store->branch_mode === 'unified' ? $store->locations()->pluck('area_id')->map(fn ($id) => (string) $id)->all() : [])), $store->store_filters ?? [], $store->organization_name ?: $store->username,
                $store->about.' '.$store->business_type.' '.implode(' ', $store->store_tags ?? []), '', $store->branch_mode === 'independent' ? null : (int) $store->id);
        }
        foreach (['offer' => Offer::class, 'event' => Event::class] as $type => $class) {
            $records = $class::query()->whereIn('status', ['published', 'active'])
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now('Asia/Dhaka')->toDateString()))
                ->when($type === 'offer', fn ($q) => $q->where('branch_assignment_status', 'approved'))->get();
            foreach ($records as $record) {
                if ($record->end_date && \Illuminate\Support\Carbon::parse($record->end_date->format('Y-m-d').' '.($record->end_time ?: '23:59:59'), 'Asia/Dhaka')->isPast()) continue;
                // A listing belonging to a hidden/suspended business cannot leak through search.
                if ($record->organization_id && !$stores->has($record->organization_id)) continue;
                $store = $stores->get($record->organization_id);
                $branchIds = $type === 'offer' ? (array) $record->branch_ids : [];
                $areaIds = $this->ids($record, 'area_id', 'area_ids');
                foreach ($branchIds as $id) if ($stores->has($id) && $stores[$id]->area_id) $areaIds[] = (string) $stores[$id]->area_id;
                if ($type === 'offer' && $store?->branch_mode === 'unified') {
                    $locationAreas = $store->locations()->when($record->location_ids, fn ($q) => $q->whereIn('id', $record->location_ids))->pluck('area_id')->filter()->map(fn ($id) => (string) $id)->all();
                    if ($locationAreas) $areaIds = $locationAreas;
                }
                if (!$areaIds && $store?->area_id) $areaIds[] = (string) $store->area_id;
                $before = count($results);
                $add($record, $type, array_merge($this->ids($record, 'category_id', 'category_ids'), $this->ids($record, 'subcategory_id', 'subcategory_ids')),
                    $areaIds, $record->attributes ?? [], $record->name,
                    $type === 'offer' ? (string) $record->details : (string) $record->description,
                    $store ? $store->organization_name.' '.$store->username : '');
                if ($store && count($results) > $before) {
                    foreach (array_unique(array_merge([$store->id], $branchIds)) as $businessId) {
                        if ($stores->has($businessId)) $listingBusinesses[$type.':'.$record->id][$businessId] = true;
                    }
                }
            }
        }
        // Prefer the intersection of word matches; otherwise use their union.
        // Locations and requested listing types remain constraints on both sets.
        $common = array_filter($results, fn ($result) => $result['_matches'] === count($terms));
        if ($common) $results = array_values($common);
        $relatedBusinesses = [];
        foreach ($results as $result) {
            foreach ($listingBusinesses[$result['type'].':'.$result['id']] ?? [] as $id => $_matched) {
                $relatedBusinesses[$id] = true;
            }
        }
        // Matching offers and events also surface their public business, once per business.
        foreach ($relatedBusinesses as $id => $_matched) {
            if (collect($results)->contains(fn ($result) => $result['type'] === 'organization' && $result['id'] === $id)) continue;
            $store = $stores[$id];
            // A listing venue is not proof that its owner has a store in that area.
            $areaIds = array_filter(array_merge([(string) $store->area_id], $store->branch_mode === 'unified'
                ? $store->locations()->pluck('area_id')->map(fn ($areaId) => (string) $areaId)->all() : []));
            $add($store, 'organization', array_merge(array_map('strval', $store->categories ?? []), $this->ids($store, 'subcategory_id', 'subcategory_ids')),
                $areaIds, $store->store_filters ?? [], $store->organization_name ?: $store->username,
                (string) $store->about, '', $store->branch_mode === 'independent' ? null : (int) $store->id, true);
        }
        usort($results, fn ($a, $b) => ($b['_score'] <=> $a['_score']) ?: strnatcasecmp($a['title'], $b['title']) ?: ($a['id'] <=> $b['id']));
        $brands = [];
        $counts = [];
        $output = [];
        $seen = [];
        foreach ($results as $result) {
            $key = $result['type'].':'.$result['id'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            if ($result['_brand'] && isset($brands[$result['_brand']])) {
                $output[$brands[$result['_brand']]]['other_matching_branches']++;
                continue;
            }
            if (($counts[$result['type']] ?? 0) >= $limit) continue;
            $counts[$result['type']] = ($counts[$result['type']] ?? 0) + 1;
            if ($result['_brand']) {
                $brands[$result['_brand']] = count($output);
                $result['other_matching_branches'] = 0;
            }
            unset($result['_score'], $result['_brand'], $result['_matches']);
            $output[] = $result;
        }
        return $output;
    }
}
