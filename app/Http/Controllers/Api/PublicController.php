<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Category;
use App\Models\Event;
use App\Models\Offer;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function categories()
    {
        $categories = Category::query()
            ->where('status', 'active')
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'image', 'icon', 'description']);

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    public function areas()
    {
        $areas = Area::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'areas' => $areas,
        ]);
    }

    public function categoryDetail($id)
    {
        $category = Category::query()
            ->where('status', 'active')
            ->whereKey($id)
            ->first(['id', 'name', 'short_name', 'description', 'image', 'icon', 'created_at', 'updated_at']);

        if (!$category) {
            return response()->json(['error' => 'Category not found.'], 404);
        }

        $events = Event::query()
            ->with('organization:id,organization_name')
            ->where('status', 'published')
            ->where('category_id', $category->id)
            ->orderBy('starting_date')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'description',
                'banner',
                'starting_date',
                'end_date',
                'location',
                'address',
                'organization_id',
            ]);

        $offers = Offer::query()
            ->with('organization:id,organization_name')
            ->where('status', 'active')
            ->where('category_id', $category->id)
            ->orderBy('start_date')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'details',
                'start_date',
                'end_date',
                'address',
                'discount_type',
                'discount_value',
                'cover',
                'images',
                'organization_id',
            ]);

        $formattedEvents = $events->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->name,
                'description' => $event->description,
                'date' => $event->starting_date,
                'endDate' => $event->end_date,
                'location' => $event->location,
                'address' => $event->address,
                'image' => is_array($event->banner) ? ($event->banner[0] ?? null) : $event->banner,
                'organizationName' => $event->organization?->organization_name,
                'type' => 'event',
            ];
        });

        $formattedOffers = $offers->map(function ($offer) {
            $images = is_array($offer->images) ? $offer->images : [];
            return [
                'id' => $offer->id,
                'title' => $offer->name,
                'description' => $offer->details,
                'date' => $offer->start_date,
                'endDate' => $offer->end_date,
                'discount_type' => $offer->discount_type,
                'discount_value' => $offer->discount_value,
                'image' => $offer->cover ?: ($images[0] ?? null),
                'organizationName' => $offer->organization?->organization_name,
                'type' => 'offer',
            ];
        });

        return response()->json([
            'success' => true,
            'category' => array_merge($category->toArray(), [
                'itemCount' => $formattedEvents->count() + $formattedOffers->count(),
            ]),
            'events' => $formattedEvents,
            'offers' => $formattedOffers,
        ]);
    }

    public function events(Request $request)
    {
        $limit = min((int)$request->query('limit', 20), 100);
        $categoryId = $request->query('category_id');

        $query = Event::query()
            ->with([
                'organization:id,organization_name',
                'category:id,name',
            ])
            ->where('status', 'published')
            ->orderBy('starting_date')
            ->orderByDesc('created_at');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        $events = $query->limit($limit)->get([
            'id',
            'name',
            'description',
            'banner',
            'starting_date',
            'end_date',
            'location',
            'address',
            'organization_id',
            'category_id',
        ])->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->name,
                'description' => $event->description,
                'date' => $event->starting_date,
                'endDate' => $event->end_date,
                'location' => $event->location,
                'address' => $event->address,
                'image' => is_array($event->banner) ? ($event->banner[0] ?? null) : $event->banner,
                'organizationName' => $event->organization?->organization_name,
                'category_id' => $event->category_id,
                'category' => $event->category ? [
                    'id' => $event->category->id,
                    'name' => $event->category->name,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'events' => $events,
            'filters' => [
                'category_id' => $categoryId,
            ],
        ]);
    }

    public function eventHighlights(Request $request)
    {
        $limit = min((int)$request->query('limit', 12), 50);

        $events = Event::query()
            ->with('organization:id,organization_name')
            ->where('status', 'published')
            ->orderBy('starting_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'name',
                'description',
                'banner',
                'starting_date',
                'end_date',
                'location',
                'address',
                'organization_id',
            ])->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'description' => $event->description,
                    'banner' => $event->banner ?? [],
                    'starting_date' => $event->starting_date,
                    'end_date' => $event->end_date,
                    'location' => $event->location,
                    'address' => $event->address,
                    'organizationName' => $event->organization?->organization_name,
                ];
            });

        return response()->json(['success' => true, 'events' => $events]);
    }

    public function eventDetail($id)
    {
        $event = Event::query()
            ->with(['organization:id,organization_name', 'category:id,name'])
            ->where('status', 'published')
            ->find($id);

        if (!$event) {
            return response()->json(['error' => 'Event not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'banner' => $event->banner ?? [],
                'starting_date' => $event->starting_date,
                'end_date' => $event->end_date,
                'location' => $event->location,
                'address' => $event->address,
                'organization' => $event->organization ? [
                    'organizationName' => $event->organization->organization_name,
                ] : null,
                'category' => $event->category ? [
                    'id' => $event->category->id,
                    'name' => $event->category->name,
                ] : null,
            ],
        ]);
    }

    public function offers(Request $request)
    {
        $limit = min((int)$request->query('limit', 20), 100);

        $categoryId = $request->query('category_id');

        $offers = Offer::query()
            ->with([
                'organization:id,organization_name',
                'category:id,name',
                'area:id,name',
            ])
            ->where('status', 'active')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('start_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'name',
                'details',
                'start_date',
                'end_date',
                'address',
                'facebook_url',
                'instagram_url',
                'website_url',
                'google_map_url',
                'discount_type',
                'discount_value',
                'cover',
                'images',
                'organization_id',
                'category_id',
                'area_id',
            ])->map(function ($offer) {
                $images = is_array($offer->images) ? $offer->images : [];
                return [
                    'id' => $offer->id,
                    'title' => $offer->name,
                    'description' => $offer->details,
                    'date' => $offer->start_date,
                    'endDate' => $offer->end_date,
                    'address' => $offer->address,
                    'facebook_url' => $offer->facebook_url,
                    'instagram_url' => $offer->instagram_url,
                    'website_url' => $offer->website_url,
                    'google_map_url' => $offer->google_map_url,
                    'discount_type' => $offer->discount_type,
                    'discount_value' => $offer->discount_value,
                    'image' => $offer->cover ?: ($images[0] ?? null),
                    'organizationName' => $offer->organization?->organization_name,
                    'category_id' => $offer->category_id,
                    'area_id' => $offer->area_id,
                    'category' => $offer->category ? [
                        'id' => $offer->category->id,
                        'name' => $offer->category->name,
                    ] : null,
                    'area' => $offer->area ? [
                        'id' => $offer->area->id,
                        'name' => $offer->area->name,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'offers' => $offers,
            'filters' => [
                'category_id' => $categoryId,
            ],
        ]);
    }

    public function offerHighlights(Request $request)
    {
        $limit = min((int)$request->query('limit', 12), 50);

        $offers = Offer::query()
            ->with('organization:id,organization_name')
            ->where('status', 'active')
            ->orderBy('start_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'name',
                'details',
                'start_date',
                'end_date',
                'discount_type',
                'discount_value',
                'cover',
                'images',
                'videos',
                'thumbnail',
                'organization_id',
            ])->map(function ($offer) {
                return [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'details' => $offer->details,
                    'start_date' => $offer->start_date,
                    'end_date' => $offer->end_date,
                    'discount_type' => $offer->discount_type,
                    'discount_value' => $offer->discount_value,
                    'cover' => $offer->cover,
                    'images' => $offer->images ?? [],
                    'videos' => $offer->videos ?? [],
                    'thumbnail' => $offer->thumbnail,
                    'organizationName' => $offer->organization?->organization_name,
                ];
            });

        return response()->json(['success' => true, 'highlights' => $offers]);
    }

    public function offerDetail($id)
    {
        $offer = Offer::query()
            ->with(['organization:id,organization_name', 'category:id,name', 'area:id,name'])
            ->where('status', 'active')
            ->find($id);

        if (!$offer) {
            return response()->json(['error' => 'Offer not found.'], 404);
        }

        $images = is_array($offer->images) ? $offer->images : [];
        $videos = is_array($offer->videos) ? $offer->videos : [];

        return response()->json([
            'success' => true,
            'offer' => [
                'id' => $offer->id,
                'name' => $offer->name,
                'details' => $offer->details,
                'start_date' => $offer->start_date,
                'end_date' => $offer->end_date,
                'address' => $offer->address,
                'facebook_url' => $offer->facebook_url,
                'instagram_url' => $offer->instagram_url,
                'website_url' => $offer->website_url,
                'google_map_url' => $offer->google_map_url,
                'discount_type' => $offer->discount_type,
                'discount_value' => $offer->discount_value,
                'cover' => $offer->cover,
                'images' => $images,
                'videos' => $videos,
                'thumbnail' => $offer->thumbnail,
                'organization' => $offer->organization ? [
                    'organizationName' => $offer->organization->organization_name,
                ] : null,
                'category' => $offer->category ? [
                    'id' => $offer->category->id,
                    'name' => $offer->category->name,
                ] : null,
                'area' => $offer->area ? [
                    'id' => $offer->area->id,
                    'name' => $offer->area->name,
                ] : null,
            ],
        ]);
    }

    public function search(Request $request)
    {
        $q = trim((string)$request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }

        $limit = min((int)$request->query('limit', 5), 15);

        $categories = Category::query()
            ->where('status', 'active')
            ->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('short_name', 'like', "%{$q}%");
            })
            ->limit($limit)
            ->get(['id', 'name', 'short_name', 'description', 'icon'])
            ->map(fn ($cat) => ['type' => 'category', ...$cat->toArray()]);

        $events = Event::query()
            ->where('status', 'published')
            ->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%");
            })
            ->orderBy('starting_date')
            ->limit($limit)
            ->get(['id', 'name', 'description', 'starting_date', 'location', 'address'])
            ->map(fn ($event) => [
                'type' => 'event',
                'id' => $event->id,
                'title' => $event->name,
                'description' => $event->description,
                'location' => $event->location,
                'address' => $event->address,
                'date' => $event->starting_date,
            ]);

        $offers = Offer::query()
            ->where('status', 'active')
            ->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('details', 'like', "%{$q}%");
            })
            ->orderBy('start_date')
            ->limit($limit)
            ->get(['id', 'name', 'details', 'start_date'])
            ->map(fn ($offer) => [
                'type' => 'offer',
                'id' => $offer->id,
                'title' => $offer->name,
                'description' => $offer->details,
                'date' => $offer->start_date,
            ]);

        return response()->json([
            'success' => true,
            'results' => $categories->concat($events)->concat($offers)->values(),
        ]);
    }

    public function setting(string $key)
    {
        $setting = SystemSetting::where('key', $key)->first();
        if (!$setting || !$setting->is_active) {
            return response()->json(['error' => 'Setting not found'], 404);
        }

        return response()->json([
            'success' => true,
            'setting' => $setting,
        ]);
    }
}
