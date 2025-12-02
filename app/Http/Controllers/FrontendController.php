<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Offer;
use App\Models\SystemSetting;
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
            ->get(['id', 'name', 'details', 'start_date', 'end_date', 'discount_type', 'discount_value', 'offer_type', 'cover', 'images', 'organization_id'])
        );

        $homeSlider = $this->hydrateMediaArray($this->contentSetting('content_home_slider', []));
        $blockOne = $this->hydrateMediaArray($this->contentSetting('content_block_one', []));
        $blockTwo = $this->hydrateMediaArray($this->contentSetting('content_block_two', []));

        return view('home', compact('settings', 'categories', 'events', 'offers', 'homeSlider', 'blockOne', 'blockTwo'));
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
                ->get(['id', 'name', 'details', 'start_date', 'end_date', 'discount_type', 'discount_value', 'offer_type', 'cover', 'images', 'organization_id'])
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

    public function offers()
    {
        $settings = $this->siteSettings();
        $offers = $this->formatOffers(
            Offer::query()
                ->with('organization:id,organization_name')
                ->where('status', 'active')
                ->orderBy('start_date')
                ->orderByDesc('created_at')
                ->paginate(12, ['id', 'name', 'details', 'start_date', 'end_date', 'discount_type', 'discount_value', 'offer_type', 'cover', 'images', 'organization_id'])
        );

        return view('offers.index', compact('settings', 'offers'));
    }

    public function loginForm()
    {
        $settings = $this->siteSettings();

        return view('auth.login', compact('settings'));
    }

    public function registerForm()
    {
        $settings = $this->siteSettings();

        return view('auth.register', compact('settings'));
    }

    private function siteSettings(): array
    {
        $settings = SystemSetting::query()
            ->whereIn('key', ['site_title', 'tagline', 'logo', 'contact_email'])
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

    private function resolveMedia(?string $path): ?string
    {
        if (!$path) {
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
