<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Category;
use App\Models\ContentBlock;
use App\Models\Event;
use App\Models\HighlightReel;
use App\Models\HighlightReelReaction;
use App\Models\HighlightReelShare;
use App\Models\Offer;
use App\Models\Attribute;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->orderBy('sort_order')
            ->orderBy('starting_date')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'description',
                'banner',
                'thumbnail',
                'starting_date',
                'end_date',
                'location',
                'address',
                'facebook_url',
                'instagram_url',
                'website_url',
                'google_map_url',
                'organization_id',
                'sort_order',
            ]);

        $offers = Offer::query()
            ->with('organization:id,organization_name')
            ->where('status', 'active')
            ->where('category_id', $category->id)
            ->orderBy('sort_order')
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
                'thumbnail',
                'cover',
                'images',
                'organization_id',
                'sort_order',
                'offer_type',
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
                'facebook_url' => $event->facebook_url,
                'instagram_url' => $event->instagram_url,
                'website_url' => $event->website_url,
                'google_map_url' => $event->google_map_url,
                'thumbnail' => $event->thumbnail,
                'image' => $event->thumbnail ?: (is_array($event->banner) ? ($event->banner[0] ?? null) : $event->banner),
                'organizationName' => $event->organization?->organization_name,
                'sort_order' => $event->sort_order ?? 0,
                'serial' => $event->sort_order ?? 0,
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
                'thumbnail' => $offer->thumbnail,
                'cover' => $offer->cover,
                'image' => $offer->thumbnail ?: ($offer->cover ?: ($images[0] ?? null)),
                'organizationName' => $offer->organization?->organization_name,
                'sort_order' => $offer->sort_order ?? 0,
                'serial' => $offer->sort_order ?? 0,
                'offer_type' => $offer->offer_type ?? 'regular',
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
        $offset = max((int)$request->query('offset', 0), 0);
        $categoryId = $request->query('category_id');

        $query = Event::query()
            ->with([
                'organization:id,organization_name',
                'category:id,name',
            ])
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('starting_date')
            ->orderByDesc('created_at');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        $total = (clone $query)->count();
        $events = $query->skip($offset)->limit($limit)->get([
            'id',
            'name',
            'description',
            'banner',
            'thumbnail',
            'attributes',
            'starting_date',
            'end_date',
            'location',
            'address',
            'facebook_url',
            'instagram_url',
            'website_url',
            'google_map_url',
            'organization_id',
            'category_id',
            'sort_order',
        ])->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->name,
                'description' => $event->description,
                'date' => $event->starting_date,
                'endDate' => $event->end_date,
                'location' => $event->location,
                'address' => $event->address,
                'facebook_url' => $event->facebook_url,
                'instagram_url' => $event->instagram_url,
                'website_url' => $event->website_url,
                'google_map_url' => $event->google_map_url,
                'thumbnail' => $event->thumbnail,
                'image' => $event->thumbnail ?: (is_array($event->banner) ? ($event->banner[0] ?? null) : $event->banner),
                'organizationName' => $event->organization?->organization_name,
                'category_id' => $event->category_id,
                'attributes' => $event->attributes ?? [],
                'sort_order' => $event->sort_order ?? 0,
                'serial' => $event->sort_order ?? 0,
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
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'count' => $events->count(),
                'has_more' => ($offset + $events->count()) < $total,
            ],
        ]);
    }

    public function eventHighlights(Request $request)
    {
        $limit = min((int)$request->query('limit', 12), 50);

        $events = Event::query()
            ->with('organization:id,organization_name')
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('starting_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
            'id',
            'name',
            'description',
            'banner',
            'thumbnail',
            'attributes',
            'starting_date',
            'end_date',
            'location',
            'address',
            'facebook_url',
            'instagram_url',
            'website_url',
            'google_map_url',
            'organization_id',
            'sort_order',
        ])->map(function ($event) {
            return [
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'banner' => $event->banner ?? [],
                'thumbnail' => $event->thumbnail,
                'image' => $event->thumbnail ?: (is_array($event->banner) ? ($event->banner[0] ?? null) : $event->banner),
                'attributes' => $event->attributes ?? [],
                'starting_date' => $event->starting_date,
                'end_date' => $event->end_date,
                'location' => $event->location,
                'address' => $event->address,
                'facebook_url' => $event->facebook_url,
                'instagram_url' => $event->instagram_url,
                'website_url' => $event->website_url,
                'google_map_url' => $event->google_map_url,
                'organizationName' => $event->organization?->organization_name,
                'sort_order' => $event->sort_order ?? 0,
                'serial' => $event->sort_order ?? 0,
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
                'thumbnail' => $event->thumbnail,
                'attributes' => $event->attributes ?? [],
                'starting_date' => $event->starting_date,
                'end_date' => $event->end_date,
                'location' => $event->location,
                'address' => $event->address,
                'facebook_url' => $event->facebook_url,
                'instagram_url' => $event->instagram_url,
                'website_url' => $event->website_url,
                'google_map_url' => $event->google_map_url,
                'sort_order' => $event->sort_order ?? 0,
                'serial' => $event->sort_order ?? 0,
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
        $offset = max((int)$request->query('offset', 0), 0);

        $categoryId = $request->query('category_id');

        $query = Offer::query()
            ->with([
                'organization:id,organization_name',
                'category:id,name',
                'area:id,name',
            ])
            ->where('status', 'active')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('sort_order')
            ->orderBy('start_date')
            ->orderByDesc('created_at');

        $total = (clone $query)->count();

        $offers = $query
            ->skip($offset)
            ->limit($limit)
            ->get([
                'id',
                'name',
                'details',
                'start_date',
                'end_date',
                'address',
                'phone_number',
                'facebook_url',
                'instagram_url',
                'website_url',
                'google_map_url',
                'discount_type',
                'discount_value',
                'thumbnail',
                'cover',
                'images',
                'attributes',
                'organization_id',
                'category_id',
                'area_id',
                'sort_order',
                'offer_type',
            ])->map(function ($offer) {
                $images = is_array($offer->images) ? $offer->images : [];
                return [
                    'id' => $offer->id,
                    'title' => $offer->name,
                    'description' => $offer->details,
                    'date' => $offer->start_date,
                    'endDate' => $offer->end_date,
                    'address' => $offer->address,
                    'phone_number' => $offer->phone_number,
                    'facebook_url' => $offer->facebook_url,
                    'instagram_url' => $offer->instagram_url,
                    'website_url' => $offer->website_url,
                    'google_map_url' => $offer->google_map_url,
                    'discount_type' => $offer->discount_type,
                    'discount_value' => $offer->discount_value,
                    'thumbnail' => $offer->thumbnail,
                    'cover' => $offer->cover,
                    'image' => $offer->thumbnail ?: ($offer->cover ?: ($images[0] ?? null)),
                    'organizationName' => $offer->organization?->organization_name,
                    'category_id' => $offer->category_id,
                    'area_id' => $offer->area_id,
                    'attributes' => $offer->attributes ?? [],
                    'sort_order' => $offer->sort_order ?? 0,
                    'serial' => $offer->sort_order ?? 0,
                    'offer_type' => $offer->offer_type ?? 'regular',
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
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'count' => $offers->count(),
                'has_more' => ($offset + $offers->count()) < $total,
            ],
        ]);
    }

    public function contentBlocks()
    {
        $blocks = ContentBlock::query()
            ->with(['items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $formatted = $blocks->map(function (ContentBlock $block) {
            return [
                'id' => $block->id,
                'name' => $block->name,
                'description' => $block->description,
                'is_featured' => (bool) $block->is_featured,
                'teared_block' => (bool) $block->teared_block,
                'thumbnail_image' => $block->thumbnail_image,
                'featured_sort_order' => $block->featured_sort_order,
                'items' => $block->items->map(fn ($item) => [
                    'id' => $item->id,
                    'type' => $item->type,
                    'targetId' => $item->target_id,
                    'target_id' => $item->target_id,
                    'title' => $item->title,
                    'subtitle' => $item->subtitle,
                    'image' => $item->image,
                    'external_link' => $item->external_link,
                    'sort_order' => $item->sort_order,
                ])->values(),
            ];
        });

        return response()->json([
            'success' => true,
            'blocks' => $formatted,
        ]);
    }

    public function offerHighlights(Request $request)
    {
        $limit = min((int)$request->query('limit', 12), 50);

        $offers = Offer::query()
            ->with('organization:id,organization_name')
            ->where('status', 'active')
            ->orderBy('sort_order')
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
                'sort_order',
                'offer_type',
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
                    'sort_order' => $offer->sort_order ?? 0,
                    'serial' => $offer->sort_order ?? 0,
                    'offer_type' => $offer->offer_type ?? 'regular',
                ];
            });

        return response()->json(['success' => true, 'highlights' => $offers]);
    }

    public function highlights(Request $request)
    {
        $limit = min((int)$request->query('limit', 12), 50);

        $highlights = HighlightReel::query()
            ->where('is_active', true)
            ->with(['items' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $highlightIds = $highlights->pluck('id')->all();
        $reactionRows = HighlightReelReaction::query()
            ->whereIn('highlight_reel_id', $highlightIds)
            ->select(
                'highlight_reel_id',
                'reaction',
                'offer_id',
                'event_id',
                'organization_id',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('highlight_reel_id', 'reaction', 'offer_id', 'event_id', 'organization_id')
            ->get();

        $reactionCounts = $reactionRows->groupBy('highlight_reel_id')->map(function ($rows) {
            return $rows->groupBy(function ($row) {
                $offer = $row->offer_id ?? 0;
                $event = $row->event_id ?? 0;
                $org = $row->organization_id ?? 0;
                return "offer:$offer|event:$event|org:$org";
            })->map(function ($group) {
                return $group->mapWithKeys(function ($row) {
                    return [$row->reaction => (int)$row->total];
                });
            });
        });

        $shareRows = HighlightReelShare::query()
            ->whereIn('highlight_reel_id', $highlightIds)
            ->select(
                'highlight_reel_id',
                'offer_id',
                'event_id',
                'organization_id',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('highlight_reel_id', 'offer_id', 'event_id', 'organization_id')
            ->get();

        $shareCounts = $shareRows->groupBy('highlight_reel_id')->map(function ($rows) {
            return $rows->mapWithKeys(function ($row) {
                $offer = $row->offer_id ?? 0;
                $event = $row->event_id ?? 0;
                $org = $row->organization_id ?? 0;
                $key = "offer:$offer|event:$event|org:$org";
                return [$key => (int)$row->total];
            });
        });

        $formatted = $highlights->map(function ($highlight) use ($reactionCounts, $shareCounts) {
            $linkUrl = $highlight->external_link;
            $linkType = $linkUrl ? 'external' : null;

            return [
                'id' => $highlight->id,
                'title' => $highlight->title,
                'thumbnail' => $highlight->thumbnail,
                'external_link' => $highlight->external_link,
                'link_url' => $linkUrl,
                'link_type' => $linkType,
                'sort_order' => $highlight->sort_order,
                'items' => $highlight->items->map(function ($item) use ($reactionCounts, $shareCounts) {
                    $offer = $item->offer_id ?? 0;
                    $event = $item->event_id ?? 0;
                    $org = $item->organization_id ?? 0;
                    $key = "offer:$offer|event:$event|org:$org";
                    $itemReactions = $reactionCounts[$item->highlight_id][$key] ?? [];
                    $itemShareCount = (int)($shareCounts[$item->highlight_id][$key] ?? 0);

                    return [
                        'id' => $item->id,
                        'title' => $item->title,
                        'subtitle' => $item->subtitle,
                        'description' => $item->description,
                        'offer_id' => $item->offer_id,
                        'event_id' => $item->event_id,
                        'organization_id' => $item->organization_id,
                        'image' => $item->image,
                        'external_link' => $item->external_link,
                        'sort_order' => $item->sort_order,
                        'reactions' => $itemReactions,
                        'share_count' => $itemShareCount,
                    ];
                })->values(),
            ];
        });

        return response()->json(['success' => true, 'highlights' => $formatted]);
    }

    public function highlightReactions(Request $request)
    {
        $user = $request->user();
        $reactions = HighlightReelReaction::query()
            ->where('user_id', $user->id)
            ->get(['highlight_reel_id', 'reaction', 'offer_id', 'event_id', 'organization_id'])
            ->map(function ($reaction) {
                $offer = $reaction->offer_id ?? 0;
                $event = $reaction->event_id ?? 0;
                $org = $reaction->organization_id ?? 0;
                return [
                    'highlight_reel_id' => $reaction->highlight_reel_id,
                    'offer_id' => $reaction->offer_id,
                    'event_id' => $reaction->event_id,
                    'organization_id' => $reaction->organization_id,
                    'reaction' => $reaction->reaction,
                    'key' => "highlight:{$reaction->highlight_reel_id}|offer:$offer|event:$event|org:$org",
                ];
            });

        return response()->json(['success' => true, 'reactions' => $reactions]);
    }

    public function reactToHighlight(Request $request, HighlightReel $highlight)
    {
        $data = $request->validate([
            'reaction' => ['required', 'string', 'max:20'],
            'offer_id' => ['nullable', 'exists:offers,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
        ]);

        $reaction = strtolower(trim($data['reaction']));
        $allowed = ['like', 'love', 'care', 'wow', 'sad', 'angry'];
        if (!in_array($reaction, $allowed, true)) {
            return response()->json(['error' => 'Invalid reaction.'], 422);
        }

        $offerId = $data['offer_id'] ?? null;
        $eventId = $data['event_id'] ?? null;
        $organizationId = $data['organization_id'] ?? null;

        $targets = array_filter([$offerId, $eventId, $organizationId], fn ($value) => !empty($value));
        if (count($targets) > 1) {
            return response()->json(['error' => 'Only one of offer_id, event_id, or organization_id is allowed.'], 422);
        }

        $existing = HighlightReelReaction::query()
            ->where('highlight_reel_id', $highlight->id)
            ->where('user_id', $request->user()->id)
            ->where('offer_id', $offerId)
            ->where('event_id', $eventId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($existing && $existing->reaction === $reaction) {
            $existing->delete();
            $userReaction = null;
        } else {
            HighlightReelReaction::updateOrCreate(
                [
                    'highlight_reel_id' => $highlight->id,
                    'user_id' => $request->user()->id,
                    'offer_id' => $offerId,
                    'event_id' => $eventId,
                    'organization_id' => $organizationId,
                ],
                ['reaction' => $reaction]
            );
            $userReaction = $reaction;
        }

        $counts = HighlightReelReaction::query()
            ->where('highlight_reel_id', $highlight->id)
            ->where('offer_id', $offerId)
            ->where('event_id', $eventId)
            ->where('organization_id', $organizationId)
            ->select('reaction', DB::raw('COUNT(*) as total'))
            ->groupBy('reaction')
            ->get()
            ->mapWithKeys(function ($row) {
                return [$row->reaction => (int)$row->total];
            });

        return response()->json([
            'success' => true,
            'reactions' => $counts,
            'user_reaction' => $userReaction,
        ]);
    }

    public function shareHighlight(Request $request, HighlightReel $highlight)
    {
        $data = $request->validate([
            'channel' => ['nullable', 'string', 'max:50'],
            'offer_id' => ['nullable', 'exists:offers,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
        ]);

        $offerId = $data['offer_id'] ?? null;
        $eventId = $data['event_id'] ?? null;
        $organizationId = $data['organization_id'] ?? null;

        $targets = array_filter([$offerId, $eventId, $organizationId], fn ($value) => !empty($value));
        if (count($targets) > 1) {
            return response()->json(['error' => 'Only one of offer_id, event_id, or organization_id is allowed.'], 422);
        }

        HighlightReelShare::create([
            'highlight_reel_id' => $highlight->id,
            'offer_id' => $offerId,
            'event_id' => $eventId,
            'organization_id' => $organizationId,
            'user_id' => $request->user()?->id,
            'channel' => $data['channel'] ?? null,
        ]);

        $shareCount = HighlightReelShare::query()
            ->where('highlight_reel_id', $highlight->id)
            ->where('offer_id', $offerId)
            ->where('event_id', $eventId)
            ->where('organization_id', $organizationId)
            ->count();

        return response()->json(['success' => true, 'share_count' => $shareCount]);
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
                'phone_number' => $offer->phone_number,
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
                'attributes' => $offer->attributes ?? [],
                'sort_order' => $offer->sort_order ?? 0,
                'serial' => $offer->sort_order ?? 0,
                'offer_type' => $offer->offer_type ?? 'regular',
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
        $normalizedQuery = strtolower($q);
        $searchOffersByType = in_array($normalizedQuery, ['offer', 'offers'], true);
        $searchEventsByType = in_array($normalizedQuery, ['event', 'events'], true);
        $searchCategoriesByType = in_array($normalizedQuery, ['category', 'categories'], true);

        $categories = Category::query()
            ->where('status', 'active')
            ->where(function ($builder) use ($q, $searchCategoriesByType) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('short_name', 'like', "%{$q}%");

                if ($searchCategoriesByType) {
                    $builder->orWhereRaw('1 = 1');
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'short_name', 'description', 'icon'])
            ->map(fn ($cat) => [
                'type' => 'category',
                'id' => $cat->id,
                'title' => $cat->name,
                'name' => $cat->name,
                'short_name' => $cat->short_name,
                'description' => $cat->description,
                'subtitle' => $cat->short_name,
                'icon' => $cat->icon,
            ]);

        $events = Event::query()
            ->where('status', 'published')
            ->where(function ($builder) use ($q, $searchEventsByType) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%")
                    ->orWhereHas('organization', function ($orgQuery) use ($q) {
                        $orgQuery->where('organization_name', 'like', "%{$q}%")
                            ->orWhere('username', 'like', "%{$q}%");
                    });

                if ($searchEventsByType) {
                    $builder->orWhereRaw('1 = 1');
                }
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
            ->where(function ($builder) use ($q, $searchOffersByType) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('details', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%")
                    ->orWhere('phone_number', 'like', "%{$q}%")
                    ->orWhereHas('organization', function ($orgQuery) use ($q) {
                        $orgQuery->where('organization_name', 'like', "%{$q}%")
                            ->orWhere('username', 'like', "%{$q}%");
                    });

                if ($searchOffersByType) {
                    $builder->orWhereRaw('1 = 1');
                }
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

        if ($setting->key === 'content_home_slider' && is_array($setting->value)) {
            $setting->value = collect($setting->value)
                ->sortBy(function ($slide, $index) {
                    if (is_array($slide)) {
                        if (array_key_exists('sort_order', $slide)) {
                            return (int) $slide['sort_order'];
                        }
                        if (array_key_exists('serial', $slide)) {
                            return (int) $slide['serial'];
                        }
                        if (array_key_exists('order', $slide)) {
                            return (int) $slide['order'];
                        }
                    }
                    return $index;
                })
                ->values()
                ->all();
        }

        return response()->json([
            'success' => true,
            'setting' => $setting,
        ]);
    }

    public function attributes(Request $request)
    {
        $query = Attribute::query()->with(['values' => fn ($q) => $q->orderBy('id')]);

        if ($request->query('search')) {
            $term = $request->query('search');
            $query->where('name', 'like', "%{$term}%");
        }
        if ($request->query('type')) {
            $query->where('type', $request->query('type'));
        }

        $attributes = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'attributes' => $attributes,
        ]);
    }
}
