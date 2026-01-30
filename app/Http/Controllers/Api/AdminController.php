<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\ContentBlock;
use App\Models\Event;
use App\Models\HighlightReel;
use App\Models\Offer;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function users(Request $request)
    {
        $users = User::query()
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('username', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->get(['id', 'username', 'email', 'role', 'organization_name', 'created_at']);

        return response()->json(['success' => true, 'users' => $users]);
    }

    public function updateUserRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['user', 'organization', 'admin'])],
        ]);

        $user->update(['role' => $data['role']]);
        $user->syncRoles([$data['role']]);

        return response()->json(['success' => true, 'user' => $user]);
    }

    public function deleteUser(User $user)
    {
        $user->delete();
        return response()->json(['success' => true]);
    }

    public function organizations(Request $request)
    {
        $organizations = User::query()
            ->where('role', 'organization')
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('username', 'like', "%{$term}%")
                        ->orWhere('organization_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->get(['id', 'username', 'email', 'organization_name', 'business_type', 'phone', 'created_at']);

        return response()->json([
            'success' => true,
            'organizations' => $organizations->map(fn (User $org) => $this->formatOrganization($org)),
        ]);
    }

    public function storeOrganization(Request $request)
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $organizationName = trim($data['organization_name']);
        if ($organizationName === '') {
            return response()->json(['error' => 'Organization name is required.'], 422);
        }

        $existing = $this->findExistingOrganization($organizationName);
        if ($existing) {
            return response()->json([
                'success' => true,
                'organization' => $this->formatOrganization($existing),
                'created' => false,
            ]);
        }

        $usernameInput = $data['username'] ?? $organizationName;
        $username = $this->generateUniqueUsername($usernameInput);
        $email = $data['email'] ?? $this->generateUniqueOrganizationEmail($organizationName);
        $password = $data['password'] ?? Str::random(16);

        $organization = User::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'organization',
            'organization_name' => $organizationName,
            'business_type' => $data['business_type'] ?? null,
            'phone' => $data['phone'] ?? null,
            'full_name' => null,
            'dob' => null,
            'about' => null,
        ]);

        Role::firstOrCreate(['name' => 'organization', 'guard_name' => 'sanctum']);
        $organization->syncRoles(['organization']);

        return response()->json([
            'success' => true,
            'organization' => $this->formatOrganization($organization),
            'created' => true,
        ], 201);
    }

    public function updateOrganization(Request $request, User $user)
    {
        if ($user->role !== 'organization') {
            return response()->json(['error' => 'Organization not found.'], 404);
        }

        $data = $request->validate([
            'organization_name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        if (array_key_exists('organization_name', $data)) {
            $data['organization_name'] = trim($data['organization_name']);
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->fill($data);
        $user->role = 'organization';
        $user->save();

        Role::firstOrCreate(['name' => 'organization', 'guard_name' => 'sanctum']);
        $user->syncRoles(['organization']);

        return response()->json([
            'success' => true,
            'organization' => $this->formatOrganization($user),
        ]);
    }

    public function stats()
    {
        return response()->json([
            'success' => true,
            'stats' => [
                'totalUsers' => User::count(),
                'adminCount' => User::where('role', 'admin')->count(),
                'organizationCount' => User::where('role', 'organization')->count(),
                'userCount' => User::where('role', 'user')->count(),
            ],
        ]);
    }

    public function listCategories(Request $request)
    {
        $categories = Category::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'categories' => $categories]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            'description' => ['nullable', 'string'],
        ]);

        $category = Category::create($data + ['created_by' => $request->user()->id]);

        return response()->json(['success' => true, 'category' => $category], 201);
    }

    public function updateCategory(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            'description' => ['nullable', 'string'],
        ]);

        $category->update($data);
        return response()->json(['success' => true, 'category' => $category]);
    }

    public function deleteCategory(Category $category)
    {
        $category->delete();
        return response()->json(['success' => true]);
    }

    public function uploadCategoryImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file('image')->store('uploads/categories', 'public');
        return response()->json([
            'success' => true,
            'imageUrl' => '/storage/' . $path,
        ], 201);
    }

    public function listEvents(Request $request)
    {
        $query = Event::query()
            ->with(['organization:id,organization_name', 'category:id,name', 'area:id,name'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('category_id'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('location', 'like', "%{$term}%");
                });
            })
            ->when($request->query('organization_id'), fn ($q, $id) => $q->where('organization_id', $id))
            ->orderByDesc('created_at');

        $events = $query->paginate((int)$request->query('limit', 15));

        return response()->json([
            'success' => true,
            'events' => $events->items(),
            'pagination' => [
                'total' => $events->total(),
                'per_page' => $events->perPage(),
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
            ],
        ]);
    }

    public function storeEvent(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable'],
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.value_ids' => ['nullable', 'array'],
            'attributes.*.value_ids.*' => ['integer', 'exists:attribute_values,id'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'starting_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:starting_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
        ]);

        $event = Event::create([
            ...$data,
            'banner' => $this->toArrayField($data['banner'] ?? []),
            'attributes' => $this->normalizeAttributes($data['attributes'] ?? []),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'event' => $event], 201);
    }

    public function updateEvent(Request $request, Event $event)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable'],
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.value_ids' => ['nullable', 'array'],
            'attributes.*.value_ids.*' => ['integer', 'exists:attribute_values,id'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'starting_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:starting_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
        ]);

        if (array_key_exists('banner', $data)) {
            $data['banner'] = $this->toArrayField($data['banner']);
        }
        if (array_key_exists('attributes', $data)) {
            $data['attributes'] = $this->normalizeAttributes($data['attributes']);
        }

        $event->update($data);
        return response()->json(['success' => true, 'event' => $event]);
    }

    public function deleteEvent(Event $event)
    {
        $event->delete();
        return response()->json(['success' => true]);
    }

    public function uploadEventBanner(Request $request)
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
        $query = Offer::query()
            ->with(['organization:id,organization_name', 'category:id,name', 'area:id,name'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('details', 'like', "%{$term}%");
                });
            })
            ->when($request->query('organization_id'), fn ($q, $id) => $q->where('organization_id', $id))
            ->orderByDesc('created_at');

        $offers = $query->paginate((int)$request->query('limit', 15));

        return response()->json([
            'success' => true,
            'offers' => $offers->items(),
            'pagination' => [
                'total' => $offers->total(),
                'per_page' => $offers->perPage(),
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
            ],
        ]);
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
            'organization_id' => ['required', 'exists:users,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive', 'expired'])],
        ]);

        $offer = Offer::create([
            ...$data,
            'images' => $this->toArrayField($data['images'] ?? []),
            'videos' => $this->toArrayField($data['videos'] ?? []),
            'attributes' => $this->normalizeAttributes($data['attributes'] ?? []),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'offer' => $offer], 201);
    }

    public function updateOffer(Request $request, Offer $offer)
    {
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
            'organization_id' => ['nullable', 'exists:users,id'],
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
            $data['attributes'] = $this->normalizeAttributes($data['attributes']);
        }

        $offer->update($data + ['updated_by' => $request->user()->id]);
        return response()->json(['success' => true, 'offer' => $offer]);
    }

    public function deleteOffer(Offer $offer)
    {
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

    public function listHighlights()
    {
        $highlights = HighlightReel::query()
            ->with([
                'offer:id,name,details,cover,images,videos,thumbnail,organization_id',
                'offer.organization:id,organization_name',
                'event:id,name,description,banner,organization_id',
                'event.organization:id,organization_name',
            ])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'highlights' => $highlights,
        ]);
    }

    public function storeHighlight(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'galleries' => ['nullable'],
            'external_link' => ['nullable', 'string', 'max:500'],
            'offer_id' => ['nullable', 'exists:offers,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (!empty($data['offer_id']) && !empty($data['event_id'])) {
            return response()->json(['error' => 'Select either an offer or an event, not both.'], 422);
        }

        $title = trim((string)($data['title'] ?? ''));
        if (empty($data['offer_id']) && empty($data['event_id']) && $title === '') {
            return response()->json(['error' => 'Title is required for custom highlights.'], 422);
        }

        $data['galleries'] = $this->toArrayField($data['galleries'] ?? []);

        $highlight = HighlightReel::create($data + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'highlight' => $highlight], 201);
    }

    public function updateHighlight(Request $request, HighlightReel $highlight)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'galleries' => ['nullable'],
            'external_link' => ['nullable', 'string', 'max:500'],
            'offer_id' => ['nullable', 'exists:offers,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $nextOfferId = array_key_exists('offer_id', $data) ? $data['offer_id'] : $highlight->offer_id;
        $nextEventId = array_key_exists('event_id', $data) ? $data['event_id'] : $highlight->event_id;
        $nextTitle = array_key_exists('title', $data) ? trim((string)$data['title']) : $highlight->title;

        if (!empty($nextOfferId) && !empty($nextEventId)) {
            return response()->json(['error' => 'Select either an offer or an event, not both.'], 422);
        }

        if (empty($nextOfferId) && empty($nextEventId) && trim((string)$nextTitle) === '') {
            return response()->json(['error' => 'Title is required for custom highlights.'], 422);
        }

        if (array_key_exists('galleries', $data)) {
            $data['galleries'] = $this->toArrayField($data['galleries']);
        }

        $highlight->update($data + ['updated_by' => $request->user()->id]);

        return response()->json(['success' => true, 'highlight' => $highlight]);
    }

    public function deleteHighlight(HighlightReel $highlight)
    {
        $highlight->delete();
        return response()->json(['success' => true]);
    }

    public function uploadHighlightMedia(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/*,video/*', 'max:20480'],
        ]);

        $path = $request->file('file')->store('uploads/highlights', 'public');
        return response()->json([
            'success' => true,
            'fileUrl' => '/storage/' . $path,
            'mimeType' => $request->file('file')->getClientMimeType(),
        ], 201);
    }

    public function listSettings(Request $request)
    {
        $settings = SystemSetting::query()
            ->when($request->query('group'), fn ($q, $group) => $q->where('group', $group))
            ->orderBy('key')
            ->get();

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
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
            'metadata' => ['nullable'],
        ]);

        $setting = SystemSetting::create($data + [
            'created_by' => $request->user()->id,
            'value' => $this->normalizeJsonField($data['value'] ?? null),
            'metadata' => $this->normalizeJsonField($data['metadata'] ?? null),
        ]);

        return response()->json(['success' => true, 'setting' => $setting], 201);
    }

    public function updateSetting(Request $request, $setting)
    {
        $settingModel = SystemSetting::where('key', $setting)
            ->orWhere('id', $setting)
            ->first();

        $data = $request->validate([
            'key' => ['sometimes', 'string', 'max:150', Rule::unique('system_settings', 'key')->ignore($settingModel?->id)],
            'value' => ['nullable'],
            'type' => ['nullable', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'group' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable'],
        ]);

        if (array_key_exists('value', $data)) {
            $data['value'] = $this->normalizeJsonField($data['value']);
        }
        if (array_key_exists('metadata', $data)) {
            $data['metadata'] = $this->normalizeJsonField($data['metadata']);
        }

        if (!$settingModel) {
            $settingModel = SystemSetting::create($data + [
                'key' => $data['key'] ?? $setting,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        } else {
            $settingModel->update($data + ['updated_by' => $request->user()->id]);
        }

        return response()->json(['success' => true, 'setting' => $settingModel]);
    }

    public function deleteSetting(SystemSetting $setting)
    {
        $setting->delete();
        return response()->json(['success' => true]);
    }

    public function uploadSettingImage(Request $request)
    {
        $field = $request->hasFile('image') ? 'image' : 'file';

        $request->validate([
            $field => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file($field)->store('uploads/system', 'public');
        return response()->json([
            'success' => true,
            'fileUrl' => '/storage/' . $path,
            'imageUrl' => '/storage/' . $path,
        ], 201);
    }

    public function listContentBlocks()
    {
        $blocks = ContentBlock::query()
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'blocks' => $blocks,
        ]);
    }

    public function showContentBlock(ContentBlock $contentBlock)
    {
        $contentBlock->load('items');

        return response()->json([
            'success' => true,
            'block' => $contentBlock,
        ]);
    }

    public function storeContentBlock(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $block = ContentBlock::create($data + [
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'block' => $block], 201);
    }

    public function updateContentBlock(Request $request, ContentBlock $contentBlock)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $contentBlock->update($data + [
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'block' => $contentBlock]);
    }

    public function updateContentBlockItems(Request $request, ContentBlock $contentBlock)
    {
        $data = $request->validate([
            'items' => ['nullable', 'array', 'max:10'],
            'items.*.type' => ['required', Rule::in(['category', 'event', 'offer'])],
            'items.*.target_id' => ['nullable', 'integer'],
            'items.*.title' => ['nullable', 'string', 'max:200'],
            'items.*.subtitle' => ['nullable', 'string', 'max:255'],
            'items.*.image' => ['nullable', 'string', 'max:500'],
            'items.*.external_link' => ['nullable', 'string', 'max:500'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        $items = $data['items'] ?? [];

        DB::transaction(function () use ($contentBlock, $items) {
            $contentBlock->items()->delete();

            if (count($items) === 0) {
                return;
            }

            $payload = [];
            foreach ($items as $index => $item) {
                $payload[] = [
                    'type' => $item['type'],
                    'target_id' => $item['target_id'] ?? null,
                    'title' => $item['title'] ?? null,
                    'subtitle' => $item['subtitle'] ?? null,
                    'image' => $item['image'] ?? null,
                    'external_link' => $item['external_link'] ?? null,
                    'sort_order' => $item['sort_order'] ?? $index,
                ];
            }

            $contentBlock->items()->createMany($payload);
        });

        $contentBlock->update(['updated_by' => $request->user()->id]);
        $contentBlock->load('items');

        return response()->json([
            'success' => true,
            'block' => $contentBlock,
        ]);
    }

    public function deleteContentBlock(ContentBlock $contentBlock)
    {
        $contentBlock->delete();
        return response()->json(['success' => true]);
    }

    public function listAttributes(Request $request)
    {
        $query = Attribute::query()
            ->with(['values' => fn ($q) => $q->orderBy('id')]);

        if ($request->boolean('with_categories')) {
            $query->with(['categories:id,name']);
        }

        if ($request->query('search')) {
            $term = $request->query('search');
            $query->where('name', 'like', "%{$term}%");
        }

        if ($request->query('type')) {
            $query->where('type', $request->query('type'));
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

    public function storeAttribute(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['event', 'offer'])],
            'values' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        return DB::transaction(function () use ($data) {
            $attribute = Attribute::create([
                'name' => $data['name'],
                'type' => $data['type'],
            ]);

            $values = $this->normalizeAttributeValues($data['values'] ?? []);
            if (!empty($values)) {
                $attribute->values()->createMany($values);
            }

            if (!empty($data['category_ids'])) {
                $attribute->categories()->sync($data['category_ids']);
            }

            $attribute->load(['values' => fn ($q) => $q->orderBy('id')]);

            return response()->json([
                'success' => true,
                'attribute' => $attribute,
            ], 201);
        });
    }

    public function updateAttribute(Request $request, Attribute $attribute)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['event', 'offer'])],
            'values' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        return DB::transaction(function () use ($data, $attribute) {
            if (array_key_exists('name', $data)) {
                $attribute->name = $data['name'];
            }
            if (array_key_exists('type', $data)) {
                $attribute->type = $data['type'];
            }
            $attribute->save();

            if (array_key_exists('values', $data)) {
                $attribute->values()->delete();
                $values = $this->normalizeAttributeValues($data['values'] ?? []);
                if (!empty($values)) {
                    $attribute->values()->createMany($values);
                }
            }

            if (array_key_exists('category_ids', $data)) {
                $attribute->categories()->sync($data['category_ids'] ?? []);
            }

            $attribute->load(['values' => fn ($q) => $q->orderBy('id')]);

            return response()->json([
                'success' => true,
                'attribute' => $attribute,
            ]);
        });
    }

    public function deleteAttribute(Attribute $attribute)
    {
        $attribute->delete();
        return response()->json(['success' => true]);
    }

    private function findExistingOrganization(string $organizationName): ?User
    {
        $normalized = strtolower(trim($organizationName));
        if ($normalized === '') {
            return null;
        }

        return User::query()
            ->where('role', 'organization')
            ->where(function ($query) use ($normalized) {
                $query->whereRaw('LOWER(organization_name) = ?', [$normalized])
                    ->orWhereRaw('LOWER(username) = ?', [$normalized]);
            })
            ->first();
    }

    private function generateUniqueUsername(string $base): string
    {
        $candidateBase = trim($base) !== '' ? trim($base) : 'organization';
        $candidateBase = substr($candidateBase, 0, 50);
        $candidate = $candidateBase;
        $counter = 1;

        while (User::where('username', $candidate)->exists()) {
            $suffix = (string)$counter;
            $trimLength = max(0, 50 - strlen($suffix));
            $candidate = substr($candidateBase, 0, $trimLength) . $suffix;
            $counter++;
        }

        return $candidate;
    }

    private function generateUniqueOrganizationEmail(string $organizationName): string
    {
        $slug = Str::slug($organizationName);
        $slug = $slug !== '' ? $slug : 'organization';

        do {
            $email = 'org-' . $slug . '-' . Str::random(6) . '@auto.local';
        } while (User::where('email', $email)->exists());

        return $email;
    }

    private function toArrayField($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }
        if (is_string($value)) {
            return array_values(
                array_filter(
                    array_map('trim', preg_split('/\r?\n|,/', $value))
                )
            );
        }

        return [];
    }

    private function normalizeJsonField($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded ?? $value;
        }

        return $value;
    }

    private function normalizeAttributeValues($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];
        foreach ($values as $value) {
            if (is_string($value)) {
                $label = trim($value);
                $color = null;
            } elseif (is_array($value)) {
                $label = trim((string)($value['value'] ?? ''));
                $color = $value['color_code'] ?? null;
            } else {
                continue;
            }

            if ($label === '') {
                continue;
            }

            $normalized[] = [
                'value' => $label,
                'color_code' => $color,
            ];
        }

        return $normalized;
    }

    private function normalizeAttributes($attributes): array
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

    private function formatOrganization(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'organization_name' => $user->organization_name,
            'organizationName' => $user->organization_name,
            'business_type' => $user->business_type,
            'businessType' => $user->business_type,
            'phone' => $user->phone,
            'created_at' => $user->created_at,
            'createdAt' => $user->created_at,
        ];
    }
}
