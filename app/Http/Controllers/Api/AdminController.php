<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Offer;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            ->get(['id', 'username', 'email', 'organization_name']);

        return response()->json(['success' => true, 'organizations' => $organizations]);
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
            'discount_type' => ['nullable', Rule::in(['percentage', 'flat', 'bogo', 'custom'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
            'cover' => ['nullable', 'string', 'max:500'],
            'images' => ['nullable'],
            'videos' => ['nullable'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'organization_id' => ['required', 'exists:users,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'offer_type' => [
                'nullable',
                Rule::in([
                    'general',
                    'category',
                    'event',
                    'special',
                    'bogo',
                    'discount',
                    'combo',
                    'happy_hour',
                    'lunch_hour',
                    'late_night',
                    'complimentary',
                ]),
            ],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive', 'expired'])],
        ]);

        $offer = Offer::create([
            ...$data,
            'images' => $this->toArrayField($data['images'] ?? []),
            'videos' => $this->toArrayField($data['videos'] ?? []),
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
            'discount_type' => ['nullable', Rule::in(['percentage', 'flat', 'bogo', 'custom'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
            'cover' => ['nullable', 'string', 'max:500'],
            'images' => ['nullable'],
            'videos' => ['nullable'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'offer_type' => [
                'nullable',
                Rule::in([
                    'general',
                    'category',
                    'event',
                    'special',
                    'bogo',
                    'discount',
                    'combo',
                    'happy_hour',
                    'lunch_hour',
                    'late_night',
                    'complimentary',
                ]),
            ],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive', 'expired'])],
        ]);

        if (array_key_exists('images', $data)) {
            $data['images'] = $this->toArrayField($data['images']);
        }
        if (array_key_exists('videos', $data)) {
            $data['videos'] = $this->toArrayField($data['videos']);
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

    private function normalizeJsonField($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded ?? $value;
        }

        return $value;
    }
}
