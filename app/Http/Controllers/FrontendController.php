<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Offer;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FrontendController extends Controller
{
    public function home()
    {
        $settings = $this->siteSettings();
        $categories = $this->formatCategories(
            Category::query()
            ->where('status', 'active')
            ->orderBy('order')
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name', 'short_name', 'description', 'icon', 'image'])
        );

        $events = $this->formatEvents(
            Event::query()
                ->with('organization:id,organization_name')
                ->where('status', 'published')
                ->orderBy('starting_date')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(['id', 'name', 'description', 'banner', 'starting_date', 'end_date', 'location', 'organization_id'])
        );

        $offers = $this->formatOffers(
            Offer::query()
                ->with('organization:id,organization_name')
                ->where('status', 'active')
                ->orderBy('start_date')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'name', 'details', 'start_date', 'end_date', 'discount_type', 'discount_value', 'cover', 'images', 'organization_id'])
        );

        $homeSlider = $this->hydrateMediaArray($this->contentSetting('content_home_slider', []));
        $blockOne = $this->hydrateMediaArray($this->contentSetting('content_block_one', []));
        $blockTwo = $this->hydrateMediaArray($this->contentSetting('content_block_two', []));
        $blockTwoSliders = collect($blockTwo)
            ->take(6)
            ->map(function ($item) {
                $type = $item['type'] ?? 'category';
                $ref = $item['category'] ?? null;

                $query = Offer::query()
                    ->with('organization:id,organization_name')
                    ->where('status', 'active');

                if ($type === 'category' && $ref) {
                    $query->where('category_id', $ref);
                } elseif ($type === 'event' && $ref) {
                    $query->where('event_id', $ref);
                } elseif ($type === 'offer' && $ref) {
                    $query->where('id', $ref);
                }

                $offers = $this->formatOffers(
                    $query->orderBy('start_date')
                        ->orderByDesc('created_at')
                        ->limit(12)
                        ->get(['id', 'name', 'details', 'start_date', 'end_date', 'discount_type', 'discount_value', 'cover', 'images', 'organization_id', 'category_id', 'event_id'])
                );

                return [
                    'meta' => $item,
                    'offers' => $offers,
                ];
            })
            ->filter(function ($slider) {
                // Keep sliders even if they have no offers so the anchor still works, but require meta.
                return is_array($slider['meta'] ?? null);
            })
            ->values()
            ->all();

        return view('home', compact('settings', 'categories', 'events', 'offers', 'homeSlider', 'blockOne', 'blockTwo', 'blockTwoSliders'));
    }

    public function categories()
    {
        $settings = $this->siteSettings();
        $categories = $this->formatCategories(
            Category::query()
            ->where('status', 'active')
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'description', 'icon', 'image'])
        );

        return view('categories.index', compact('settings', 'categories'));
    }

    public function category(Category $category)
    {
        abort_unless($category->status === 'active', 404);

        $settings = $this->siteSettings();
        $category->display_image = $this->resolveMedia($category->image);

        $events = $this->formatEvents(
            Event::query()
                ->with('organization:id,organization_name')
                ->where('status', 'published')
                ->where('category_id', $category->id)
                ->orderBy('starting_date')
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'description', 'banner', 'starting_date', 'end_date', 'location', 'organization_id'])
        );

        $offers = $this->formatOffers(
            Offer::query()
                ->with('organization:id,organization_name')
                ->where('status', 'active')
                ->where('category_id', $category->id)
                ->orderBy('start_date')
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'details', 'start_date', 'end_date', 'discount_type', 'discount_value', 'cover', 'images', 'organization_id'])
        );

        return view('categories.show', [
            'settings' => $settings,
            'category' => $category,
            'events' => $events,
            'offers' => $offers,
        ]);
    }

    public function events()
    {
        $settings = $this->siteSettings();
        $events = $this->formatEvents(
            Event::query()
                ->with('organization:id,organization_name')
                ->where('status', 'published')
                ->orderBy('starting_date')
                ->orderByDesc('created_at')
                ->paginate(12, ['id', 'name', 'description', 'banner', 'starting_date', 'end_date', 'location', 'organization_id'])
        );

        return view('events.index', compact('settings', 'events'));
    }

    public function event(Event $event)
    {
        abort_unless($event->status === 'published', 404);

        $settings = $this->siteSettings();
        $event->load('organization:id,organization_name');
        $event = $this->formatEvents(collect([$event]))->first();
        $offers = $this->formatOffers(
            Offer::query()
                ->with('organization:id,organization_name')
                ->where('status', 'active')
                ->where('event_id', $event->id)
                ->orderBy('start_date')
                ->orderByDesc('created_at')
                ->limit(12)
                ->get(['id', 'name', 'details', 'start_date', 'end_date', 'discount_type', 'discount_value', 'cover', 'images', 'organization_id'])
        );

        return view('events.show', [
            'settings' => $settings,
            'event' => $event,
            'organization' => $event->organization,
            'offers' => $offers,
        ]);
    }

    public function offers()
    {
        $settings = $this->siteSettings();
        $specialOffers = $this->formatOffers(
            Offer::query()
                ->with('organization:id,organization_name')
                ->where('status', 'active')
                ->orderByDesc('start_date')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get(['id', 'name', 'details', 'start_date', 'end_date', 'discount_type', 'discount_value', 'cover', 'images', 'organization_id'])
        );
        $offers = $this->formatOffers(
            Offer::query()
                ->with('organization:id,organization_name')
                ->where('status', 'active')
                ->orderBy('start_date')
                ->orderByDesc('created_at')
                ->paginate(12, ['id', 'name', 'details', 'start_date', 'end_date', 'discount_type', 'discount_value', 'cover', 'images', 'organization_id'])
        );

        return view('offers.index', compact('settings', 'offers', 'specialOffers'));
    }

    public function offer(Offer $offer)
    {
        abort_unless($offer->status === 'active', 404);

        $settings = $this->siteSettings();
        $offer = $this->formatOffers(collect([$offer]))->first();
        $organization = $offer->organization;

        return view('offers.show', [
            'settings' => $settings,
            'offer' => $offer,
            'organization' => $organization,
        ]);
    }

    public function loginForm()
    {
        $settings = $this->siteSettings();

        return view('auth.login', compact('settings'));
    }

    public function search(Request $request)
    {
        $term = trim($request->get('q', ''));
        if ($term === '') {
            return response()->json(['categories' => [], 'events' => [], 'offers' => []]);
        }

        $like = '%' . $term . '%';

        $categories = Category::query()
            ->where('status', 'active')
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'image'])
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'label' => $category->name,
                    'url' => route('categories.show', $category),
                    'image' => $this->resolveMedia($category->image),
                ];
            });

        $events = Event::query()
            ->where('status', 'published')
            ->where('name', 'like', $like)
            ->orderBy('starting_date')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'banner', 'starting_date'])
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'label' => $event->name,
                    'meta' => optional($event->starting_date)->format('M d, Y'),
                    'url' => route('events.show', $event),
                    'image' => $this->resolveMedia($event->banner),
                ];
            });

        $offers = Offer::query()
            ->where('status', 'active')
            ->where('name', 'like', $like)
            ->orderBy('start_date')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'cover', 'images'])
            ->map(function ($offer) {
                $image = $offer->cover;
                if (!$image && is_array($offer->images)) {
                    $image = $offer->images[0] ?? null;
                }
                return [
                    'id' => $offer->id,
                    'label' => $offer->name,
                    'meta' => null,
                    'url' => route('offers.show', $offer),
                    'image' => $this->resolveMedia($image),
                ];
            });

        return response()->json([
            'categories' => $categories,
            'events' => $events,
            'offers' => $offers,
        ]);
    }

    public function registerForm()
    {
        $settings = $this->siteSettings();

        return view('auth.register', compact('settings'));
    }

    private function siteSettings(): array
    {
        $settings = SystemSetting::query()
            ->whereIn('key', ['site_title', 'tagline', 'logo', 'favicon', 'contact_email'])
            ->where('is_active', true)
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $value = function ($key, $default = null) use ($settings) {
            $payload = $settings[$key] ?? null;

            if (is_array($payload) && array_key_exists('value', $payload)) {
                return $payload['value'];
            }

            return $payload ?? $default;
        };

        return [
            'siteTitle' => $value('site_title', 'oWorld'),
            'tagline' => $value('tagline', 'Local experiences, curated offers, and cultural insights.'),
            'logo' => $this->resolveMedia($value('logo')),
            'favicon' => $this->resolveMedia($value('favicon')),
            'contactEmail' => $value('contact_email'),
        ];
    }

    private function formatEvents($events)
    {
        return $this->transformItems($events, function ($event) {
            $banner = $event->banner;
            if (is_array($banner)) {
                $banner = $banner[0] ?? null;
            }

            $event->display_banner = $this->resolveMedia($banner);
            $event->organization_name = $event->organization->organization_name ?? null;

            return $event;
        });
    }

    private function formatCategories(Collection $categories)
    {
        return $this->transformItems($categories, function ($category) {
            $category->display_image = $this->resolveMedia($category->image);
            return $category;
        });
    }

    private function formatOffers($offers)
    {
        return $this->transformItems($offers, function ($offer) {
            $image = $offer->cover;
            if (!$image && is_array($offer->images)) {
                $image = $offer->images[0] ?? null;
            }

            $offer->display_image = $this->resolveMedia($image);
            $offer->organization_name = $offer->organization->organization_name ?? null;

            return $offer;
        });
    }

    private function resolveMedia($path): ?string
    {
        if (!$path) {
            return null;
        }

        if (!is_string($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return asset($path);
    }

    private function contentSetting(string $key, $default = null)
    {
        $setting = SystemSetting::where('key', $key)->first();
        if (!$setting || $setting->value === null) {
            return $default;
        }

        return $setting->value;
    }

    private function hydrateMediaArray($items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return collect($items)->map(function ($item) {
            if (!is_array($item)) {
                return $item;
            }
            if (!empty($item['image'])) {
                $item['image'] = $this->resolveMedia($item['image']);
            }
            return $item;
        })->all();
    }

    /**
     * @param  \Illuminate\Support\Collection|\Illuminate\Pagination\LengthAwarePaginator|\Illuminate\Pagination\Paginator  $items
     */
    private function transformItems($items, callable $callback)
    {
        if ($items instanceof LengthAwarePaginator || $items instanceof Paginator) {
            $items->getCollection()->transform($callback);
            return $items;
        }

        return $items->map($callback);
    }
}
