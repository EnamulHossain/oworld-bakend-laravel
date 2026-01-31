<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Offer;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminWebController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'stats' => [
                'totalUsers' => User::count(),
                'adminCount' => User::where('role', 'admin')->count(),
                'organizationCount' => User::where('role', 'organization')->count(),
                'userCount' => User::where('role', 'user')->count(),
                'totalCategories' => Category::count(),
                'activeCategories' => Category::where('status', 'active')->count(),
                'activeOffers' => Offer::where('status', 'active')->count(),
                'publishedEvents' => Event::where('status', 'published')->count(),
            ],
            'recentEvents' => Event::with('category:id,name')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get(['id', 'name', 'status', 'starting_date', 'category_id']),
            'recentOffers' => Offer::with('category:id,name')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get(['id', 'name', 'status', 'start_date', 'end_date', 'category_id']),
        ]);
    }

    public function categories()
    {
        $categories = Category::orderBy('order')->orderBy('name')->paginate(20);
        return view('admin.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
        ]);

        Category::create($data + ['created_by' => $request->user()->id]);

        return back()->with('status', 'Category created.');
    }

    public function updateCategory(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
        ]);

        $category->update($data);
        return back()->with('status', 'Category updated.');
    }

    public function deleteCategory(Category $category)
    {
        $category->delete();
        return back()->with('status', 'Category deleted.');
    }

    public function events()
    {
        $events = Event::with(['category:id,name', 'organization:id,organization_name'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $categories = Category::orderBy('name')->get(['id', 'name']);
        $organizations = User::where('role', 'organization')->orderBy('organization_name')->get(['id', 'organization_name']);

        return view('admin.events', compact('events', 'categories', 'organizations'));
    }

    public function storeEvent(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'starting_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:starting_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        Event::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Event created.');
    }

    public function updateEvent(Request $request, Event $event)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'starting_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:starting_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $event->update($data);

        return back()->with('status', 'Event updated.');
    }

    public function deleteEvent(Event $event)
    {
        $event->delete();

        return back()->with('status', 'Event deleted.');
    }

    public function offers()
    {
        $offers = Offer::with(['category:id,name', 'organization:id,organization_name'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $categories = Category::orderBy('name')->get(['id', 'name']);
        $organizations = User::where('role', 'organization')->orderBy('organization_name')->get(['id', 'organization_name']);
        $events = Event::orderBy('name')->get(['id', 'name']);

        return view('admin.offers', compact('offers', 'categories', 'organizations', 'events'));
    }

    public function createOffer()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $organizations = User::where('role', 'organization')->orderBy('organization_name')->get(['id', 'organization_name']);
        $events = Event::orderBy('name')->get(['id', 'name']);

        return view('admin.offers-create', compact('categories', 'organizations', 'events'));
    }

    public function editOffer(Offer $offer)
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $organizations = User::where('role', 'organization')->orderBy('organization_name')->get(['id', 'organization_name']);
        $events = Event::orderBy('name')->get(['id', 'name']);

        return view('admin.offers-edit', compact('offer', 'categories', 'organizations', 'events'));
    }

    public function storeOffer(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'discount_type' => ['nullable', Rule::in(['percentage', 'flat', 'bogo', 'custom'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'thumbnail' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'images.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'videos.*' => ['nullable', 'file', 'mimes:mp4,webm,mov', 'max:51200'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'expired'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $images[] = '/storage/' . $file->store('uploads/offers', 'public');
            }
        }
        $videos = [];
        if ($request->hasFile('videos')) {
            foreach ($request->file('videos') as $file) {
                $videos[] = '/storage/' . $file->store('uploads/offers', 'public');
            }
        }

        $payload = $data;
        $payload['thumbnail'] = $request->hasFile('thumbnail') ? '/storage/' . $request->file('thumbnail')->store('uploads/offers', 'public') : null;
        $payload['cover'] = $request->hasFile('cover') ? '/storage/' . $request->file('cover')->store('uploads/offers', 'public') : null;
        $payload['images'] = $images;
        $payload['videos'] = $videos;
        $payload['created_by'] = $request->user()->id;

        Offer::create($payload);

        return back()->with('status', 'Offer created.');
    }

    public function updateOffer(Request $request, Offer $offer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'discount_type' => ['nullable', Rule::in(['percentage', 'flat', 'bogo', 'custom'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'thumbnail' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'images.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'videos.*' => ['nullable', 'file', 'mimes:mp4,webm,mov', 'max:51200'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'expired'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $images = $offer->images ?? [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $images[] = '/storage/' . $file->store('uploads/offers', 'public');
            }
        }
        $videos = $offer->videos ?? [];
        if ($request->hasFile('videos')) {
            foreach ($request->file('videos') as $file) {
                $videos[] = '/storage/' . $file->store('uploads/offers', 'public');
            }
        }

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = '/storage/' . $request->file('thumbnail')->store('uploads/offers', 'public');
        }
        if ($request->hasFile('cover')) {
            $data['cover'] = '/storage/' . $request->file('cover')->store('uploads/offers', 'public');
        }
        $data['images'] = $images;
        $data['videos'] = $videos;

        $offer->update($data + ['updated_by' => $request->user()->id]);

        return back()->with('status', 'Offer updated.');
    }

    public function deleteOffer(Offer $offer)
    {
        $offer->delete();
        return back()->with('status', 'Offer deleted.');
    }

    public function users()
    {
        $users = User::orderByDesc('created_at')->paginate(20);
        return view('admin.users', compact('users'));
    }

    public function updateUserRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['user', 'organization', 'admin'])],
        ]);

        $user->update(['role' => $data['role']]);
        $user->syncRoles([$data['role']]);

        return back()->with('status', 'User role updated.');
    }

    public function deleteUser(User $user)
    {
        $user->delete();
        return back()->with('status', 'User removed.');
    }

    public function settings()
    {
        $settings = SystemSetting::orderBy('group')->orderBy('key')->paginate(25);
        return view('admin.settings', compact('settings'));
    }

    public function website()
    {
        $site = $this->siteGeneral();

        return view('admin.website', [
            'settings' => $site,
        ]);
    }

    public function homeSlider()
    {
        $homeSlider = $this->getSettingValue('content_home_slider', []);
        return view('admin.content-home-slider', ['homeSlider' => $homeSlider]);
    }

    public function updateHomeSlider(Request $request)
    {
        $data = $request->validate([
            'home_slider' => ['nullable', 'string'],
            'slide_image_*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $userId = $request->user()->id;
        $slides = $this->normalizeArraySetting($this->decodeJsonOrString($data['home_slider'] ?? ''));

        foreach ($slides as $idx => &$slide) {
            if (!is_array($slide)) {
                $slide = [];
            }

            if (!array_key_exists('sort_order', $slide)) {
                if (array_key_exists('serial', $slide)) {
                    $slide['sort_order'] = (int) $slide['serial'];
                } elseif (array_key_exists('order', $slide)) {
                    $slide['sort_order'] = (int) $slide['order'];
                } else {
                    $slide['sort_order'] = $idx;
                }
            } else {
                $slide['sort_order'] = (int) $slide['sort_order'];
            }
        }

        $slides = collect($slides)
            ->sortBy(fn ($slide, $index) => is_array($slide) ? ($slide['sort_order'] ?? $index) : $index)
            ->values()
            ->all();

        foreach ($slides as $idx => &$slide) {
            $file = $request->file("slide_image_{$idx}");
            if ($file) {
                $path = $file->store('uploads/content', 'public');
                $slide['image'] = '/storage/' . $path;
            }
        }

        $this->setSetting('content_home_slider', $slides, 'Home Slider', 'Slides for the landing page hero', 'content', $userId);

        return back()->with('status', 'Home slider updated.');
    }

    public function contentBlockOne()
    {
        $blockOne = $this->getSettingValue('content_block_one', []);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $events = Event::orderBy('name')->get(['id', 'name']);
        $offers = Offer::orderBy('name')->get(['id', 'name']);

        return view('admin.content-block-one', [
            'blockOne' => $blockOne,
            'categories' => $categories,
            'events' => $events,
            'offers' => $offers,
        ]);
    }

    public function updateContentBlockOne(Request $request)
    {
        $data = $request->validate([
            'block_one' => ['nullable', 'string'],
            'block1_image_*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $userId = $request->user()->id;
        $block = $this->normalizeArraySetting($this->decodeJsonOrString($data['block_one'] ?? ''));

        foreach ($block as $idx => &$card) {
            $file = $request->file("block1_image_{$idx}");
            if ($file) {
                $path = $file->store('uploads/content', 'public');
                $card['image'] = '/storage/' . $path;
            }
        }

        $this->setSetting('content_block_one', $block, 'Content Block 1', 'First content block on landing page', 'content', $userId);

        return back()->with('status', 'Content Block 1 updated.');
    }

    public function contentBlockTwo()
    {
        $blockTwo = $this->getSettingValue('content_block_two', []);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $events = Event::orderBy('name')->get(['id', 'name']);
        $offers = Offer::orderBy('name')->get(['id', 'name']);

        return view('admin.content-block-two', [
            'blockTwo' => $blockTwo,
            'categories' => $categories,
            'events' => $events,
            'offers' => $offers,
        ]);
    }

    public function updateContentBlockTwo(Request $request)
    {
        $data = $request->validate([
            'block_two' => ['nullable', 'string'],
            'block2_image_*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $userId = $request->user()->id;
        $block = $this->normalizeArraySetting($this->decodeJsonOrString($data['block_two'] ?? ''));

        foreach ($block as $idx => &$card) {
            $file = $request->file("block2_image_{$idx}");
            if ($file) {
                $path = $file->store('uploads/content', 'public');
                $card['image'] = '/storage/' . $path;
            }
        }

        $this->setSetting('content_block_two', $block, 'Content Block 2', 'Second content block on landing page', 'content', $userId);

        return back()->with('status', 'Content Block 2 updated.');
    }

    public function updateWebsite(Request $request)
    {
        $data = $request->validate([
            'siteTitle' => ['required', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'metaKeywords' => ['nullable', 'string'],
            'metaDescription' => ['nullable', 'string'],
            'contactEmail' => ['nullable', 'email'],
            'contactPhone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
            'favicon' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,ico', 'max:2048'],
            'ogImage' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'remove_ogImage' => ['nullable', 'boolean'],
        ]);

        $value = $this->siteGeneral();
        $fields = [
            'siteTitle',
            'tagline',
            'description',
            'metaKeywords',
            'metaDescription',
            'contactEmail',
            'contactPhone',
            'address',
        ];

        foreach ($fields as $field) {
            $value[$field] = $data[$field] ?? '';
        }

        $value['logo'] = $this->handleAsset($request, 'logo', $value['logo'] ?? null, (bool)$request->boolean('remove_logo'));
        $value['favicon'] = $this->handleAsset($request, 'favicon', $value['favicon'] ?? null, (bool)$request->boolean('remove_favicon'));
        $value['ogImage'] = $this->handleAsset($request, 'ogImage', $value['ogImage'] ?? null, (bool)$request->boolean('remove_ogImage'));

        $setting = SystemSetting::firstOrNew(['key' => 'site_general']);
        $setting->fill([
            'value' => $value,
            'type' => 'json',
            'label' => 'Website Setup',
            'description' => 'Branding, meta, and contact info',
            'group' => 'site',
            'is_active' => true,
            'updated_by' => $request->user()->id,
            'created_by' => $setting->exists ? $setting->created_by : $request->user()->id,
        ]);
        $setting->save();

        return back()->with('status', 'Website settings saved.');
    }

    public function storeSetting(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:150', 'unique:system_settings,key'],
            'value' => ['nullable'],
            'type' => ['nullable', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'group' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        SystemSetting::create($data + [
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Setting created.');
    }

    public function updateSetting(Request $request, SystemSetting $setting)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:150', Rule::unique('system_settings', 'key')->ignore($setting->id)],
            'value' => ['nullable'],
            'type' => ['nullable', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'group' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $setting->update($data + ['updated_by' => $request->user()->id]);

        return back()->with('status', 'Setting updated.');
    }

    public function deleteSetting(SystemSetting $setting)
    {
        $setting->delete();
        return back()->with('status', 'Setting removed.');
    }

    private function siteGeneral(): array
    {
        $setting = SystemSetting::where('key', 'site_general')->first();
        $defaults = [
            'siteTitle' => 'oWorld',
            'tagline' => 'Local experiences, curated offers, and cultural insights.',
            'description' => '',
            'metaKeywords' => '',
            'metaDescription' => '',
            'contactEmail' => '',
            'contactPhone' => '',
            'address' => '',
            'logo' => '',
            'favicon' => '',
            'ogImage' => '',
        ];

        if (!$setting || !is_array($setting->value)) {
            return $defaults;
        }

        return array_merge($defaults, $setting->value);
    }

    private function handleAsset(Request $request, string $field, ?string $current, bool $remove): ?string
    {
        if ($remove) {
            return '';
        }

        if ($request->hasFile($field)) {
            $path = $request->file($field)->store('uploads/system', 'public');
            return '/storage/' . $path;
        }

        return $current;
    }

    private function getSettingValue(string $key, $default = null)
    {
        $setting = SystemSetting::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        return $setting->value ?? $default;
    }

    private function setSetting(string $key, $value, string $label, string $description, string $group, int $userId): void
    {
        $setting = SystemSetting::firstOrNew(['key' => $key]);
        $setting->fill([
            'value' => $value,
            'type' => 'json',
            'label' => $label,
            'description' => $description,
            'group' => $group,
            'is_active' => true,
            'updated_by' => $userId,
            'created_by' => $setting->exists ? $setting->created_by : $userId,
        ]);
        $setting->save();
    }

    private function decodeJsonOrString(?string $payload)
    {
        if (!$payload) {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $payload;
    }

    private function normalizeArraySetting($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return [];
    }
}
