<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Offer;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'item_type' => ['nullable', Rule::in(['event', 'offer'])],
            'item_id' => ['nullable', 'integer'],
        ]);

        $query = WishlistItem::query()
            ->where('user_id', $request->user()->id)
            ->latest();

        if (!empty($validated['item_type'])) {
            $query->where('item_type', $validated['item_type']);
        }

        if (!empty($validated['item_id'])) {
            $query->where('item_id', $validated['item_id']);
        }

        $items = $query->get();

        $events = Event::query()
            ->with(['organization:id,organization_name', 'category:id,name,image'])
            ->whereIn('id', $items->where('item_type', 'event')->pluck('item_id'))
            ->get()
            ->keyBy('id');

        $offers = Offer::query()
            ->with(['organization:id,organization_name', 'category:id,name,image'])
            ->whereIn('id', $items->where('item_type', 'offer')->pluck('item_id'))
            ->get()
            ->keyBy('id');

        return response()->json([
            'items' => $items->map(fn ($item) => $this->formatWishlistItem($item, $events, $offers)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_type' => ['required', Rule::in(['event', 'offer'])],
            'item_id' => ['required', 'integer'],
        ]);

        $resolvedItem = $this->resolveTargetItem($data['item_type'], (int) $data['item_id']);

        if (!$resolvedItem) {
            return response()->json(['error' => 'Item not found or not available.'], 404);
        }

        $wishlistItem = WishlistItem::firstOrCreate([
            'user_id' => $request->user()->id,
            'item_type' => $data['item_type'],
            'item_id' => $data['item_id'],
        ]);

        $events = $data['item_type'] === 'event'
            ? collect([$resolvedItem])->keyBy('id')
            : collect();
        $offers = $data['item_type'] === 'offer'
            ? collect([$resolvedItem])->keyBy('id')
            : collect();

        return response()->json([
            'message' => $wishlistItem->wasRecentlyCreated ? 'Added to wishlist.' : 'Item already saved.',
            'item' => $this->formatWishlistItem($wishlistItem, $events, $offers),
        ], $wishlistItem->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'item_type' => ['required', Rule::in(['event', 'offer'])],
            'item_id' => ['required', 'integer'],
        ]);

        $deleted = WishlistItem::query()
            ->where('user_id', $request->user()->id)
            ->where('item_type', $data['item_type'])
            ->where('item_id', $data['item_id'])
            ->delete();

        return response()->json([
            'message' => $deleted ? 'Removed from wishlist.' : 'Item was not in wishlist.',
        ]);
    }

    private function resolveTargetItem(string $type, int $id): Event|Offer|null
    {
        if ($type === 'event') {
            return Event::query()
                ->with(['organization:id,organization_name', 'category:id,name,image'])
                ->where('status', 'published')
                ->find($id);
        }

        return Offer::query()
            ->with(['organization:id,organization_name', 'category:id,name,image'])
            ->where('status', 'active')
            ->find($id);
    }

    private function formatWishlistItem(WishlistItem $item, $events, $offers): array
    {
        $resolved = $item->item_type === 'event'
            ? $events->get($item->item_id)
            : $offers->get($item->item_id);

        return [
            'id' => $item->id,
            'itemType' => $item->item_type,
            'itemId' => $item->item_id,
            'addedAt' => $item->created_at,
            'item' => $resolved ? $this->transformTarget($item->item_type, $resolved) : null,
        ];
    }

    private function transformTarget(string $type, Event|Offer $item): array
    {
        if ($type === 'event') {
            $banner = is_array($item->banner) ? $item->banner : [];
            $image = $item->thumbnail;

            return [
                'id' => $item->id,
                'title' => $item->name,
                'description' => $item->description,
                'date' => $item->starting_date,
                'endDate' => $item->end_date,
                'location' => $item->location,
                'image' => $image,
                'thumbnail' => $item->thumbnail,
                'banner' => $banner,
                'organizationName' => $item->organization?->organization_name,
                'category' => $item->category ? [
                    'id' => $item->category->id,
                    'name' => $item->category->name,
                    'image' => $item->category->image,
                ] : null,
                'type' => 'event',
            ];
        }

        $images = is_array($item->images) ? $item->images : [];

        return [
            'id' => $item->id,
            'title' => $item->name,
            'description' => $item->details,
            'date' => $item->start_date,
            'endDate' => $item->end_date,
            'discount_type' => $item->discount_type,
            'discount_value' => $item->discount_value,
            'image' => $item->thumbnail,
            'thumbnail' => $item->thumbnail,
            'cover' => $item->cover,
            'images' => $images,
            'organizationName' => $item->organization?->organization_name,
            'category' => $item->category ? [
                'id' => $item->category->id,
                'name' => $item->category->name,
                'image' => $item->category->image,
            ] : null,
            'type' => 'offer',
        ];
    }
}
