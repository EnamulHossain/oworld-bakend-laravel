<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Offer;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        $totalEvents = Event::where('organization_id', $userId)->count();
        $publishedEvents = Event::where('organization_id', $userId)->where('status', 'published')->count();
        $upcomingEvents = Event::where('organization_id', $userId)->where('starting_date', '>', now())->count();

        return response()->json([
            'success' => true,
            'stats' => [
                'totalEvents' => $totalEvents,
                'publishedEvents' => $publishedEvents,
                'upcomingEvents' => $upcomingEvents,
            ],
        ]);
    }

    public function categories()
    {
        $categories = Category::query()
            ->where('status', 'active')
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);

        return response()->json(['success' => true, 'categories' => $categories]);
    }

    public function attributes(Request $request)
    {
        $query = Attribute::query()->with(['values' => fn ($q) => $q->orderBy('id')]);

        if ($request->query('search')) {
            $term = $request->query('search');
            $query->where('name', 'like', "%{$term}%");
        }

        $attributes = $query
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'attributes' => $attributes,
        ]);
    }

    public function listEvents(Request $request)
    {
        $userId = $request->user()->id;
        $query = Event::query()
            ->where('organization_id', $userId)
            ->with(['category:id,name', 'area:id,name'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('location', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at');

        $events = $query->paginate((int)$request->query('limit', 10));
        return response()->json($events);
    }

    public function storeEvent(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'starting_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:starting_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ]);

        $event = Event::create([
            ...$data,
            'banner' => $this->toArrayField($data['banner'] ?? []),
            'created_by' => $request->user()->id,
            'organization_id' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'event' => $event], 201);
    }

    public function updateEvent(Request $request, Event $event)
    {
        if ($event->organization_id !== $request->user()->id) {
            return response()->json(['error' => 'You are not allowed to manage this event.'], 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'starting_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:starting_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ]);

        if (array_key_exists('banner', $data)) {
            $data['banner'] = $this->toArrayField($data['banner']);
        }

        $event->update($data);
        return response()->json(['success' => true, 'event' => $event]);
    }

    public function deleteEvent(Request $request, Event $event)
    {
        if ($event->organization_id !== $request->user()->id) {
            return response()->json(['error' => 'You are not allowed to manage this event.'], 403);
        }

        $event->delete();
        return response()->json(['success' => true]);
    }

    public function uploadBanner(Request $request)
    {
        $request->validate([
            'banner' => ['required', 'file', 'mimetypes:image/*,video/*', 'max:20480'],
        ]);

        $path = $request->file('banner')->store('uploads/events', 'public');
        return response()->json([
            'success' => true,
            'bannerUrl' => '/storage/' . $path,
            'mimeType' => $request->file('banner')->getClientMimeType(),
        ], 201);
    }

    public function listOffers(Request $request)
    {
        $userId = $request->user()->id;
        $query = Offer::query()
            ->where('organization_id', $userId)
            ->with(['category:id,name', 'event:id,name', 'area:id,name'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('details', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at');

        $offers = $query->paginate((int)$request->query('limit', 10));
        return response()->json($offers);
    }

    public function storeOffer(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'address' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['nullable', Rule::in(['percentage', 'flat', 'bogo', 'custom'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
            'cover' => ['nullable', 'string', 'max:500'],
            'images' => ['nullable'],
            'videos' => ['nullable'],
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.value_ids' => ['nullable', 'array'],
            'attributes.*.value_ids.*' => ['integer', 'exists:attribute_values,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive', 'expired'])],
        ]);

        $offer = Offer::create([
            ...$data,
            'images' => $this->toArrayField($data['images'] ?? []),
            'videos' => $this->toArrayField($data['videos'] ?? []),
            'attributes' => $this->normalizeOfferAttributes($data['attributes'] ?? []),
            'created_by' => $request->user()->id,
            'organization_id' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'offer' => $offer], 201);
    }

    public function updateOffer(Request $request, Offer $offer)
    {
        if ($offer->organization_id !== $request->user()->id) {
            return response()->json(['error' => 'You are not allowed to manage this offer.'], 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'details' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'address' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['nullable', Rule::in(['percentage', 'flat', 'bogo', 'custom'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
            'cover' => ['nullable', 'string', 'max:500'],
            'images' => ['nullable'],
            'videos' => ['nullable'],
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.value_ids' => ['nullable', 'array'],
            'attributes.*.value_ids.*' => ['integer', 'exists:attribute_values,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive', 'expired'])],
        ]);

        if (array_key_exists('images', $data)) {
            $data['images'] = $this->toArrayField($data['images']);
        }
        if (array_key_exists('videos', $data)) {
            $data['videos'] = $this->toArrayField($data['videos']);
        }
        if (array_key_exists('attributes', $data)) {
            $data['attributes'] = $this->normalizeOfferAttributes($data['attributes']);
        }

        $offer->update($data + ['updated_by' => $request->user()->id]);
        return response()->json(['success' => true, 'offer' => $offer]);
    }

    public function deleteOffer(Request $request, Offer $offer)
    {
        if ($offer->organization_id !== $request->user()->id) {
            return response()->json(['error' => 'You are not allowed to manage this offer.'], 403);
        }

        $offer->delete();
        return response()->json(['success' => true]);
    }

    public function uploadOfferMedia(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/*,video/*', 'max:20480'],
        ]);

        $path = $request->file('file')->store('uploads/offers', 'public');
        return response()->json([
            'success' => true,
            'fileUrl' => '/storage/' . $path,
            'mimeType' => $request->file('file')->getClientMimeType(),
        ], 201);
    }

    private function toArrayField($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }
        if (is_string($value)) {
            return array_values(
                array_filter(
                    array_map('trim', preg_split('/\\r?\\n|,/', $value))
                )
            );
        }

        return [];
    }

    private function normalizeOfferAttributes($attributes): array
    {
        if (!is_array($attributes)) {
            return [];
        }

        $normalized = [];
        foreach ($attributes as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $attributeId = $entry['attribute_id'] ?? $entry['attributeId'] ?? null;
            if (!$attributeId) {
                continue;
            }
            $valueIds = $entry['value_ids'] ?? $entry['valueIds'] ?? [];
            $valueIds = is_array($valueIds) ? array_values(array_filter($valueIds, 'is_numeric')) : [];

            $normalized[] = [
                'attribute_id' => (int) $attributeId,
                'value_ids' => array_map('intval', $valueIds),
            ];
        }

        return $normalized;
    }
}
