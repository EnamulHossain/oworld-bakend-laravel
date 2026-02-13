<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\ContentBlock;
use App\Models\Event;
use App\Models\HighlightReel;
use App\Models\HighlightReelItem;
use App\Models\Offer;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function listAreas(Request $request)
    {
        $areas = Area::query()
            ->when($request->query('search'), function ($q, $term) {
                $q->where('name', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'areas' => $areas]);
    }

    public function storeArea(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:areas,name'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['error' => 'Area name is required.'], 422);
        }

        $area = Area::create(['name' => $name]);

        return response()->json(['success' => true, 'area' => $area], 201);
    }

    public function updateArea(Request $request, Area $area)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('areas', 'name')->ignore($area->id)],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['error' => 'Area name is required.'], 422);
        }

        $area->update(['name' => $name]);

        return response()->json(['success' => true, 'area' => $area]);
    }

    public function deleteArea(Area $area)
    {
        $area->delete();

        return response()->json(['success' => true]);
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
            ->orderBy('sort_order')
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
            'thumbnail' => ['nullable', 'string', 'max:500'],
            'gallery_sort_order' => ['nullable'],
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.value_ids' => ['nullable', 'array'],
            'attributes.*.value_ids.*' => ['integer', 'exists:attribute_values,id'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'starting_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['required', 'date', 'after_or_equal:starting_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'serial' => ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('serial', $data) && !array_key_exists('sort_order', $data)) {
            $data['sort_order'] = $data['serial'];
        }

        $data = $this->normalizeDateAndTimeFields($data, 'starting_date');

        $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order'] ?? []);
        if (!is_array($gallerySortOrder)) {
            $gallerySortOrder = [];
        }

        $event = Event::create([
            ...$data,
            'banner' => $this->toArrayField($data['banner'] ?? []),
            'gallery_sort_order' => $gallerySortOrder,
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
            'thumbnail' => ['nullable', 'string', 'max:500'],
            'gallery_sort_order' => ['nullable'],
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.value_ids' => ['nullable', 'array'],
            'attributes.*.value_ids.*' => ['integer', 'exists:attribute_values,id'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'starting_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:starting_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'serial' => ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('serial', $data) && !array_key_exists('sort_order', $data)) {
            $data['sort_order'] = $data['serial'];
        }

        $data = $this->normalizeDateAndTimeFields($data, 'starting_date');

        if (array_key_exists('banner', $data)) {
            $data['banner'] = $this->toArrayField($data['banner']);
        }
        if (array_key_exists('gallery_sort_order', $data)) {
            $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order']);
            $data['gallery_sort_order'] = is_array($gallerySortOrder) ? $gallerySortOrder : [];
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

    public function uploadEventThumbnail(Request $request)
    {
        $request->validate([
            'thumbnail' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ]);

        $path = $request->file('thumbnail')->store('uploads/events', 'public');
        return response()->json([
            'success' => true,
            'thumbnailUrl' => '/storage/' . $path,
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
            ->orderBy('sort_order')
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
        try {
            Log::info('API storeOffer called', [
                'user_id' => optional($request->user())->id,
                'payload' => $request->except(['thumbnail', 'cover', 'images', 'videos']),
                'has_images' => $request->has('images') || $request->hasFile('images'),
                'has_videos' => $request->has('videos') || $request->hasFile('videos'),
            ]);

            $data = $request->validate([
                'name' => ['required', 'string', 'max:200'],
                'details' => ['nullable', 'string'],
                'start_date' => ['required', 'date'],
                'start_time' => ['nullable', 'date_format:H:i'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'end_time' => ['nullable', 'date_format:H:i'],
                'address' => ['nullable', 'string', 'max:255'],
                'phone_number' => ['nullable', 'string', 'max:50'],
                'facebook_url' => ['nullable', 'string', 'max:500'],
                'instagram_url' => ['nullable', 'string', 'max:500'],
                'website_url' => ['nullable', 'string', 'max:500'],
                'google_map_url' => ['nullable', 'string', 'max:500'],
                'discount_type' => ['nullable', Rule::in(['percentage', 'flat', 'bogo', 'custom'])],
                'discount_value' => ['nullable', 'numeric', 'min:0'],
                'thumbnail' => ['nullable', 'string', 'max:500'],
                'cover' => ['nullable', 'string', 'max:500'],
                'images' => ['nullable'],
                'gallery_sort_order' => ['nullable'],
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
                'sort_order' => ['nullable', 'integer', 'min:0'],
                'serial' => ['nullable', 'integer', 'min:0'],
                'offer_type' => ['nullable', Rule::in(['regular', 'exclusive'])],
            ]);

            if (array_key_exists('serial', $data) && !array_key_exists('sort_order', $data)) {
                $data['sort_order'] = $data['serial'];
            }

            $data = $this->normalizeDateAndTimeFields($data, 'start_date');

            $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order'] ?? []);
            if (!is_array($gallerySortOrder)) {
                $gallerySortOrder = [];
            }

            $offer = DB::transaction(function () use ($data, $request, $gallerySortOrder) {
                $requestedOrder = $data['sort_order'] ?? null;
                $nextOrder = (int) Offer::max('sort_order') + 1;
                $finalOrder = $requestedOrder === null ? $nextOrder : max(0, (int) $requestedOrder);

                if ($requestedOrder !== null) {
                    Offer::where('sort_order', '>=', $finalOrder)->increment('sort_order');
                }

                return Offer::create([
                    ...$data,
                    'sort_order' => $finalOrder,
                    'images' => $this->toArrayField($data['images'] ?? []),
                    'gallery_sort_order' => $gallerySortOrder,
                    'videos' => $this->toArrayField($data['videos'] ?? []),
                    'attributes' => $this->normalizeAttributes($data['attributes'] ?? []),
                    'created_by' => $request->user()->id,
                ]);
            });

            Log::info('API storeOffer success', ['offer_id' => $offer->id ?? null]);

            return response()->json(['success' => true, 'offer' => $offer], 201);
        } catch (\Throwable $e) {
            Log::error('API storeOffer failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    public function updateOffer(Request $request, Offer $offer)
    {
        try {
            Log::info('API updateOffer called', [
                'user_id' => optional($request->user())->id,
                'offer_id' => $offer->id,
                'payload' => $request->except(['thumbnail', 'cover', 'images', 'videos']),
                'has_images' => $request->has('images') || $request->hasFile('images'),
                'has_videos' => $request->has('videos') || $request->hasFile('videos'),
            ]);

            $data = $request->validate([
                'name' => ['sometimes', 'string', 'max:200'],
                'details' => ['nullable', 'string'],
                'start_date' => ['nullable', 'date'],
                'start_time' => ['nullable', 'date_format:H:i'],
                'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                'end_time' => ['nullable', 'date_format:H:i'],
                'address' => ['nullable', 'string', 'max:255'],
                'phone_number' => ['nullable', 'string', 'max:50'],
                'facebook_url' => ['nullable', 'string', 'max:500'],
                'instagram_url' => ['nullable', 'string', 'max:500'],
                'website_url' => ['nullable', 'string', 'max:500'],
                'google_map_url' => ['nullable', 'string', 'max:500'],
                'discount_type' => ['nullable', Rule::in(['percentage', 'flat', 'bogo', 'custom'])],
                'discount_value' => ['nullable', 'numeric', 'min:0'],
                'thumbnail' => ['nullable', 'string', 'max:500'],
                'cover' => ['nullable', 'string', 'max:500'],
                'images' => ['nullable'],
                'gallery_sort_order' => ['nullable'],
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
                'sort_order' => ['nullable', 'integer', 'min:0'],
                'serial' => ['nullable', 'integer', 'min:0'],
                'offer_type' => ['nullable', Rule::in(['regular', 'exclusive'])],
            ]);

            if (array_key_exists('serial', $data) && !array_key_exists('sort_order', $data)) {
                $data['sort_order'] = $data['serial'];
            }

            $data = $this->normalizeDateAndTimeFields($data, 'start_date');

            if (array_key_exists('images', $data)) {
                $data['images'] = $this->toArrayField($data['images']);
            }
            if (array_key_exists('gallery_sort_order', $data)) {
                $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order']);
                $data['gallery_sort_order'] = is_array($gallerySortOrder) ? $gallerySortOrder : [];
            }
            if (array_key_exists('videos', $data)) {
                $data['videos'] = $this->toArrayField($data['videos']);
            }
            if (array_key_exists('attributes', $data)) {
                $data['attributes'] = $this->normalizeAttributes($data['attributes']);
            }

            DB::transaction(function () use ($data, $offer, $request) {
                if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
                    $newOrder = max(0, (int) $data['sort_order']);
                    $currentOrder = (int) $offer->sort_order;

                    if ($newOrder !== $currentOrder) {
                        if ($newOrder < $currentOrder) {
                            Offer::where('id', '!=', $offer->id)
                                ->whereBetween('sort_order', [$newOrder, $currentOrder - 1])
                                ->increment('sort_order');
                        } else {
                            Offer::where('id', '!=', $offer->id)
                                ->whereBetween('sort_order', [$currentOrder + 1, $newOrder])
                                ->decrement('sort_order');
                        }
                    }

                    $data['sort_order'] = $newOrder;
                }

                $offer->update($data + ['updated_by' => $request->user()->id]);
            });
            Log::info('API updateOffer success', ['offer_id' => $offer->id]);
            return response()->json(['success' => true, 'offer' => $offer->fresh()]);
        } catch (\Throwable $e) {
            Log::error('API updateOffer failed', [
                'offer_id' => $offer->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
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
            ->with(['items' => function ($query) {
                $query->orderBy('sort_order')->orderBy('id');
            }])
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
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'external_link' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.title' => ['nullable', 'string', 'max:200'],
            'items.*.subtitle' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.offer_id' => ['nullable', 'exists:offers,id'],
            'items.*.event_id' => ['nullable', 'exists:events,id'],
            'items.*.organization_id' => ['nullable', 'exists:users,id'],
            'items.*.image' => ['nullable', 'string', 'max:255'],
            'items.*.external_link' => ['nullable', 'string', 'max:500'],
            'items.*.sort_order' => ['nullable', 'integer'],
            'items.*.is_active' => ['nullable', 'boolean'],
        ]);

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            return response()->json(['error' => 'Title is required for custom highlights.'], 422);
        }

        $items = $data['items'] ?? [];
        unset($data['items']);

        $highlight = DB::transaction(function () use ($data, $items, $request) {
            $nextOrder = (int)($data['sort_order'] ?? 0);
            $nextOrder = $nextOrder > 0 ? $nextOrder : ((int)HighlightReel::max('sort_order') + 1);
            $data['sort_order'] = $nextOrder;

            HighlightReel::where('sort_order', '>=', $nextOrder)->increment('sort_order');

            $highlight = HighlightReel::create($data + [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $itemsData = $this->normalizeHighlightItems($items, $request->user()->id);
            if (!empty($itemsData)) {
                $highlight->items()->createMany($itemsData);
            }

            return $highlight->load('items');
        });

        return response()->json(['success' => true, 'highlight' => $highlight], 201);
    }

    public function updateHighlight(Request $request, HighlightReel $highlight)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'external_link' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.title' => ['nullable', 'string', 'max:200'],
            'items.*.subtitle' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.offer_id' => ['nullable', 'exists:offers,id'],
            'items.*.event_id' => ['nullable', 'exists:events,id'],
            'items.*.organization_id' => ['nullable', 'exists:users,id'],
            'items.*.image' => ['nullable', 'string', 'max:255'],
            'items.*.external_link' => ['nullable', 'string', 'max:500'],
            'items.*.sort_order' => ['nullable', 'integer'],
            'items.*.is_active' => ['nullable', 'boolean'],
        ]);

        $nextTitle = array_key_exists('title', $data) ? trim((string)$data['title']) : $highlight->title;

        if (trim((string)$nextTitle) === '') {
            return response()->json(['error' => 'Title is required for custom highlights.'], 422);
        }

        $itemsProvided = array_key_exists('items', $data);
        $items = $data['items'] ?? [];
        unset($data['items']);

        $highlight = DB::transaction(function () use ($highlight, $data, $itemsProvided, $items, $request) {
            if (array_key_exists('sort_order', $data)) {
                $currentOrder = (int)$highlight->sort_order;
                $nextOrder = (int)$data['sort_order'];
                $nextOrder = $nextOrder > 0 ? $nextOrder : $currentOrder;

                if ($nextOrder !== $currentOrder) {
                    if ($nextOrder > $currentOrder) {
                        HighlightReel::where('id', '!=', $highlight->id)
                            ->whereBetween('sort_order', [$currentOrder + 1, $nextOrder])
                            ->decrement('sort_order');
                    } else {
                        HighlightReel::where('id', '!=', $highlight->id)
                            ->whereBetween('sort_order', [$nextOrder, $currentOrder - 1])
                            ->increment('sort_order');
                    }
                    $data['sort_order'] = $nextOrder;
                }
            }

            $highlight->update($data + ['updated_by' => $request->user()->id]);

            if ($itemsProvided) {
                $itemsData = $this->normalizeHighlightItems($items, $request->user()->id);
                $highlight->items()->delete();
                if (!empty($itemsData)) {
                    $highlight->items()->createMany($itemsData);
                }
            }

            return $highlight->load('items');
        });

        return response()->json(['success' => true, 'highlight' => $highlight]);
    }

    public function deleteHighlight(HighlightReel $highlight)
    {
        $highlight->delete();
        return response()->json(['success' => true]);
    }

    private function normalizeHighlightItems(array $items, int $userId): array
    {
        $normalized = [];

        foreach ($items as $index => $item) {
            $offerId = $item['offer_id'] ?? null;
            $eventId = $item['event_id'] ?? null;
            $organizationId = $item['organization_id'] ?? null;

            $links = array_filter([$offerId, $eventId, $organizationId], fn ($value) => !empty($value));
            if (count($links) > 1) {
                throw ValidationException::withMessages([
                    "items.$index" => 'Only one of offer_id, event_id, or organization_id is allowed.',
                ]);
            }

            $title = trim((string)($item['title'] ?? ''));
            if (count($links) === 0 && $title === '') {
                throw ValidationException::withMessages([
                    "items.$index.title" => 'Title is required when no offer, event, or organization is selected.',
                ]);
            }

            $normalized[] = [
                'title' => $item['title'] ?? null,
                'subtitle' => $item['subtitle'] ?? null,
                'description' => $item['description'] ?? null,
                'offer_id' => $offerId,
                'event_id' => $eventId,
                'organization_id' => $organizationId,
                'image' => $item['image'] ?? null,
                'external_link' => $item['external_link'] ?? null,
                'sort_order' => $item['sort_order'] ?? 0,
                'is_active' => $item['is_active'] ?? true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ];
        }

        return $normalized;
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

        if ($setting->key === 'content_home_slider') {
            $setting->value = $this->normalizeHomeSlider($setting->value);
            $setting->save();
        }

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

        if ($settingModel->key === 'content_home_slider') {
            $settingModel->value = $this->normalizeHomeSlider($settingModel->value);
            $settingModel->save();
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

    public function uploadContentBlockThumbnail(Request $request)
    {
        $field = $request->hasFile('thumbnail') ? 'thumbnail' : 'image';

        $request->validate([
            $field => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file($field)->store('uploads/content-blocks', 'public');
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
            'is_featured' => ['nullable', 'boolean'],
            'teared_block' => ['nullable', 'boolean'],
            'thumbnail_image' => ['required', 'string', 'max:255'],
            'featured_sort_order' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (!empty($data['is_featured'])) {
            if (empty($data['featured_sort_order'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Featured sort order is required for featured blocks.',
                ], 422);
            }
            $featuredCount = ContentBlock::where('is_featured', true)->count();
            if ($featuredCount >= 6) {
                return response()->json([
                    'success' => false,
                    'error' => 'You can only have up to 6 featured blocks.',
                ], 422);
            }
        } else {
            $data['featured_sort_order'] = null;
        }

        $block = DB::transaction(function () use ($data, $request) {
            $maxOrder = (int) ContentBlock::max('sort_order');
            $requestedOrder = (int) ($data['sort_order'] ?? 0);
            if ($requestedOrder < 1) {
                $requestedOrder = $maxOrder + 1;
            } elseif ($requestedOrder > $maxOrder + 1) {
                $requestedOrder = $maxOrder + 1;
            }

            ContentBlock::where('sort_order', '>=', $requestedOrder)->increment('sort_order');

            return ContentBlock::create($data + [
                'sort_order' => $requestedOrder,
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json(['success' => true, 'block' => $block], 201);
    }

    public function updateContentBlock(Request $request, ContentBlock $contentBlock)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'teared_block' => ['nullable', 'boolean'],
            'thumbnail_image' => ['nullable', 'string', 'max:255'],
            'featured_sort_order' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (array_key_exists('is_featured', $data) && $data['is_featured'] && !$contentBlock->is_featured) {
            if (empty($data['featured_sort_order'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Featured sort order is required for featured blocks.',
                ], 422);
            }
            $featuredCount = ContentBlock::where('is_featured', true)->count();
            if ($featuredCount >= 6) {
                return response()->json([
                    'success' => false,
                    'error' => 'You can only have up to 6 featured blocks.',
                ], 422);
            }
        }

        $contentBlock = DB::transaction(function () use ($contentBlock, $data, $request) {
            $payload = $data + ['updated_by' => $request->user()->id];

            if (array_key_exists('is_featured', $data) && !$data['is_featured']) {
                $payload['featured_sort_order'] = null;
            }

            if (array_key_exists('sort_order', $data)) {
                $currentOrder = (int) ($contentBlock->sort_order ?? 0);
                $maxOrder = (int) ContentBlock::where('id', '!=', $contentBlock->id)->max('sort_order');
                $targetOrder = (int) ($data['sort_order'] ?? 0);

                if ($targetOrder < 1) {
                    $targetOrder = 1;
                } elseif ($targetOrder > $maxOrder + 1) {
                    $targetOrder = $maxOrder + 1;
                }

                if ($targetOrder !== $currentOrder) {
                    if ($targetOrder < $currentOrder) {
                        ContentBlock::where('id', '!=', $contentBlock->id)
                            ->whereBetween('sort_order', [$targetOrder, $currentOrder - 1])
                            ->increment('sort_order');
                    } else {
                        ContentBlock::where('id', '!=', $contentBlock->id)
                            ->whereBetween('sort_order', [$currentOrder + 1, $targetOrder])
                            ->decrement('sort_order');
                    }
                }

                $payload['sort_order'] = $targetOrder;
            }

            $contentBlock->update($payload);
            return $contentBlock;
        });

        return response()->json(['success' => true, 'block' => $contentBlock]);
    }

    public function updateContentBlockItems(Request $request, ContentBlock $contentBlock)
    {
        $data = $request->validate([
            'items' => ['nullable', 'array'],
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
            ->orderBy('sort_order')
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
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'values' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        return DB::transaction(function () use ($data) {
            $attribute = Attribute::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'sort_order' => (int) ($data['sort_order'] ?? 0),
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
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
            if (array_key_exists('sort_order', $data)) {
                $attribute->sort_order = (int) ($data['sort_order'] ?? 0);
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

    private function normalizeDateAndTimeFields(array $data, string $dateFieldPrefix): array
    {
        $startDateField = $dateFieldPrefix;
        $startTimeField = 'start_time';
        $endDateField = 'end_date';
        $endTimeField = 'end_time';

        if (array_key_exists($startDateField, $data) && $data[$startDateField]) {
            [$normalizedDate, $timeFromDate] = $this->extractDateAndTime((string) $data[$startDateField]);
            $data[$startDateField] = $normalizedDate;
            if (empty($data[$startTimeField]) && $timeFromDate !== null) {
                $data[$startTimeField] = $timeFromDate;
            }
        }

        if (array_key_exists($endDateField, $data) && $data[$endDateField]) {
            [$normalizedDate, $timeFromDate] = $this->extractDateAndTime((string) $data[$endDateField]);
            $data[$endDateField] = $normalizedDate;
            if (empty($data[$endTimeField]) && $timeFromDate !== null) {
                $data[$endTimeField] = $timeFromDate;
            }
        }

        if (array_key_exists($startTimeField, $data)) {
            $data[$startTimeField] = $this->normalizeTimeValue($data[$startTimeField]);
        }
        if (array_key_exists($endTimeField, $data)) {
            $data[$endTimeField] = $this->normalizeTimeValue($data[$endTimeField]);
        }

        return $data;
    }

    private function extractDateAndTime(string $value): array
    {
        $raw = trim($value);
        if ($raw === '') {
            return [null, null];
        }

        $parsed = Carbon::parse($raw);
        $date = $parsed->toDateString();
        $hasExplicitTime = (bool) preg_match('/\d{1,2}:\d{2}/', $raw);
        if (!$hasExplicitTime) {
            return [$date, null];
        }

        $time = $parsed->format('H:i:s');
        if (in_array($time, ['00:00:00', '23:59:59'], true)) {
            return [$date, null];
        }

        return [$date, $time];
    }

    private function normalizeTimeValue($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $parsed = Carbon::parse($raw)->format('H:i:s');
        if (in_array($parsed, ['00:00:00', '23:59:59'], true)) {
            return null;
        }
        return $parsed;
    }

    private function normalizeHomeSlider($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $slides = [];
        foreach ($value as $idx => $slide) {
            $slide = is_array($slide) ? $slide : [];
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
            $slides[] = $slide;
        }

        return collect($slides)
            ->sortBy(fn ($slide, $index) => $slide['sort_order'] ?? $index)
            ->values()
            ->all();
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
