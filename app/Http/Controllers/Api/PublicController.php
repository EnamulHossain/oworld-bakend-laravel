<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AnalyticsEvent;
use App\Models\Category;
use App\Models\ContentBlock;
use App\Models\Coupon;
use App\Models\CouponDetail;
use App\Models\Event;
use App\Models\HighlightReel;
use App\Models\HighlightReelReaction;
use App\Models\HighlightReelShare;
use App\Models\Offer;
use App\Models\Attribute;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    public function validateCoupon(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'offer_id' => ['nullable', 'exists:offers,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'order_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->expireCouponsIfNeeded();

        $detail = CouponDetail::query()
            ->with(['couponMaster.organization'])
            ->where('coupon', trim((string) $data['code']))
            ->first();

        if (!$detail || !$detail->couponMaster) {
            return response()->json(['error' => 'Coupon code was not found.'], 404);
        }

        $result = $this->evaluateCouponDetail($detail, $data, Auth::guard('sanctum')->user());
        if (!$result['valid']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json([
            'success' => true,
            'coupon' => $result['coupon'],
        ]);
    }

    public function redeemCoupon(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'offer_id' => ['nullable', 'exists:offers,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'order_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->expireCouponsIfNeeded();

        return DB::transaction(function () use ($request, $data) {
            $detail = CouponDetail::query()
                ->with(['couponMaster.organization'])
                ->lockForUpdate()
                ->where('coupon', trim((string) $data['code']))
                ->first();

            if (!$detail || !$detail->couponMaster) {
                return response()->json(['error' => 'Coupon code was not found.'], 404);
            }

            $result = $this->evaluateCouponDetail($detail, $data, $request->user(), true);
            if (!$result['valid']) {
                return response()->json(['error' => $result['message']], 422);
            }

            $detail->claimed_by_user_id = $detail->claimed_by_user_id ?: $request->user()->id;
            $detail->claimed_at = $detail->claimed_at ?: now();
            $detail->user_id = $request->user()->id;
            $detail->is_used = true;
            $detail->used_at = now();
            $detail->save();

            return response()->json([
                'success' => true,
                'coupon' => $this->buildCouponEvaluationPayload($detail, $data, $request->user()),
            ]);
        });
    }

    public function claimCoupon(Request $request)
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'exists:coupons,id'],
            'offer_id' => ['nullable', 'exists:offers,id'],
            'event_id' => ['nullable', 'exists:events,id'],
        ]);

        $this->expireCouponsIfNeeded([$data['campaign_id']]);

        return DB::transaction(function () use ($request, $data) {
            $user = $request->user();
            $coupon = Coupon::query()
                ->lockForUpdate()
                ->find($data['campaign_id']);

            if (!$coupon) {
                return response()->json(['error' => 'Coupon campaign was not found.'], 404);
            }

            $targetColumn = !empty($data['offer_id']) ? 'offer_id' : (!empty($data['event_id']) ? 'event_id' : null);
            $targetValue = $targetColumn ? (int) ($data[$targetColumn] ?? 0) : null;

            $existingClaim = CouponDetail::query()
                ->with(['couponMaster.organization'])
                ->lockForUpdate()
                ->where('coupon_id', $coupon->id)
                ->where('claimed_by_user_id', $user->id)
                ->where('is_used', false)
                ->when($targetColumn, fn ($query) => $query->where($targetColumn, $targetValue))
                ->orderBy('id')
                ->first();

            if ($existingClaim) {
                return response()->json([
                    'success' => true,
                    'already_claimed' => true,
                    'message' => 'Coupon already added to your account.',
                    'coupon' => $this->buildCouponEvaluationPayload($existingClaim, $data, $user),
                ]);
            }

            if (!empty($coupon->usage_limit_per_user)) {
                $claimedCount = CouponDetail::query()
                    ->where('coupon_id', $coupon->id)
                    ->where(function ($query) use ($user) {
                        $query->where('claimed_by_user_id', $user->id)
                            ->orWhere('user_id', $user->id);
                    })
                    ->count();

                if ($claimedCount >= (int) $coupon->usage_limit_per_user) {
                    return response()->json(['error' => 'You have already received the maximum number of coupons for this campaign.'], 422);
                }
            }

            $detail = CouponDetail::query()
                ->with(['couponMaster.organization'])
                ->lockForUpdate()
                ->where('coupon_id', $coupon->id)
                ->whereNull('claimed_by_user_id')
                ->whereNull('claimed_at')
                ->where('is_used', false)
                ->when($targetColumn, fn ($query) => $query->where($targetColumn, $targetValue))
                ->orderBy('coupon_tier_id')
                ->orderBy('id')
                ->first();

            if (!$detail) {
                return response()->json(['error' => 'No coupon codes are available right now.'], 422);
            }

            $result = $this->evaluateCouponDetail($detail, $data, $user);
            if (!$result['valid']) {
                return response()->json(['error' => $result['message']], 422);
            }

            $detail->claimed_by_user_id = $user->id;
            $detail->claimed_at = now();
            $detail->save();

            return response()->json([
                'success' => true,
                'already_claimed' => false,
                'message' => 'Coupon added to your account.',
                'coupon' => $this->buildCouponEvaluationPayload($detail, $data, $user),
            ]);
        });
    }

    public function categories()
    {
        $categories = Category::query()
            ->where('status', 'active')
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'image', 'icon', 'description', 'banner', 'gallery_sort_order']);

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    public function areas()
    {
        $areas = Area::query()
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'order']);

        return response()->json([
            'success' => true,
            'areas' => $areas,
        ]);
    }

    public function categoryDetail($id)
    {
        $this->syncOfferAndEventLifecycle();

        $category = Category::query()
            ->where('status', 'active')
            ->whereKey($id)
            ->first(['id', 'name', 'short_name', 'description', 'image', 'banner', 'gallery_sort_order', 'icon', 'created_at', 'updated_at']);

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
                'start_time',
                'end_date',
                'end_time',
                'location',
                'address',
                'phone_number',
                'facebook_url',
                'instagram_url',
                'website_url',
                'google_map_url',
                'organization_id',
                'sort_order',
            ]);

        $offers = Offer::query()
            ->with('organization:id,organization_name')
            ->where('status', 'published')
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
                'starting_date' => $event->starting_date,
                'start_time' => $event->start_time,
                'endDate' => $event->end_date,
                'end_date' => $event->end_date,
                'end_time' => $event->end_time,
                'location' => $event->location,
                'address' => $event->address,
                'phone_number' => $event->phone_number,
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

        $categoryBanner = is_array($category->banner) ? $category->banner : [];
        $gallerySortOrder = is_array($category->gallery_sort_order) ? $category->gallery_sort_order : [];
        usort($categoryBanner, function ($left, $right) use ($gallerySortOrder) {
            $leftOrder = isset($gallerySortOrder[$left]) ? (int) $gallerySortOrder[$left] : PHP_INT_MAX;
            $rightOrder = isset($gallerySortOrder[$right]) ? (int) $gallerySortOrder[$right] : PHP_INT_MAX;
            if ($leftOrder === $rightOrder) {
                return 0;
            }
            return $leftOrder <=> $rightOrder;
        });

        return response()->json([
            'success' => true,
            'category' => array_merge($category->toArray(), [
                'itemCount' => $formattedEvents->count() + $formattedOffers->count(),
                'gallery' => $categoryBanner,
                'banners' => $categoryBanner,
            ]),
            'events' => $formattedEvents,
            'offers' => $formattedOffers,
        ]);
    }

    public function events(Request $request)
    {
        $this->syncOfferAndEventLifecycle(Event::class);

        $limit = min((int)$request->query('limit', 20), 100);
        $offset = max((int)$request->query('offset', 0), 0);
        $categoryId = $request->query('category_id');

        $query = Event::query()
            ->with([
                'organization:id,organization_name',
                'category:id,name',
                'area:id,name',
            ])
            ->whereIn('status', ['published', 'expired'])
            ->orderByRaw("CASE WHEN LOWER(TRIM(COALESCE(status, ''))) = 'published' THEN 0 ELSE 1 END")
            ->orderByDesc('sort_order')
            ->orderByDesc('starting_date')
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
            'start_time',
            'end_date',
            'end_time',
            'location',
            'address',
            'phone_number',
            'facebook_url',
            'instagram_url',
            'website_url',
            'google_map_url',
            'organization_id',
            'category_id',
            'area_id',
            'status',
            'sort_order',
        ])->map(function ($event) {
            return [
                'id' => $event->id,
                'status' => $event->status,
                'title' => $event->name,
                'description' => $event->description,
                'date' => $event->starting_date,
                'starting_date' => $event->starting_date,
                'start_time' => $event->start_time,
                'endDate' => $event->end_date,
                'end_date' => $event->end_date,
                'end_time' => $event->end_time,
                'location' => $event->location,
                'address' => $event->address,
                'phone_number' => $event->phone_number,
                'facebook_url' => $event->facebook_url,
                'instagram_url' => $event->instagram_url,
                'website_url' => $event->website_url,
                'google_map_url' => $event->google_map_url,
                'thumbnail' => $event->thumbnail,
                'image' => $event->thumbnail ?: (is_array($event->banner) ? ($event->banner[0] ?? null) : $event->banner),
                'organizationName' => $event->organization?->organization_name,
                'category_id' => $event->category_id,
                'area_id' => $event->area_id,
                'attributes' => $event->attributes ?? [],
                'sort_order' => $event->sort_order ?? 0,
                'serial' => $event->sort_order ?? 0,
                'category' => $event->category ? [
                    'id' => $event->category->id,
                    'name' => $event->category->name,
                ] : null,
                'area' => $event->area ? [
                    'id' => $event->area->id,
                    'name' => $event->area->name,
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
        $this->syncOfferAndEventLifecycle(Event::class);

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
            'start_time',
            'end_date',
            'end_time',
            'location',
            'address',
            'phone_number',
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
                'gallery_sort_order' => $event->gallery_sort_order ?? [],
                'thumbnail' => $event->thumbnail,
                'image' => $event->thumbnail ?: (is_array($event->banner) ? ($event->banner[0] ?? null) : $event->banner),
                'attributes' => $event->attributes ?? [],
                'starting_date' => $event->starting_date,
                'start_time' => $event->start_time,
                'end_date' => $event->end_date,
                'end_time' => $event->end_time,
                'location' => $event->location,
                'address' => $event->address,
                'phone_number' => $event->phone_number,
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
        $this->syncOfferAndEventLifecycle(Event::class, [(int) $id]);

        $user = Auth::guard('sanctum')->user();
        $event = Event::query()
            ->with(['organization:id,organization_name', 'category:id,name'])
            ->whereIn('status', ['published', 'expired'])
            ->find($id);

        if (!$event) {
            return response()->json(['error' => 'Event not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'event' => [
                'id' => $event->id,
                'status' => $event->status,
                'name' => $event->name,
                'description' => $event->description,
                'banner' => $event->banner ?? [],
                'thumbnail' => $event->thumbnail,
                'attributes' => $event->attributes ?? [],
                'starting_date' => $event->starting_date,
                'start_time' => $event->start_time,
                'end_date' => $event->end_date,
                'end_time' => $event->end_time,
                'location' => $event->location,
                'address' => $event->address,
                'phone_number' => $event->phone_number,
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
                'coupons' => $this->getActiveCouponsForTarget('event', (int) $event->id, $user?->id),
            ],
        ]);
    }

    public function offers(Request $request)
    {
        $this->syncOfferAndEventLifecycle(Offer::class);

        $limit = min((int)$request->query('limit', 20), 100);
        $offset = max((int)$request->query('offset', 0), 0);

        $categoryId = $request->query('category_id');
        $offerType = strtolower((string)$request->query('offer_type', ''));

        $query = Offer::query()
            ->with([
                'organization:id,organization_name',
                'category:id,name',
                'area:id,name',
            ])
            ->whereIn('status', ['published', 'expired'])
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($offerType === 'exclusive', fn ($q) => $q->whereRaw("LOWER(TRIM(COALESCE(offer_type, ''))) = 'exclusive'"))
            ->when($offerType === 'regular', fn ($q) => $q->whereRaw("LOWER(TRIM(COALESCE(offer_type, ''))) <> 'exclusive'"))
            ->orderByRaw("CASE WHEN LOWER(TRIM(COALESCE(offer_type, ''))) = 'exclusive' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN LOWER(TRIM(COALESCE(status, ''))) = 'published' THEN 0 ELSE 1 END")
            ->orderByDesc('sort_order')
            ->orderByDesc('start_date')
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
                'is_recurring',
                'recurring_start_date',
                'recurring_end_date',
                'recurring_days',
                'status',
                'sort_order',
                'offer_type',
            ])->map(function ($offer) {
                $images = is_array($offer->images) ? $offer->images : [];
                return [
                    'id' => $offer->id,
                    'status' => $offer->status,
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
                    'is_recurring' => (bool) $offer->is_recurring,
                    'recurring_start_date' => $offer->recurring_start_date,
                    'recurring_end_date' => $offer->recurring_end_date,
                    'recurring_days' => $offer->recurring_days ?? [],
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
                'offer_type' => in_array($offerType, ['exclusive', 'regular'], true) ? $offerType : null,
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
        $this->syncOfferAndEventLifecycle();
        $this->syncContentBlockLifecycle();

        $blocks = ContentBlock::query()
            ->with(['items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->where('is_active', true)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $offerIds = $blocks
            ->flatMap(fn (ContentBlock $block) => $block->items)
            ->filter(fn ($item) => $item->type === 'offer' && $item->target_id)
            ->pluck('target_id')
            ->unique()
            ->values();
        $eventIds = $blocks
            ->flatMap(fn (ContentBlock $block) => $block->items)
            ->filter(fn ($item) => $item->type === 'event' && $item->target_id)
            ->pluck('target_id')
            ->unique()
            ->values();
        $offersById = $offerIds->isNotEmpty()
            ? Offer::whereIn('id', $offerIds)->get([
                'id', 'status', 'start_date', 'start_time', 'end_date', 'end_time', 'expiration_date', 'expiration_time',
            ])->keyBy('id')
            : collect();
        $eventsById = $eventIds->isNotEmpty()
            ? Event::whereIn('id', $eventIds)->get([
                'id', 'status', 'starting_date', 'start_time', 'end_date', 'end_time', 'expiration_date', 'expiration_time',
            ])->keyBy('id')
            : collect();

        $now = now('Asia/Dhaka');
        $formatted = $blocks->map(function (ContentBlock $block) use ($offersById, $eventsById, $now) {
            return [
                'id' => $block->id,
                'name' => $block->name,
                'description' => $block->description,
                'is_featured' => (bool) $block->is_featured,
                'teared_block' => (bool) $block->teared_block,
                'thumbnail_image' => $block->thumbnail_image,
                'featured_sort_order' => $block->featured_sort_order,
                'items' => $block->items
                    ->filter(function ($item) use ($offersById, $eventsById, $now) {
                        if ($item->type === 'offer') {
                            return in_array($offersById->get($item->target_id)?->status, ['published', 'active'], true);
                        }
                        if ($item->type === 'event') {
                            return in_array($eventsById->get($item->target_id)?->status, ['published', 'active'], true);
                        }
                        if ($item->type !== 'custom') {
                            return true;
                        }

                        if ($item->start_date) {
                            $startsAt = Carbon::parse(
                                $item->start_date->format('Y-m-d') . ' ' . ($item->start_time ?: '00:00:00'),
                                'Asia/Dhaka'
                            );
                            if ($startsAt->isAfter($now)) {
                                return false;
                            }
                        }

                        if ($item->end_date) {
                            $endsAt = Carbon::parse(
                                $item->end_date->format('Y-m-d') . ' ' . ($item->end_time ?: '23:59:59'),
                                'Asia/Dhaka'
                            );
                            if ($endsAt->isBefore($now)) {
                                return false;
                            }
                        }

                        return true;
                    })
                    ->map(function ($item) use ($offersById, $eventsById) {
                    $targetStatus = null;
                    $source = null;
                    $startDateField = 'start_date';
                    if ($item->type === 'offer' && $item->target_id) {
                        $source = $offersById->get($item->target_id);
                    }
                    if ($item->type === 'event' && $item->target_id) {
                        $source = $eventsById->get($item->target_id);
                        $startDateField = 'starting_date';
                    }
                    $targetStatus = $source?->status;

                    return [
                        'id' => $item->id,
                        'type' => $item->type,
                        'targetId' => $item->target_id,
                        'target_id' => $item->target_id,
                        'target_status' => $targetStatus,
                        'targetStatus' => $targetStatus,
                        'title' => $item->title,
                        'subtitle' => $item->subtitle,
                        'image' => $item->image,
                        'external_link' => $item->external_link,
                        'start_date' => $source?->{$startDateField}?->format('Y-m-d') ?? $item->start_date?->format('Y-m-d'),
                        'start_time' => $source?->start_time ?? $item->start_time,
                        'end_date' => $source?->end_date?->format('Y-m-d') ?? $item->end_date?->format('Y-m-d'),
                        'end_time' => $source?->end_time ?? $item->end_time,
                        'expiration_date' => $source?->expiration_date?->format('Y-m-d') ?? $item->expiration_date?->format('Y-m-d'),
                        'expiration_time' => $source?->expiration_time ?? $item->expiration_time,
                        'sort_order' => $item->sort_order,
                    ];
                })->values(),
            ];
        });

        return response()->json([
            'success' => true,
            'blocks' => $formatted,
        ]);
    }

    public function offerHighlights(Request $request)
    {
        $this->syncOfferAndEventLifecycle(Offer::class);

        $limit = min((int)$request->query('limit', 12), 50);

        $offers = Offer::query()
            ->with('organization:id,organization_name')
            ->where('status', 'published')
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
                'highlight_reel_item_id',
                'reaction',
                'offer_id',
                'event_id',
                'organization_id',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('highlight_reel_id', 'highlight_reel_item_id', 'reaction', 'offer_id', 'event_id', 'organization_id')
            ->get();

        $reactionCounts = $reactionRows->groupBy('highlight_reel_id')->map(function ($rows) {
            return $rows->groupBy(function ($row) {
                $item = $row->highlight_reel_item_id ?? 0;
                $offer = $row->offer_id ?? 0;
                $event = $row->event_id ?? 0;
                $org = $row->organization_id ?? 0;
                return "item:$item|offer:$offer|event:$event|org:$org";
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
                    $key = "item:{$item->id}|offer:$offer|event:$event|org:$org";
                    $shareKey = "offer:$offer|event:$event|org:$org";
                    $itemReactions = $reactionCounts[$item->highlight_id][$key] ?? [];
                    $itemShareCount = (int)($shareCounts[$item->highlight_id][$shareKey] ?? 0);

                    return [
                        'id' => $item->id,
                        'title' => $item->title,
                        'subtitle' => $item->subtitle,
                        'description' => $item->description,
                        'offer_id' => $item->offer_id,
                        'event_id' => $item->event_id,
                        'organization_id' => $item->organization_id,
                        'image' => $item->image,
                        'video' => $item->video,
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
            ->get(['highlight_reel_id', 'highlight_reel_item_id', 'reaction', 'offer_id', 'event_id', 'organization_id'])
            ->map(function ($reaction) {
                $item = $reaction->highlight_reel_item_id ?? 0;
                $offer = $reaction->offer_id ?? 0;
                $event = $reaction->event_id ?? 0;
                $org = $reaction->organization_id ?? 0;
                return [
                    'highlight_reel_id' => $reaction->highlight_reel_id,
                    'highlight_reel_item_id' => $reaction->highlight_reel_item_id,
                    'offer_id' => $reaction->offer_id,
                    'event_id' => $reaction->event_id,
                    'organization_id' => $reaction->organization_id,
                    'reaction' => $reaction->reaction,
                    'key' => "highlight:{$reaction->highlight_reel_id}|item:$item|offer:$offer|event:$event|org:$org",
                ];
            });

        return response()->json(['success' => true, 'reactions' => $reactions]);
    }

    public function reactToHighlight(Request $request, HighlightReel $highlight)
    {
        $data = $request->validate([
            'reaction' => ['required', 'string', 'max:20'],
            'highlight_reel_item_id' => ['nullable', 'exists:highlight_reels_items,id'],
            'offer_id' => ['nullable', 'exists:offers,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
        ]);

        $reaction = strtolower(trim($data['reaction']));
        $allowed = ['like', 'love', 'care', 'wow', 'sad', 'angry'];
        if (!in_array($reaction, $allowed, true)) {
            return response()->json(['error' => 'Invalid reaction.'], 422);
        }

        $itemId = $data['highlight_reel_item_id'] ?? null;
        $offerId = $data['offer_id'] ?? null;
        $eventId = $data['event_id'] ?? null;
        $organizationId = $data['organization_id'] ?? null;

        if ($itemId) {
            $belongsToHighlight = DB::table('highlight_reels_items')
                ->where('id', $itemId)
                ->where('highlight_id', $highlight->id)
                ->exists();

            if (!$belongsToHighlight) {
                return response()->json(['error' => 'Highlight item does not belong to this highlight.'], 422);
            }
        }

        $targets = array_filter([$offerId, $eventId, $organizationId], fn ($value) => !empty($value));
        if (count($targets) > 1) {
            return response()->json(['error' => 'Only one of offer_id, event_id, or organization_id is allowed.'], 422);
        }

        $existing = HighlightReelReaction::query()
            ->where('highlight_reel_id', $highlight->id)
            ->where('highlight_reel_item_id', $itemId)
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
                    'highlight_reel_item_id' => $itemId,
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
            ->where('highlight_reel_item_id', $itemId)
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
        $this->syncOfferAndEventLifecycle(Offer::class, [(int) $id]);

        $user = Auth::guard('sanctum')->user();
        $offer = Offer::query()
            ->with(['organization:id,organization_name', 'category:id,name', 'area:id,name'])
            ->whereIn('status', ['published', 'expired'])
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
                'status' => $offer->status,
                'name' => $offer->name,
                'details' => $offer->details,
                'start_date' => $offer->start_date,
                'start_time' => $offer->start_time,
                'end_date' => $offer->end_date,
                'end_time' => $offer->end_time,
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
                'gallery_sort_order' => $offer->gallery_sort_order ?? [],
                'thumbnail' => $offer->thumbnail,
                'attributes' => $offer->attributes ?? [],
                'is_recurring' => (bool) $offer->is_recurring,
                'recurring_start_date' => $offer->recurring_start_date,
                'recurring_end_date' => $offer->recurring_end_date,
                'recurring_days' => $offer->recurring_days ?? [],
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
                'coupons' => $this->getActiveCouponsForTarget('offer', (int) $offer->id, $user?->id),
            ],
        ]);
    }

    public function search(Request $request)
    {
        $this->syncOfferAndEventLifecycle();

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
            ->where('status', 'published')
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

    public function trackAnalyticsEvent(Request $request)
    {
        $data = $request->validate([
            'event_name' => ['required', 'string', 'max:120'],
            'params' => ['nullable', 'array'],
            'client_session_id' => ['nullable', 'string', 'max:120'],
        ]);

        $params = is_array($data['params'] ?? null) ? $data['params'] : [];
        $metadata = Arr::except($params, [
            'page',
            'action',
            'filter',
            'channel',
            'highlight_id',
            'offer_id',
            'event_id',
            'organization_id',
            'item_id',
            'item_type',
            'attribute_id',
        ]);

        $toNullableInt = static function ($value): ?int {
            if ($value === null || $value === '') {
                return null;
            }
            $parsed = filter_var($value, FILTER_VALIDATE_INT);
            return $parsed !== false && $parsed > 0 ? $parsed : null;
        };

        $userId = Auth::guard('sanctum')->user()?->id ?? $request->user()?->id;

        $event = AnalyticsEvent::create([
            'event_name' => trim((string) $data['event_name']),
            'page' => isset($params['page']) ? trim((string) $params['page']) : null,
            'action' => isset($params['action']) ? trim((string) $params['action']) : null,
            'filter' => isset($params['filter']) ? trim((string) $params['filter']) : null,
            'channel' => isset($params['channel']) ? trim((string) $params['channel']) : null,
            'highlight_id' => $toNullableInt($params['highlight_id'] ?? null),
            'offer_id' => $toNullableInt($params['offer_id'] ?? $params['item_id'] ?? null),
            'event_id' => $toNullableInt($params['event_id'] ?? null),
            'organization_id' => $toNullableInt($params['organization_id'] ?? null),
            'user_id' => $userId,
            'client_session_id' => trim((string) ($data['client_session_id'] ?? '')) ?: null,
            'metadata' => empty($metadata) ? null : $metadata,
            'occurred_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'event_id' => $event->id,
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

    private function evaluateCouponDetail(CouponDetail $detail, array $data, $user = null, bool $forRedemption = false): array
    {
        $coupon = $detail->couponMaster;

        if (!$coupon) {
            return ['valid' => false, 'message' => 'Coupon campaign no longer exists.'];
        }

        $targets = array_filter([
            $data['offer_id'] ?? null,
            $data['event_id'] ?? null,
        ], fn ($value) => !empty($value));

        if (count($targets) > 1) {
            return ['valid' => false, 'message' => 'Coupon can be checked against one target at a time.'];
        }

        if (!in_array($coupon->status ?? 'draft', ['published', 'active'], true)) {
            return ['valid' => false, 'message' => 'Coupon is not active.'];
        }

        if ($this->couponStartsInFuture($coupon)) {
            return ['valid' => false, 'message' => 'Coupon is not active yet.'];
        }

        if ($this->couponIsExpired($coupon)) {
            return ['valid' => false, 'message' => 'Coupon has expired.'];
        }

        if ($detail->is_used) {
            return ['valid' => false, 'message' => 'Coupon has already been used.'];
        }

        if (!empty($detail->offer_id) && (string) ($data['offer_id'] ?? '') !== (string) $detail->offer_id) {
            return ['valid' => false, 'message' => 'Coupon is not valid for this offer.'];
        }

        if (!empty($detail->event_id) && (string) ($data['event_id'] ?? '') !== (string) $detail->event_id) {
            return ['valid' => false, 'message' => 'Coupon is not valid for this event.'];
        }

        $orderAmount = array_key_exists('order_amount', $data) ? (float) ($data['order_amount'] ?? 0) : null;
        $minOrderAmount = $detail->min_order_amount !== null ? (float) $detail->min_order_amount : null;
        if ($minOrderAmount !== null && $orderAmount !== null && $orderAmount < $minOrderAmount) {
            return ['valid' => false, 'message' => 'Order amount is below the coupon minimum.'];
        }

        $referralRequired = (int) ($detail->referral_required_count ?? 0);
        $actualReferralCount = $user
            ? (int) $user->referralsMade()->where('status', 'completed')->count()
            : 0;

        if ($referralRequired > 0 && !$user) {
            return ['valid' => false, 'message' => 'Login is required for referral-based coupons.'];
        }

        if ($referralRequired > 0 && $actualReferralCount < $referralRequired) {
            return ['valid' => false, 'message' => 'Referral requirement has not been met for this coupon.'];
        }

        if ($forRedemption && !$user) {
            return ['valid' => false, 'message' => 'You must be logged in to redeem a coupon.'];
        }

        if ($user && !empty($coupon->usage_limit_per_user)) {
            $usedCount = CouponDetail::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->where('is_used', true)
                ->count();

            if ($usedCount >= (int) $coupon->usage_limit_per_user) {
                return ['valid' => false, 'message' => 'You have reached the usage limit for this campaign.'];
            }
        }

        return [
            'valid' => true,
            'message' => null,
            'coupon' => $this->buildCouponEvaluationPayload($detail, $data, $user),
        ];
    }

    private function buildCouponEvaluationPayload(CouponDetail $detail, array $data, $user = null): array
    {
        $coupon = $detail->couponMaster;
        $orderAmount = array_key_exists('order_amount', $data) ? (float) ($data['order_amount'] ?? 0) : null;
        $discountValue = $detail->discount_value !== null ? (float) $detail->discount_value : 0.0;
        $maxDiscountAmount = $detail->max_discount_amount !== null ? (float) $detail->max_discount_amount : null;
        $discountAmount = null;

        if ($orderAmount !== null) {
            if ($detail->discount_type === 'percentage') {
                $discountAmount = round($orderAmount * ($discountValue / 100), 2);
                if ($maxDiscountAmount !== null) {
                    $discountAmount = min($discountAmount, $maxDiscountAmount);
                }
            } elseif ($detail->discount_type === 'flat') {
                $discountAmount = min($discountValue, $orderAmount);
            }
        }

        return [
            'campaign_id' => $coupon->id,
            'campaign_name' => $coupon->name,
            'image' => $coupon->image,
            'modal_image' => $coupon->modal_image,
            'modal_title' => $coupon->modal_title,
            'modal_main_text' => $coupon->modal_main_text,
            'modal_sub_text' => $coupon->modal_sub_text,
            'modal_placeholder_text' => $coupon->modal_placeholder_text,
            'modal_success_message' => $coupon->modal_success_message,
            'campaign_type' => $coupon->campaign_type,
            'code' => $detail->coupon,
            'offer_id' => $detail->offer_id,
            'event_id' => $detail->event_id,
            'organization_id' => $detail->organization_id ?: $coupon->organization_id,
            'discount_type' => $detail->discount_type,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'max_discount_amount' => $maxDiscountAmount,
            'min_order_amount' => $detail->min_order_amount !== null ? (float) $detail->min_order_amount : null,
            'referral_required_count' => (int) ($detail->referral_required_count ?? 0),
            'current_referral_count' => $user ? (int) $user->referralsMade()->where('status', 'completed')->count() : 0,
            'usage_limit_per_user' => $coupon->usage_limit_per_user !== null ? (int) $coupon->usage_limit_per_user : null,
            'status' => $coupon->status,
            'start_date' => $coupon->start_date?->format('Y-m-d'),
            'end_date' => $coupon->end_date?->format('Y-m-d'),
            'claimed' => !empty($detail->claimed_at),
            'claimed_at' => optional($detail->claimed_at)?->toISOString(),
            'used' => (bool) $detail->is_used,
            'used_at' => optional($detail->used_at)?->toISOString(),
            'redeemed_by_user_id' => $detail->user_id,
            'current_user_id' => $user?->id,
        ];
    }

    private function couponStartsInFuture(Coupon $coupon): bool
    {
        if (!$coupon->start_date) {
            return false;
        }

        $startAt = sprintf(
            '%s %s',
            $coupon->start_date instanceof \DateTimeInterface ? $coupon->start_date->format('Y-m-d') : (string) $coupon->start_date,
            $coupon->start_time ?: '00:00:00'
        );

        return $this->couponNow()->lt($startAt);
    }

    private function couponIsExpired(Coupon $coupon): bool
    {
        if (!$coupon->end_date) {
            return false;
        }

        $endAt = sprintf(
            '%s %s',
            $coupon->end_date instanceof \DateTimeInterface ? $coupon->end_date->format('Y-m-d') : (string) $coupon->end_date,
            $coupon->end_time ?: '23:59:59'
        );

        return $this->couponNow()->gt($endAt);
    }

    private function getActiveCouponsForTarget(string $targetType, int $targetId, ?int $userId = null): array
    {
        if (!in_array($targetType, ['offer', 'event'], true) || $targetId < 1) {
            return [];
        }

        $relationColumn = $targetType === 'offer' ? 'offer_id' : 'event_id';

        return Coupon::query()
            ->with([
                'organization:id,organization_name,username',
                'tiers:id,coupon_id,label,quantity,discount_type,discount_value,max_discount_amount,min_order_amount,referral_required_count,sort_order',
                'details:id,coupon_id,offer_id,event_id,coupon,claimed_by_user_id',
            ])
            ->whereIn('status', ['published', 'active'])
            ->whereHas('details', function ($query) use ($relationColumn, $targetId) {
                $query->where($relationColumn, $targetId);
            })
            ->orderByDesc('id')
            ->get()
            ->filter(function (Coupon $coupon) {
                return !$this->couponStartsInFuture($coupon) && !$this->couponIsExpired($coupon);
            })
            ->map(function (Coupon $coupon) use ($targetType, $targetId, $userId) {
                $firstTier = $coupon->tiers->sortBy('sort_order')->first();
                $currentUserClaim = $userId
                    ? $coupon->details
                        ->where('claimed_by_user_id', $userId)
                        ->where($targetType === 'offer' ? 'offer_id' : 'event_id', $targetId)
                        ->sortBy('id')
                        ->first()
                    : null;

                return [
                    'id' => $coupon->id,
                    'campaign_name' => $coupon->name,
                    'image' => $coupon->image,
                    'description' => $coupon->description,
                    'modal_image' => $coupon->modal_image,
                    'modal_title' => $coupon->modal_title,
                    'modal_main_text' => $coupon->modal_main_text,
                    'modal_sub_text' => $coupon->modal_sub_text,
                    'modal_placeholder_text' => $coupon->modal_placeholder_text,
                    'modal_success_message' => $coupon->modal_success_message,
                    'campaign_type' => $coupon->campaign_type,
                    'organization_name' => $coupon->organization?->organization_name ?: $coupon->organization?->username,
                    'status' => $coupon->status,
                    'offer_id' => $targetType === 'offer' ? $targetId : null,
                    'event_id' => $targetType === 'event' ? $targetId : null,
                    'coupon_no' => (int) ($coupon->total_coupon ?? 0),
                    'usage_limit_per_user' => $coupon->usage_limit_per_user !== null ? (int) $coupon->usage_limit_per_user : null,
                    'start_date' => $coupon->start_date?->format('Y-m-d'),
                    'start_time' => $coupon->start_time,
                    'end_date' => $coupon->end_date?->format('Y-m-d'),
                    'end_time' => $coupon->end_time,
                    'discount_type' => $firstTier?->discount_type,
                    'discount_value' => $firstTier?->discount_value !== null ? (float) $firstTier->discount_value : null,
                    'max_discount_amount' => $firstTier?->max_discount_amount !== null ? (float) $firstTier->max_discount_amount : null,
                    'min_order_amount' => $firstTier?->min_order_amount !== null ? (float) $firstTier->min_order_amount : null,
                    'referral_required_count' => (int) ($firstTier?->referral_required_count ?? 0),
                    'current_user_claimed' => (bool) $currentUserClaim,
                    'claimed_code' => $currentUserClaim?->coupon,
                    'tiers' => $coupon->tiers
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($tier) => [
                            'id' => $tier->id,
                            'label' => $tier->label,
                            'quantity' => (int) ($tier->quantity ?? 0),
                            'discount_type' => $tier->discount_type,
                            'discount_value' => $tier->discount_value !== null ? (float) $tier->discount_value : null,
                            'max_discount_amount' => $tier->max_discount_amount !== null ? (float) $tier->max_discount_amount : null,
                            'min_order_amount' => $tier->min_order_amount !== null ? (float) $tier->min_order_amount : null,
                            'referral_required_count' => (int) ($tier->referral_required_count ?? 0),
                        ])
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function expireCouponsIfNeeded(?array $couponIds = null): void
    {
        Coupon::query()
            ->when($couponIds, fn ($query) => $query->whereIn('id', $couponIds))
            ->whereNotIn('status', ['inactive', 'archived', 'canceled'])
            ->whereNotNull('expiration_date')
            ->whereRaw("timestamp(expiration_date, coalesce(expiration_time, '23:59:59')) < ?", [$this->couponNow()->toDateTimeString()])
            ->update([
                'status' => 'inactive',
                'updated_at' => now(),
            ]);

        Coupon::query()
            ->when($couponIds, fn ($query) => $query->whereIn('id', $couponIds))
            ->whereNotIn('status', ['expired', 'inactive', 'archived', 'canceled'])
            ->whereNotNull('end_date')
            ->whereRaw("timestamp(end_date, coalesce(end_time, '23:59:59')) < ?", [$this->couponNow()->toDateTimeString()])
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
    }

    private function syncOfferAndEventLifecycle(?string $modelClass = null, ?array $ids = null): void
    {
        $classes = $modelClass ? [$modelClass] : [Offer::class, Event::class];
        $now = now('Asia/Dhaka')->toDateTimeString();

        foreach ($classes as $class) {
            $startDate = $class === Event::class ? 'starting_date' : 'start_date';

            $class::query()
                ->when($ids, fn ($query) => $query->whereIn('id', $ids))
                ->where('status', 'scheduled')
                ->whereNotNull($startDate)
                ->whereRaw("timestamp($startDate, coalesce(start_time, '00:00:00')) <= ?", [$now])
                ->update([
                    'status' => 'published',
                    'updated_at' => now(),
                ]);

            $class::query()
                ->when($ids, fn ($query) => $query->whereIn('id', $ids))
                ->where('status', 'published')
                ->whereNotNull('end_date')
                ->whereRaw("timestamp(end_date, coalesce(end_time, '23:59:59')) < ?", [$now])
                ->update([
                    'status' => 'expired',
                    'updated_at' => now(),
                ]);

            $class::query()
                ->when($ids, fn ($query) => $query->whereIn('id', $ids))
                ->where('status', 'expired')
                ->whereNotNull('expiration_date')
                ->whereRaw("timestamp(expiration_date, coalesce(expiration_time, '23:59:59')) < ?", [$now])
                ->update([
                    'status' => 'archived',
                    'updated_at' => now(),
                ]);
        }
    }

    private function syncContentBlockLifecycle(): void
    {
        $now = now('Asia/Dhaka')->toDateTimeString();

        ContentBlock::query()
            ->where('status', 'scheduled')
            ->whereNotNull('start_date')
            ->whereRaw("timestamp(start_date, coalesce(start_time, '00:00:00')) <= ?", [$now])
            ->update(['status' => 'published', 'is_active' => true, 'updated_at' => now()]);

        ContentBlock::query()
            ->where('status', 'published')
            ->whereNotNull('end_date')
            ->whereRaw("timestamp(end_date, coalesce(end_time, '23:59:59')) < ?", [$now])
            ->update(['status' => 'expired', 'is_active' => false, 'updated_at' => now()]);

        ContentBlock::query()
            ->where('status', 'expired')
            ->whereNotNull('expiration_date')
            ->whereRaw("timestamp(expiration_date, coalesce(expiration_time, '23:59:59')) < ?", [$now])
            ->update(['status' => 'archived', 'is_active' => false, 'updated_at' => now()]);
    }

    private function couponNow()
    {
        return now('Asia/Dhaka');
    }

    public function attributes(Request $request)
    {
        $this->expireAttributesIfNeeded();
        $today = now('Asia/Dhaka')->toDateString();
        $status = $this->normalizeAttributeStatus($request->query('status') ?: 'published');

        $query = Attribute::query()
            ->where('status', $status)
            ->where(function ($query) use ($today) {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $today);
            })
            ->with(['values' => fn ($q) => $q->orderBy('id')]);

        if ($request->query('search')) {
            $term = $request->query('search');
            $query->where('name', 'like', "%{$term}%");
        }
        if ($request->query('type')) {
            $query->where('type', $request->query('type'));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        $attributes = $query
            ->orderByDesc('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'attributes' => $attributes,
        ]);
    }

    private function expireAttributesIfNeeded(): void
    {
        Attribute::query()
            ->where('status', 'published')
            ->where('auto_expires', true)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now('Asia/Dhaka')->toDateString())
            ->update(['status' => 'expired']);
    }

    private function normalizeAttributeStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'active' => 'published',
            'inactive' => 'draft',
            default => strtolower((string) $status),
        };
    }
}
