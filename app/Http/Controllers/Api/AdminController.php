<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AnalyticsEvent;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\ContentBlock;
use App\Models\Coupon;
use App\Models\CouponDetail;
use App\Models\CouponTier;
use App\Models\Event;
use App\Models\Facility;
use App\Models\HighlightReel;
use App\Models\HighlightReelItem;
use App\Models\Offer;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\StorePost;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class AdminController extends Controller
{
    private const MEDIA_UPLOAD_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov', 'm4v', 'ogg', 'avi', 'mkv'];
    private const MEDIA_UPLOAD_MAX_BYTES = 20 * 1024 * 1024;

    public function users(Request $request)
    {
        $columns = ['id', 'username', 'email', 'role', 'organization_name', 'created_at'];
        foreach (['full_name', 'first_name', 'last_name', 'phone', 'dob', 'gender', 'signup_source', 'google_id'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $columns[] = $column;
            }
        }
        if (Schema::hasColumn('users', 'status')) {
            $columns[] = 'status';
        }

        $users = User::query()
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('username', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->get($columns);

        return response()->json(['success' => true, 'users' => $users]);
    }

    public function updateUserRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['user', 'organization', 'admin', 'superAdmin'])],
        ]);

        if ($data['role'] === 'superAdmin' && !$this->isSuperAdmin($request)) {
            return response()->json(['error' => 'Only super admin can assign super admin role.'], 403);
        }

        if ($user->role === 'superAdmin' && !$this->isSuperAdmin($request)) {
            return response()->json(['error' => 'Only super admin can modify this user.'], 403);
        }

        $user->update(['role' => $data['role']]);
        $user->syncRoles([$data['role']]);

        return response()->json(['success' => true, 'user' => $user]);
    }

    public function admins(Request $request)
    {
        $columns = ['id', 'username', 'email', 'role', 'created_at'];
        if (Schema::hasColumn('users', 'status')) {
            $columns[] = 'status';
        }

        $admins = User::query()
            ->whereIn('role', ['admin', 'superAdmin'])
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('username', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->get($columns);

        return response()->json(['success' => true, 'admins' => $admins]);
    }

    public function assignAdmin(Request $request, User $user)
    {
        if (!$this->isSuperAdmin($request)) {
            return response()->json(['error' => 'Only super admin can assign admin users.'], 403);
        }

        $result = $this->promoteUserToAdmin($user);
        if ($result !== true) {
            return response()->json(['error' => $result], 422);
        }

        return response()->json(['success' => true, 'user' => $user]);
    }

    public function assignAdminsBulk(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return response()->json(['error' => 'Only super admin can assign admin users.'], 403);
        }

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $users = User::query()
            ->whereIn('id', $data['user_ids'])
            ->get();

        $updated = [];
        $failed = [];

        foreach ($users as $user) {
            $result = $this->promoteUserToAdmin($user);
            if ($result === true) {
                $updated[] = $user->id;
                continue;
            }

            $failed[] = [
                'user_id' => $user->id,
                'message' => $result,
            ];
        }

        return response()->json([
            'success' => count($failed) === 0,
            'updated_user_ids' => $updated,
            'failed' => $failed,
        ]);
    }

    public function updateAdminStatus(Request $request, User $user)
    {
        if (!$this->isSuperAdmin($request)) {
            return response()->json(['error' => 'Only super admin can update admin status.'], 403);
        }

        if (!in_array($user->role, ['admin', 'superAdmin'], true)) {
            return response()->json(['error' => 'Target user is not an admin.'], 422);
        }

        if ($user->role === 'superAdmin') {
            return response()->json(['error' => 'Super admin status cannot be changed.'], 422);
        }

        if (!Schema::hasColumn('users', 'status')) {
            return response()->json(['error' => 'User status column is missing.'], 422);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $user->update(['status' => $data['status']]);

        return response()->json(['success' => true, 'user' => $user]);
    }

    public function deleteUser(User $user)
    {
        $user->delete();
        return response()->json(['success' => true]);
    }

    public function organizations(Request $request)
    {
        $query = User::query()
            ->where('role', 'organization')
            ->when($request->boolean('top_level'), function ($q) {
                $q->whereNull('parent_org_id');
            })
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('username', 'like', "%{$term}%")
                        ->orWhere('organization_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            });

        $sortColumns = ['organization_name', 'phone', 'email', 'username', 'created_at'];
        $sortBy = $request->query('sort_by');
        if (in_array($sortBy, $sortColumns, true)) {
            $query->orderBy($sortBy, $request->query('sort_dir') === 'asc' ? 'asc' : 'desc')
                ->orderByDesc('id');
        } else {
            $query->orderBy('organization_name')
                ->orderBy('id');
        }

        $columns = [
                'id',
                'parent_org_id',
                'username',
                'email',
                'organization_name',
                'business_type',
                'is_verified',
                'categories',
                'subcategory_id',
                'phone',
                'whatsapp',
                'address',
                'about',
                'avatar',
                'profile_banner',
                'opening_hours',
                'business_hours',
                'facebook_url',
                'instagram_url',
                'website_url',
                'google_map_url',
                'payment_methods',
                'facilities',
                'highlights',
                'catalog_sections',
                'catalog_items',
                'created_at',
        ];

        if (!$request->hasAny(['page', 'limit'])) {
            $organizations = $query->get($columns);

            return response()->json([
                'success' => true,
                'organizations' => $organizations->map(fn (User $org) => $this->formatOrganization($org)),
            ]);
        }

        $organizations = $query->paginate(
            min(max((int) $request->query('limit', 10), 1), 100),
            $columns
        );

        return response()->json([
            'success' => true,
            'organizations' => collect($organizations->items())->map(fn (User $org) => $this->formatOrganization($org)),
            'pagination' => [
                'current_page' => $organizations->currentPage(),
                'last_page' => $organizations->lastPage(),
                'per_page' => $organizations->perPage(),
                'total' => $organizations->total(),
            ],
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
            'public_subcategory' => ['nullable', 'string', 'max:100'],
            'public_tag' => ['nullable', 'string', 'max:50'],
            'is_verified' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'parent_org_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'organization')
                    ->whereNull('parent_org_id')),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string'],
            'store_tags' => ['nullable', 'array', 'max:30'],
            'store_tags.*' => ['string', 'max:100'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'profile_banner' => ['nullable', 'string', 'max:500'],
            'interior_media' => ['nullable', 'array', 'max:20'],
            'interior_media.*.url' => ['required', 'string', 'max:500'],
            'interior_media.*.type' => ['required', Rule::in(['image', 'video'])],
            'opening_hours' => ['nullable', 'string', 'max:120'],
            'business_hours' => ['nullable', 'array'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'payment_methods' => ['nullable', 'array'],
            'facilities' => ['nullable', 'array'],
            'highlights' => ['nullable', 'array'],
            'catalog_sections' => ['nullable', 'array'],
            'catalog_items' => ['nullable', 'array'],
        ]);

        $organizationName = trim($data['organization_name']);
        $this->validateOrganizationSubcategory($data);
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
            'parent_org_id' => $data['parent_org_id'] ?? null,
            'organization_name' => $organizationName,
            'business_type' => $data['business_type'] ?? null,
            'public_subcategory' => $data['public_subcategory'] ?? null,
            'public_tag' => $data['public_tag'] ?? null,
            'is_verified' => $data['is_verified'] ?? false,
            'categories' => $data['categories'] ?? [],
            'subcategory_ids' => $data['subcategory_ids'] ?? [],
            'subcategory_id' => $data['subcategory_ids'][0] ?? null,
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'address' => $data['address'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'profile_banner' => $data['profile_banner'] ?? null,
            'opening_hours' => $data['opening_hours'] ?? null,
            'business_hours' => $data['business_hours'] ?? [],
            'facebook_url' => $data['facebook_url'] ?? null,
            'instagram_url' => $data['instagram_url'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'google_map_url' => $data['google_map_url'] ?? null,
            'payment_methods' => $data['payment_methods'] ?? [],
            'facilities' => $data['facilities'] ?? [],
            'highlights' => $data['highlights'] ?? [],
            'catalog_sections' => $data['catalog_sections'] ?? [],
            'catalog_items' => $data['catalog_items'] ?? [],
            'full_name' => null,
            'dob' => null,
            'about' => $data['about'] ?? null,
            'store_tags' => $data['store_tags'] ?? [],
        ]);

        Role::firstOrCreate(['name' => 'organization', 'guard_name' => 'sanctum']);
        $organization->syncRoles(['organization']);

        return response()->json([
            'success' => true,
            'organization' => $this->formatOrganization($organization),
            'created' => true,
        ], 201);
    }

    public function showOrganization(User $user)
    {
        $this->ensureOrganization($user);

        return response()->json([
            'success' => true,
            'organization' => $this->formatOrganization($user->fresh()),
        ]);
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
            'public_subcategory' => ['nullable', 'string', 'max:100'],
            'public_tag' => ['nullable', 'string', 'max:50'],
            'is_verified' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'parent_org_id' => [
                'nullable',
                'integer',
                Rule::notIn([$user->id]),
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'organization')
                    ->whereNull('parent_org_id')),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string'],
            'store_tags' => ['nullable', 'array', 'max:30'],
            'store_tags.*' => ['string', 'max:100'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'profile_banner' => ['nullable', 'string', 'max:500'],
            'interior_media' => ['nullable', 'array', 'max:20'],
            'interior_media.*.url' => ['required', 'string', 'max:500'],
            'interior_media.*.type' => ['required', Rule::in(['image', 'video'])],
            'opening_hours' => ['nullable', 'string', 'max:120'],
            'business_hours' => ['nullable', 'array'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'payment_methods' => ['nullable', 'array'],
            'facilities' => ['nullable', 'array'],
            'highlights' => ['nullable', 'array'],
            'catalog_sections' => ['nullable', 'array'],
            'catalog_items' => ['nullable', 'array'],
        ]);

        if (array_key_exists('organization_name', $data)) {
            $data['organization_name'] = trim($data['organization_name']);
        }

        $this->validateOrganizationSubcategory($data);

        if (array_key_exists('subcategory_ids', $data)) {
            $data['subcategory_id'] = $data['subcategory_ids'][0] ?? null;
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

    public function uploadOrganizationImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file('image')->store('uploads/organizations', 'public');

        return response()->json([
            'success' => true,
            'imageUrl' => '/storage/' . $path,
        ], 201);
    }

    public function listOrganizationPosts(User $user)
    {
        $this->ensureOrganization($user);
        return response()->json(['success' => true, 'posts' => StorePost::where('organization_id', $user->id)
            ->orderByDesc('is_pinned')->orderBy('pin_order')->orderByDesc('created_at')->get()]);
    }

    public function storeOrganizationPost(Request $request, User $user)
    {
        $this->ensureOrganization($user);
        $data = $this->validateOrganizationPost($request);
        $this->ensureSingleMenuPost($user->id, $data['type']);
        $data['organization_id'] = $user->id;
        if ($data['type'] === 'menu') {
            $data['is_pinned'] = true;
            $data['pin_order'] = 0;
        }
        if (!empty($data['is_pinned'])) {
            $data['pin_order'] ??= ((int) StorePost::where('organization_id', $user->id)->where('is_pinned', true)->max('pin_order')) + 1;
        }
        $post = StorePost::create($data);
        return response()->json(['success' => true, 'post' => $post], 201);
    }

    public function updateOrganizationPost(Request $request, User $user, StorePost $post)
    {
        $this->ensureOrganization($user);
        abort_unless((int) $post->organization_id === (int) $user->id, 404);
        $data = $this->validateOrganizationPost($request);
        $this->ensureSingleMenuPost($user->id, $data['type'], $post->id);
        if ($data['type'] === 'menu') {
            $data['is_pinned'] = true;
            $data['pin_order'] = 0;
        }
        if (!empty($data['is_pinned']) && !$post->is_pinned) {
            $data['pin_order'] ??= ((int) StorePost::where('organization_id', $user->id)->where('is_pinned', true)->max('pin_order')) + 1;
        } elseif (empty($data['is_pinned'])) {
            $data['pin_order'] = null;
        }
        $post->update($data);
        return response()->json(['success' => true, 'post' => $post->fresh()]);
    }

    public function deleteOrganizationPost(User $user, StorePost $post)
    {
        $this->ensureOrganization($user);
        abort_unless((int) $post->organization_id === (int) $user->id, 404);
        $post->delete();
        return response()->json(['success' => true]);
    }

    private function validateOrganizationPost(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['general', 'menu', 'offer', 'event'])],
            'source_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'string', 'max:500'],
            'media' => ['nullable', 'array', 'max:20'],
            'media.*.url' => ['required', 'string', 'max:500'],
            'media.*.type' => ['required', Rule::in(['image', 'video'])],
            'media.*.caption' => ['nullable', 'string', 'max:500'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);
    }

    private function ensureSingleMenuPost(int $organizationId, string $type, ?int $ignorePostId = null): void
    {
        if ($type !== 'menu') return;

        $query = StorePost::where('organization_id', $organizationId)->where('type', 'menu');
        if ($ignorePostId) $query->where('id', '!=', $ignorePostId);
        if ($query->exists()) {
            throw ValidationException::withMessages(['type' => 'This store already has a Menu post.']);
        }
    }

    public function uploadOrganizationPostMedia(Request $request)
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,avif,mp4,webm,mov', 'max:20480'],
        ]);
        $media = collect($request->file('files'))->map(function ($file) {
            $path = $file->store('uploads/store-posts', 'public');
            return ['url' => '/storage/' . $path, 'type' => str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image'];
        })->values();
        return response()->json(['success' => true, 'media' => $media], 201);
    }

    private function ensureOrganization(User $user): void
    {
        abort_unless($user->role === 'organization', 404);
    }

    public function stats()
    {
        $signedUpUserAcquisition = $this->signupSourceStats();

        return response()->json([
            'success' => true,
            'stats' => [
                'totalUsers' => User::where('role', 'user')->count(),
                'adminCount' => User::where('role', 'admin')->count(),
                'organizationCount' => User::where('role', 'organization')->count(),
                'userCount' => User::where('role', 'user')->count(),
                'ageRatio' => $this->ageRatioStats(),
                'genderRatio' => $this->genderRatioStats(),
                'thisWeekUsers' => User::where('role', 'user')
                    ->whereBetween('created_at', [
                        now()->startOfWeek(),
                        now()->endOfWeek(),
                    ])
                    ->count(),
                'activeOffers' => Offer::where('status', 'published')->count(),
                'publishedEvents' => Event::where('status', 'published')->count(),
                'activeCategories' => Category::where('status', 'active')->count(),
                'userAcquisition' => $this->userAcquisitionStats(),
                'signedUpUserAcquisition' => $signedUpUserAcquisition,
                'signupSources' => $signedUpUserAcquisition,
            ],
        ]);
    }

    private function acquisitionBuckets(): array
    {
        return [
            'facebook' => 0,
            'instagram' => 0,
            'google' => 0,
            'other' => 0,
        ];
    }

    private function normalizeAcquisitionSource(?string $source): string
    {
        $source = Str::lower(trim((string) $source));

        return match (true) {
            in_array($source, ['facebook', 'fb'], true) || str_contains($source, 'facebook') => 'facebook',
            in_array($source, ['instagram', 'ig'], true) || str_contains($source, 'instagram') => 'instagram',
            $source === 'google' || str_contains($source, 'google') => 'google',
            default => 'other',
        };
    }

    private function formatAcquisitionBuckets(array $sources): array
    {
        return collect($sources)
            ->map(fn ($count, $source) => [
                'source' => $source,
                'label' => match ($source) {
                    'facebook' => 'Facebook',
                    'instagram' => 'Instagram',
                    'google' => 'Google',
                    default => 'Other',
                },
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    private function userAcquisitionStats(): array
    {
        $sources = $this->acquisitionBuckets();

        AnalyticsEvent::query()
            ->where('event_name', 'site_landing')
            ->get(['channel', 'metadata'])
            ->each(function (AnalyticsEvent $event) use (&$sources) {
                $source = $this->normalizeAcquisitionSource(
                    $event->channel ?: ($event->metadata['source'] ?? null)
                );
                $sources[$source]++;
            });

        return $this->formatAcquisitionBuckets($sources);
    }

    private function signupSourceStats(): array
    {
        $sources = $this->acquisitionBuckets();

        User::query()
            ->select('signup_source')
            ->get()
            ->each(function (User $user) use (&$sources) {
                $source = $this->normalizeAcquisitionSource($user->signup_source);
                $sources[$source]++;
            });

        return $this->formatAcquisitionBuckets($sources);
    }

    private function genderRatioStats(): array
    {
        $buckets = [
            'male' => ['label' => 'Male', 'group' => 'Specified', 'count' => 0],
            'female' => ['label' => 'Female', 'group' => 'Specified', 'count' => 0],
            'other' => ['label' => 'Other', 'group' => 'Specified', 'count' => 0],
            'prefer_not_to_say' => ['label' => 'Prefer not to say', 'group' => 'Unspecified', 'count' => 0],
            'unknown' => ['label' => 'Unknown', 'group' => 'Unspecified', 'count' => 0],
        ];

        User::query()
            ->select('gender')
            ->get()
            ->each(function (User $user) use (&$buckets) {
                $gender = $user->gender ?: 'unknown';

                if (!array_key_exists($gender, $buckets)) {
                    $gender = 'unknown';
                }

                $buckets[$gender]['count']++;
            });

        return collect($buckets)
            ->map(fn ($item, $gender) => [
                'gender' => $gender,
                'label' => $item['label'],
                'group' => $item['group'],
                'count' => $item['count'],
            ])
            ->values()
            ->all();
    }

    private function ageRatioStats(): array
    {
        $buckets = [
            'Under 18' => 0,
            '18-24' => 0,
            '25-34' => 0,
            '35-44' => 0,
            '45+' => 0,
            'Unknown' => 0,
        ];

        User::query()
            ->select('dob')
            ->get()
            ->each(function (User $user) use (&$buckets) {
                if (!$user->dob) {
                    $buckets['Unknown']++;
                    return;
                }

                $age = Carbon::parse($user->dob)->age;

                if ($age < 18) {
                    $buckets['Under 18']++;
                } elseif ($age <= 24) {
                    $buckets['18-24']++;
                } elseif ($age <= 34) {
                    $buckets['25-34']++;
                } elseif ($age <= 44) {
                    $buckets['35-44']++;
                } else {
                    $buckets['45+']++;
                }
            });

        return collect($buckets)
            ->map(fn ($count, $label) => [
                'ageGroup' => $label,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    public function analyticsClicks(Request $request)
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:180'],
        ]);

        $days = (int) ($data['days'] ?? 30);
        $startAt = now()->subDays($days)->startOfDay();

        $query = AnalyticsEvent::query()
            ->whereIn('event_name', ['highlight_click', 'highlight_item_open', 'filter_click', 'filter_apply'])
            ->where('occurred_at', '>=', $startAt);

        $totals = [
            'tracked_events' => (clone $query)->count(),
            'highlight_clicks' => (clone $query)->where('event_name', 'highlight_click')->count(),
            'highlight_opens' => (clone $query)->where('event_name', 'highlight_item_open')->count(),
            'filter_clicks' => (clone $query)->where('event_name', 'filter_click')->count(),
            'filter_applies' => (clone $query)->where('event_name', 'filter_apply')->count(),
        ];

        $byPage = (clone $query)
            ->select('page', DB::raw('COUNT(*) as total'))
            ->groupBy('page')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'page' => $row->page ?: 'unknown',
                'total' => (int) $row->total,
            ])->values();

        $highlightEvents = AnalyticsEvent::query()
            ->whereIn('event_name', ['highlight_click', 'highlight_item_open'])
            ->where('occurred_at', '>=', $startAt)
            ->get([
                'id',
                'event_name',
                'page',
                'action',
                'highlight_id',
                'offer_id',
                'event_id',
                'organization_id',
                'metadata',
                'occurred_at',
            ]);

        $stringOrNull = static function ($value): ?string {
            if ($value === null) {
                return null;
            }

            $normalized = trim((string) $value);
            return $normalized !== '' ? $normalized : null;
        };

        $resolveHighlightTitle = static function ($row, array $metadata) use ($stringOrNull): string {
            return $stringOrNull(data_get($metadata, 'item_title'))
                ?? $stringOrNull(data_get($metadata, 'item_subtitle'))
                ?? $stringOrNull(data_get($metadata, 'highlight_title'))
                ?? 'Unknown item';
        };

        $highlightActions = $highlightEvents
            ->groupBy(function ($row) {
                if ($row->event_name === 'highlight_item_open') {
                    return 'open';
                }

                return $row->action ?: 'unknown';
            })
            ->map(fn ($rows, $action) => [
                'action' => $action,
                'total' => $rows->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $highlightClicksByItem = $highlightEvents
            ->filter(fn ($row) => $row->event_name === 'highlight_click')
            ->groupBy(function ($row) {
                $metadata = is_array($row->metadata) ? $row->metadata : [];
                return implode('|', [
                    $row->action ?: 'click',
                    (string) ($row->highlight_id ?? ''),
                    (string) ($row->offer_id ?? ''),
                    (string) ($row->event_id ?? ''),
                    (string) ($row->organization_id ?? ''),
                    (string) data_get($metadata, 'item_title', ''),
                    (string) data_get($metadata, 'item_subtitle', ''),
                    (string) data_get($metadata, 'item_index', ''),
                    (string) data_get($metadata, 'item_position', ''),
                ]);
            })
            ->map(function ($rows) use ($resolveHighlightTitle, $stringOrNull) {
                $row = $rows->first();
                $metadata = is_array($row->metadata) ? $row->metadata : [];
                $itemIndex = data_get($metadata, 'item_index');
                $itemPosition = $stringOrNull(data_get($metadata, 'item_position'))
                    ?? ($itemIndex ? 'Slide ' . $itemIndex : null);

                return [
                    'action' => $row->action ?: 'click',
                    'highlight_id' => $row->highlight_id,
                    'highlight_title' => $stringOrNull(data_get($metadata, 'highlight_title')) ?: 'Highlight',
                    'item_title' => $resolveHighlightTitle($row, $metadata),
                    'item_subtitle' => $stringOrNull(data_get($metadata, 'item_subtitle')),
                    'item_index' => $itemIndex ? (int) $itemIndex : null,
                    'item_position' => $itemPosition,
                    'offer_id' => $row->offer_id,
                    'event_id' => $row->event_id,
                    'organization_id' => $row->organization_id,
                    'total' => $rows->count(),
                ];
            })
            ->sortByDesc('total')
            ->take(15)
            ->values();

        $highlightOpensByItem = $highlightEvents
            ->filter(fn ($row) => $row->event_name === 'highlight_item_open')
            ->groupBy(function ($row) {
                $metadata = is_array($row->metadata) ? $row->metadata : [];
                return implode('|', [
                    (string) ($row->highlight_id ?? ''),
                    (string) ($row->offer_id ?? ''),
                    (string) ($row->event_id ?? ''),
                    (string) ($row->organization_id ?? ''),
                    (string) data_get($metadata, 'item_title', ''),
                    (string) data_get($metadata, 'item_subtitle', ''),
                    (string) data_get($metadata, 'item_index', ''),
                    (string) data_get($metadata, 'item_position', ''),
                ]);
            })
            ->map(function ($rows) use ($resolveHighlightTitle, $stringOrNull) {
                $row = $rows->first();
                $metadata = is_array($row->metadata) ? $row->metadata : [];
                $itemIndex = data_get($metadata, 'item_index');
                $itemPosition = $stringOrNull(data_get($metadata, 'item_position'))
                    ?? ($itemIndex ? 'Slide ' . $itemIndex : null);

                return [
                    'highlight_id' => $row->highlight_id,
                    'highlight_title' => $stringOrNull(data_get($metadata, 'highlight_title')) ?: 'Highlight',
                    'item_title' => $resolveHighlightTitle($row, $metadata),
                    'item_subtitle' => $stringOrNull(data_get($metadata, 'item_subtitle')),
                    'item_index' => $itemIndex ? (int) $itemIndex : null,
                    'item_position' => $itemPosition,
                    'offer_id' => $row->offer_id,
                    'event_id' => $row->event_id,
                    'organization_id' => $row->organization_id,
                    'total' => $rows->count(),
                ];
            })
            ->sortByDesc('total')
            ->take(15)
            ->values();

        $filterEvents = AnalyticsEvent::query()
            ->whereIn('event_name', ['filter_click', 'filter_apply'])
            ->where('occurred_at', '>=', $startAt)
            ->get()
            ->map(function ($row) {
                $row->metadata = is_array($row->metadata) ? $row->metadata : [];
                return $row;
            });

        $filterActions = $filterEvents
            ->filter(fn ($row) => $row->event_name === 'filter_click')
            ->groupBy(function ($row) {
                $label = data_get($row->metadata, 'filter_label') ?: data_get($row->metadata, 'attribute_name') ?: $row->filter ?: 'unknown';
                return implode('|', [$row->filter ?: 'unknown', $label, $row->action ?: 'unknown']);
            })
            ->map(function ($rows) {
                $row = $rows->first();
                $label = data_get($row->metadata, 'filter_label') ?: data_get($row->metadata, 'attribute_name') ?: $row->filter ?: 'unknown';
                return [
                    'filter' => $row->filter ?: 'unknown',
                    'filter_label' => $label,
                    'action' => $row->action ?: 'unknown',
                    'total' => $rows->count(),
                ];
            })
            ->sortByDesc('total')
            ->take(50)
            ->values();

        $filterDetails = $filterEvents
            ->groupBy(function ($row) {
                return implode('|', [
                    $row->event_name ?: 'unknown',
                    $row->filter ?: 'unknown',
                    $row->action ?: 'unknown',
                    (string) data_get($row->metadata, 'filter_label', ''),
                    (string) data_get($row->metadata, 'attribute_name', ''),
                    (string) data_get($row->metadata, 'filter_value', ''),
                ]);
            })
            ->map(function ($rows) {
                $row = $rows->first();
                return [
                    'event_name' => $row->event_name ?: 'unknown',
                    'filter' => $row->filter ?: 'unknown',
                    'filter_label' => data_get($row->metadata, 'filter_label') ?: data_get($row->metadata, 'attribute_name') ?: ($row->filter ?: 'unknown'),
                    'action' => $row->action ?: ($row->event_name === 'filter_apply' ? 'apply' : 'unknown'),
                    'attribute_name' => data_get($row->metadata, 'attribute_name'),
                    'filter_value' => data_get($row->metadata, 'filter_value'),
                    'total' => $rows->count(),
                ];
            })
            ->sortByDesc('total')
            ->take(20)
            ->values();

        $daily = (clone $query)
            ->select(DB::raw('DATE(occurred_at) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy(DB::raw('DATE(occurred_at)'))
            ->orderBy(DB::raw('DATE(occurred_at)'))
            ->get()
            ->map(fn ($row) => [
                'day' => (string) $row->day,
                'total' => (int) $row->total,
            ])->values();

        $recent = (clone $query)
            ->select([
                'id',
                'event_name',
                'page',
                'action',
                'filter',
                'highlight_id',
                'offer_id',
                'event_id',
                'organization_id',
                'metadata',
                'occurred_at',
            ])
            ->latest('occurred_at')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'window_days' => $days,
            'totals' => $totals,
            'by_page' => $byPage,
            'highlight_actions' => $highlightActions,
            'highlight_click_items' => $highlightClicksByItem,
            'highlight_open_items' => $highlightOpensByItem,
            'filter_actions' => $filterActions,
            'filter_details' => $filterDetails,
            'daily' => $daily,
            'recent' => $recent,
        ]);
    }

    public function listCategories(Request $request)
    {
        $query = Category::query()
            ->with('parent:id,name')
            ->when($request->query('hierarchy') === 'subcategories', fn ($q) => $q->whereNotNull('parent_id'))
            ->when(!$request->has('hierarchy'), fn ($q) => $q->whereNull('parent_id'))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('type') === 'event', fn ($q) => $q->where('is_event_category', true))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            });

        if ($request->query('hierarchy') === 'subcategories') {
            $query->orderBy('parent_id')->orderByDesc('order')->orderBy('name');
        } else {
            $query->orderBy('order')->orderBy('name');
        }

        $categories = $query->get();

        return response()->json(['success' => true, 'categories' => $categories]);
    }

    public function listAreas(Request $request)
    {
        $areas = Area::query()
            ->when($request->query('search'), function ($q, $term) {
                $q->where('name', 'like', "%{$term}%");
            })
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'areas' => $areas]);
    }

    public function storeArea(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:areas,name'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['error' => 'Area name is required.'], 422);
        }

        $area = Area::create([
            'name' => $name,
            'order' => (int) ($data['order'] ?? 0),
        ]);

        return response()->json(['success' => true, 'area' => $area], 201);
    }

    public function updateArea(Request $request, Area $area)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('areas', 'name')->ignore($area->id)],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['error' => 'Area name is required.'], 422);
        }

        $area->update([
            'name' => $name,
            'order' => (int) ($data['order'] ?? 0),
        ]);

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
            'banner' => ['nullable'],
            'gallery_sort_order' => ['nullable'],
            'icon' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            'is_event_category' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order'] ?? []);
        if (!is_array($gallerySortOrder)) {
            $gallerySortOrder = [];
        }

        $category = Category::create($data + ['created_by' => $request->user()->id]);
        $category->update([
            'banner' => $this->toArrayField($data['banner'] ?? []),
            'gallery_sort_order' => $gallerySortOrder,
        ]);

        return response()->json(['success' => true, 'category' => $category], 201);
    }

    public function reorderCategories(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'size:2'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:categories,id'],
            'items.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $categories = Category::whereIn('id', collect($data['items'])->pluck('id')->all())->get();
        if ($categories->count() !== 2 || $categories->pluck('parent_id')->unique()->count() !== 1 || $categories->first()->parent_id === null) {
            return response()->json(['error' => 'Only subcategories in the same parent category can be swapped.'], 422);
        }

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                Category::whereKey($item['id'])->update(['order' => (int) $item['order']]);
            }
        });

        return response()->json(['success' => true, 'items' => $data['items']]);
    }

    public function updateCategory(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'string', 'max:500'],
            'banner' => ['nullable'],
            'gallery_sort_order' => ['nullable'],
            'icon' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            'is_event_category' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([$category->id])],
        ]);

        if (array_key_exists('banner', $data)) {
            $data['banner'] = $this->toArrayField($data['banner']);
        }
        if (array_key_exists('gallery_sort_order', $data)) {
            $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order']);
            $data['gallery_sort_order'] = is_array($gallerySortOrder) ? $gallerySortOrder : [];
        }

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
            'image' => [
                'required',
                'file',
                // Category SVGs are reduced below 5 MB in the admin UI before upload.
                'max:5120',
                function (string $attribute, mixed $file, \Closure $fail): void {
                    $extension = strtolower($file->getClientOriginalExtension());
                    $rasterExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                    if (in_array($extension, $rasterExtensions, true)) {
                        return;
                    }

                    if ($extension !== 'svg') {
                        $fail('The category image must be an SVG, JPG, PNG, WEBP, or GIF file.');
                        return;
                    }

                    $contents = file_get_contents($file->getRealPath());
                    $isSvg = is_string($contents)
                        && preg_match('/<svg\b[^>]*xmlns=["\']http:\/\/www\.w3\.org\/2000\/svg["\'][^>]*>/i', $contents);
                    $hasUnsafeContent = is_string($contents) && preg_match(
                        '/<script\b|\son[a-z]+\s*=|javascript\s*:|<foreignObject\b/i',
                        $contents
                    );

                    if (!$isSvg || $hasUnsafeContent) {
                        $fail('Please upload a valid SVG without scripts or embedded HTML.');
                    }
                },
            ],
        ]);

        $path = $request->file('image')->store('uploads/categories', 'public');
        return response()->json([
            'success' => true,
            'imageUrl' => '/storage/' . $path,
        ], 201);
    }

    public function listFacilities()
    {
        return response()->json([
            'success' => true,
            'facilities' => Facility::orderByDesc('order')->orderBy('name')->get(),
        ]);
    }

    public function storeFacility(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'image' => ['required', 'string', 'max:500'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $facility = Facility::create($data + ['created_by' => $request->user()->id]);

        return response()->json(['success' => true, 'facility' => $facility], 201);
    }

    public function updateFacility(Request $request, Facility $facility)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'image' => ['required', 'string', 'max:500'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $facility->update($data);

        return response()->json(['success' => true, 'facility' => $facility]);
    }

    public function reorderFacilities(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'size:2'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:facilities,id'],
            'items.*.order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                Facility::whereKey($item['id'])->update(['order' => (int) $item['order']]);
            }
        });

        return response()->json(['success' => true, 'items' => $data['items']]);
    }

    public function deleteFacility(Facility $facility)
    {
        $facility->delete();

        return response()->json(['success' => true]);
    }

    public function uploadFacilityImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file('image')->store('uploads/facilities', 'public');

        return response()->json([
            'success' => true,
            'imageUrl' => '/storage/' . $path,
        ], 201);
    }

    public function listEvents(Request $request)
    {
        $this->syncOfferAndEventLifecycle();

        $excludedStatuses = $request->boolean('coupon_targets')
            ? ['canceled', 'archived']
            : [];

        $query = Event::query()
            ->with([
                'organization:id,organization_name,username,phone',
                'category:id,name',
                'area:id,name',
                'creator:id,username,full_name',
            ])
            ->when($request->query('status'), fn ($q, $status) => $q->whereRaw('LOWER(TRIM(status)) = ?', [strtolower(trim($status))]))
            ->when($request->query('status_group') === 'published', fn ($q) => $q->whereRaw("LOWER(TRIM(status)) IN ('published', 'active')"))
            ->when($request->query('status_group') === 'other', fn ($q) => $q->whereRaw("LOWER(TRIM(status)) NOT IN ('published', 'active')"))
            ->when(
                count($excludedStatuses) > 0,
                fn ($q) => $q->whereRaw("LOWER(TRIM(status)) NOT IN ('" . implode("','", $excludedStatuses) . "')")
            )
            ->when($request->query('category_id'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('location', 'like', "%{$term}%");
                });
            })
            ->when($request->query('organization_id'), fn ($q, $id) => $q->where('organization_id', $id));

        $eventSortColumns = ['sort_order', 'id', 'name', 'location', 'starting_date', 'end_date', 'expiration_date', 'status', 'created_at'];
        $sortBy = $request->query('sort_by');
        if (in_array($sortBy, $eventSortColumns, true)) {
            $query->orderBy($sortBy, $request->query('sort_dir') === 'desc' ? 'desc' : 'asc')
                ->orderByDesc('created_at');
        } else {
            $query->orderByDesc('sort_order')
                ->orderByDesc('created_at');
        }

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
            'ordering' => [
                'max_sort_order' => (int) Event::max('sort_order'),
                'next_sort_order' => (int) Event::max('sort_order') + 1,
            ],
        ]);
    }

    public function reorderEvents(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:events,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                Event::whereKey($item['id'])->update(['sort_order' => (int) $item['sort_order']]);
            }
        });

        return response()->json([
            'success' => true,
            'ordering' => [
                'max_sort_order' => (int) Event::max('sort_order'),
                'next_sort_order' => (int) Event::max('sort_order') + 1,
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
            'status' => ['nullable', Rule::in($this->offerEventStatuses())],
            'starting_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['required', 'date', 'after_or_equal:starting_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:end_date'],
            'expiration_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'area_ids' => ['nullable', 'array'],
            'area_ids.*' => ['integer', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('parent_id', $request->input('category_id')))],
            'organization_id' => ['nullable', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'serial' => ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('serial', $data) && !array_key_exists('sort_order', $data)) {
            $data['sort_order'] = $data['serial'];
        }

        $data = $this->normalizeAreaSelection($data, 'events');
        $data = $this->normalizeDateAndTimeFields($data, 'starting_date');
        $data = $this->normalizeExpirationFields($data);

        $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order'] ?? []);
        if (!is_array($gallerySortOrder)) {
            $gallerySortOrder = [];
        }

        $event = DB::transaction(function () use ($data, $request, $gallerySortOrder) {
            $requestedOrder = $data['sort_order'] ?? null;
            $nextOrder = (int) Event::max('sort_order') + 1;
            $finalOrder = $requestedOrder === null ? $nextOrder : max(0, (int) $requestedOrder);

            if ($requestedOrder !== null) {
                Event::where('sort_order', '>=', $finalOrder)->increment('sort_order');
            }

            return Event::create([
                ...$data,
                'sort_order' => $finalOrder,
                'banner' => $this->toArrayField($data['banner'] ?? []),
                'gallery_sort_order' => $gallerySortOrder,
                'attributes' => $this->normalizeAttributes($data['attributes'] ?? []),
                'created_by' => $request->user()->id,
            ]);
        });

        $this->syncOrganizationEventContactDefaults($data);
        $this->syncOfferAndEventLifecycle(Event::class, [$event->id]);
        $event->refresh();

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
            'status' => ['nullable', Rule::in($this->offerEventStatuses())],
            'starting_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:starting_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:end_date'],
            'expiration_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'area_ids' => ['nullable', 'array'],
            'area_ids.*' => ['integer', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('parent_id', $request->input('category_id', $event->category_id)))],
            'organization_id' => ['nullable', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'serial' => ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('serial', $data) && !array_key_exists('sort_order', $data)) {
            $data['sort_order'] = $data['serial'];
        }

        $data = $this->normalizeAreaSelection($data, 'events');
        $data = $this->normalizeDateAndTimeFields($data, 'starting_date');
        $data = $this->normalizeExpirationFields($data, $event);

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

        DB::transaction(function () use ($data, $event) {
            if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
                $newOrder = max(0, (int) $data['sort_order']);
                $currentOrder = (int) $event->sort_order;

                if ($newOrder !== $currentOrder) {
                    if ($newOrder < $currentOrder) {
                        Event::where('id', '!=', $event->id)
                            ->whereBetween('sort_order', [$newOrder, $currentOrder - 1])
                            ->increment('sort_order');
                    } else {
                        Event::where('id', '!=', $event->id)
                            ->whereBetween('sort_order', [$currentOrder + 1, $newOrder])
                            ->decrement('sort_order');
                    }
                }

                $data['sort_order'] = $newOrder;
            }

            $event->update($data);
        });
        $this->syncOfferAndEventLifecycle(Event::class, [$event->id]);
        $event->refresh();
        return response()->json(['success' => true, 'event' => $event]);
    }

    public function deleteEvent(Event $event)
    {
        $event->update(['status' => 'canceled']);
        return response()->json(['success' => true]);
    }

    public function uploadEventBanner(Request $request)
    {
        try {
            $file = $this->validateMediaUpload($request, 'banner');
            $path = $file->store('uploads/events', 'public');
            return response()->json([
                'success' => true,
                'bannerUrl' => '/storage/' . $path,
                'mimeType' => $file->getClientMimeType(),
            ], 201);
        } catch (ValidationException $exception) {
            return $this->uploadValidationResponse($exception, 'Failed to upload banner media.');
        } catch (Throwable $exception) {
            Log::error('Event banner upload failed', ['error' => $exception->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to store uploaded media.'], 500);
        }
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
        $this->syncOfferAndEventLifecycle();

        $excludedStatuses = [];

        $query = Offer::query()
            ->with([
                'organization:id,organization_name,username,phone',
                'category:id,name',
                'area:id,name',
                'creator:id,username,full_name',
            ])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('status_group') === 'published', fn ($q) => $q->whereIn('status', ['published', 'active']))
            ->when($request->query('status_group') === 'other', fn ($q) => $q->whereNotIn('status', ['published', 'active']))
            ->when(count($excludedStatuses) > 0, fn ($q) => $q->whereNotIn('status', $excludedStatuses))
            ->when($request->query('category_id'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($request->query('offer_type'), fn ($q, $offerType) => $q->where('offer_type', $offerType))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('details', 'like', "%{$term}%");
                });
            })
            ->when($request->query('organization_id'), fn ($q, $id) => $q->where('organization_id', $id));

        $offerSortColumns = ['sort_order', 'id', 'name', 'start_date', 'end_date', 'expiration_date', 'status', 'created_at'];
        $sortBy = $request->query('sort_by');
        if (in_array($sortBy, $offerSortColumns, true)) {
            $query->orderBy($sortBy, $request->query('sort_dir') === 'desc' ? 'desc' : 'asc')
                ->orderByDesc('created_at');
        } else {
            $query->orderByDesc('sort_order')
                ->orderByDesc('created_at');
        }

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
            'ordering' => [
                'max_sort_order' => (int) Offer::max('sort_order'),
                'next_sort_order' => (int) Offer::max('sort_order') + 1,
            ],
        ]);
    }

    public function reorderOffers(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:offers,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                Offer::whereKey($item['id'])->update(['sort_order' => (int) $item['sort_order']]);
            }
        });

        return response()->json([
            'success' => true,
            'ordering' => [
                'max_sort_order' => (int) Offer::max('sort_order'),
                'next_sort_order' => (int) Offer::max('sort_order') + 1,
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
                'area_ids' => ['nullable', 'array'],
                'area_ids.*' => ['integer', 'exists:areas,id'],
                'status' => ['nullable', Rule::in($this->offerEventStatuses())],
                'expiration_date' => ['nullable', 'date', 'after_or_equal:end_date'],
                'expiration_time' => ['nullable', 'date_format:H:i'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
                'serial' => ['nullable', 'integer', 'min:0'],
                'offer_type' => ['nullable', Rule::in(['regular', 'exclusive'])],
                'is_recurring' => ['nullable', 'boolean'],
                'recurring_start_date' => ['nullable', 'date'],
                'recurring_end_date' => ['nullable', 'date'],
                'recurring_days' => ['nullable', 'array'],
                'recurring_days.*' => ['string'],
            ]);

            if (array_key_exists('serial', $data) && !array_key_exists('sort_order', $data)) {
                $data['sort_order'] = $data['serial'];
            }

            $data = $this->normalizeAreaSelection($data, 'offers');
            $data = $this->normalizeDateAndTimeFields($data, 'start_date');
            $data = $this->normalizeExpirationFields($data);
            $data = $this->normalizeOfferRecurringFields($data);
            $this->validateOfferRecurringFields($data);

            $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order'] ?? []);
            if (!is_array($gallerySortOrder)) {
                $gallerySortOrder = [];
            }
            $images = $this->toArrayField($data['images'] ?? []);
            $data['images'] = $images;
            $data['thumbnail'] = $this->resolveOfferThumbnail(
                $data['thumbnail'] ?? null,
                $images
            );

            $offer = DB::transaction(function () use ($data, $request, $gallerySortOrder) {
                $requestedOrder = $data['sort_order'] ?? null;
                $nextOrder = (int) Offer::max('sort_order') + 1;
                $finalOrder = $requestedOrder === null ? $nextOrder : max(0, (int) $requestedOrder);

                if ($requestedOrder !== null) {
                    Offer::where('sort_order', '>=', $finalOrder)->increment('sort_order');
                }

                $offer = Offer::create([
                    ...$data,
                    'sort_order' => $finalOrder,
                    'images' => $data['images'],
                    'gallery_sort_order' => $gallerySortOrder,
                    'videos' => $this->toArrayField($data['videos'] ?? []),
                    'attributes' => $this->normalizeAttributes($data['attributes'] ?? []),
                    'created_by' => $request->user()->id,
                ]);

                $this->syncOrganizationOfferContactDefaults($data);

                return $offer;
            });

            Log::info('API storeOffer success', ['offer_id' => $offer->id ?? null]);
            $this->syncOfferAndEventLifecycle(Offer::class, [$offer->id]);
            $offer->refresh();

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
                'area_ids' => ['nullable', 'array'],
                'area_ids.*' => ['integer', 'exists:areas,id'],
                'status' => ['nullable', Rule::in($this->offerEventStatuses())],
                'expiration_date' => ['nullable', 'date', 'after_or_equal:end_date'],
                'expiration_time' => ['nullable', 'date_format:H:i'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
                'serial' => ['nullable', 'integer', 'min:0'],
                'offer_type' => ['nullable', Rule::in(['regular', 'exclusive'])],
                'is_recurring' => ['nullable', 'boolean'],
                'recurring_start_date' => ['nullable', 'date'],
                'recurring_end_date' => ['nullable', 'date'],
                'recurring_days' => ['nullable', 'array'],
                'recurring_days.*' => ['string'],
            ]);

            if (array_key_exists('serial', $data) && !array_key_exists('sort_order', $data)) {
                $data['sort_order'] = $data['serial'];
            }

            $data = $this->normalizeAreaSelection($data, 'offers');
            $data = $this->normalizeDateAndTimeFields($data, 'start_date');
            $data = $this->normalizeExpirationFields($data, $offer);
            $data = $this->normalizeOfferRecurringFields($data);
            $this->validateOfferRecurringFields($data, $offer);

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
            if (
                array_key_exists('thumbnail', $data)
                || array_key_exists('images', $data)
            ) {
                $data['thumbnail'] = $this->resolveOfferThumbnail(
                    $data['thumbnail'] ?? $offer->thumbnail,
                    $data['images'] ?? $offer->images ?? []
                );
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
            $this->syncOfferAndEventLifecycle(Offer::class, [$offer->id]);
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
        $offer->update(['status' => 'canceled']);
        return response()->json(['success' => true]);
    }

    public function uploadOfferMedia(Request $request)
    {
        try {
            $file = $this->validateMediaUpload($request, 'file');
            $path = $file->store('uploads/offers', 'public');
            return response()->json([
                'success' => true,
                'fileUrl' => '/storage/' . $path,
                'mimeType' => $file->getClientMimeType(),
            ], 201);
        } catch (ValidationException $exception) {
            return $this->uploadValidationResponse($exception, 'Failed to upload media.');
        } catch (Throwable $exception) {
            Log::error('Offer media upload failed', ['error' => $exception->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to store uploaded media.'], 500);
        }
    }

    private function validateMediaUpload(Request $request, string $field)
    {
        $file = $request->file($field);
        if (!$file) {
            throw ValidationException::withMessages([
                $field => ['No file was received. Check PHP post_max_size and upload_max_filesize settings.'],
            ]);
        }

        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                $field => [$file->getErrorMessage() ?: 'The file failed to upload.'],
            ]);
        }

        if ($file->getSize() > self::MEDIA_UPLOAD_MAX_BYTES) {
            throw ValidationException::withMessages([
                $field => ['The file must not be greater than 20 MB.'],
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::MEDIA_UPLOAD_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                $field => ['Unsupported file type. Allowed types: ' . implode(', ', self::MEDIA_UPLOAD_EXTENSIONS) . '.'],
            ]);
        }

        return $file;
    }

    private function uploadValidationResponse(ValidationException $exception, string $fallbackMessage)
    {
        $message = $fallbackMessage;
        foreach ($exception->errors() as $messages) {
            if (!empty($messages[0])) {
                $message = $messages[0];
                break;
            }
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $exception->errors(),
        ], 422);
    }

    public function listCoupons(Request $request)
    {
        $this->expireCouponsIfNeeded();

        $query = Coupon::query()
            ->with([
                'organization:id,organization_name,username',
                'details.claimedBy:id,username,full_name,email,phone',
                'details:id,coupon_id,coupon_tier_id,coupon,offer_id,event_id,organization_id,claimed_by_user_id,claimed_at,is_used,user_id,used_at',
            ])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('organization_id'), fn ($q, $id) => $q->where('organization_id', $id))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id');

        $coupons = $query->paginate((int) $request->query('limit', 50));
        $items = collect($coupons->items())
            ->map(fn (Coupon $coupon) => $this->formatCouponForResponse($coupon))
            ->values();

        return response()->json([
            'success' => true,
            'coupons' => $items,
            'pagination' => [
                'total' => $coupons->total(),
                'per_page' => $coupons->perPage(),
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
            ],
        ]);
    }

    public function storeCoupon(Request $request)
    {
        [$data, $tiers, $masterPayload, $organizationId] = $this->prepareCouponPayload($request);
        $coupon = $this->saveCouponCampaign(null, $masterPayload, $tiers, $data, $request, $organizationId);

        $this->expireCouponsIfNeeded([$coupon->id]);
        $coupon->load([
            'organization:id,organization_name,username',
            'details.claimedBy:id,username,full_name,email,phone',
            'details:id,coupon_id,coupon_tier_id,coupon,offer_id,event_id,organization_id,claimed_by_user_id,claimed_at,is_used,user_id,used_at',
        ]);

        $responseCoupons = collect([$coupon])
            ->map(fn (Coupon $item) => $this->formatCouponForResponse($item))
            ->values();

        return response()->json([
            'success' => true,
            'created_count' => $responseCoupons->count(),
            'coupons' => $responseCoupons,
        ], 201);
    }

    public function updateCoupon(Request $request, Coupon $coupon)
    {
        $this->expireCouponsIfNeeded([$coupon->id]);
        $coupon->refresh();

        if ($coupon->status === 'archived') {
            return response()->json([
                'error' => 'Archived coupons are read-only.',
            ], 422);
        }

        [$data, $tiers, $masterPayload, $organizationId] = $this->prepareCouponPayload($request, $coupon);
        $coupon = $this->saveCouponCampaign($coupon, $masterPayload, $tiers, $data, $request, $organizationId);

        $this->expireCouponsIfNeeded([$coupon->id]);
        $coupon->load([
            'organization:id,organization_name,username',
            'details.claimedBy:id,username,full_name,email,phone',
            'details:id,coupon_id,coupon_tier_id,coupon,offer_id,event_id,organization_id,claimed_by_user_id,claimed_at,is_used,user_id,used_at',
        ]);

        return response()->json([
            'success' => true,
            'coupon' => $this->formatCouponForResponse($coupon),
        ]);
    }

    public function deleteCoupon(Coupon $coupon)
    {
        $this->expireCouponsIfNeeded([$coupon->id]);
        $coupon->refresh();

        if ($coupon->status === 'archived') {
            return response()->json([
                'error' => 'Archived coupons are read-only.',
            ], 422);
        }

        DB::transaction(function () use ($coupon) {
            $coupon->details()->delete();
            $coupon->tiers()->delete();
            $coupon->delete();
        });

        return response()->json([
            'success' => true,
        ]);
    }

    public function uploadCouponImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file('image')->store('uploads/coupons', 'public');

        return response()->json([
            'success' => true,
            'imageUrl' => '/storage/' . $path,
        ], 201);
    }

    private function prepareCouponPayload(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'coupon_name' => [
                'required',
                'string',
                'max:30',
                Rule::unique('coupons', 'name')->ignore($coupon?->id),
            ],
            'image' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'modal_image' => ['nullable', 'string', 'max:500'],
            'modal_title' => ['nullable', 'string', 'max:255'],
            'modal_main_text' => ['nullable', 'string', 'max:2000'],
            'modal_sub_text' => ['nullable', 'string', 'max:2000'],
            'modal_placeholder_text' => ['nullable', 'string', 'max:255'],
            'modal_success_message' => ['nullable', 'string', 'max:2000'],
            'campaign_type' => ['nullable', Rule::in(['standard'])],
            'offer_id' => ['nullable', 'exists:offers,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'scheduled', 'inactive', 'expired', 'archived', 'canceled'])],
            'start_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:end_date'],
            'expiration_time' => ['nullable', 'date_format:H:i'],
            'coupon_no' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $data = $this->normalizeDateAndTimeFields($data, 'start_date');
        if (array_key_exists('expiration_date', $data) && $data['expiration_date']) {
            [$normalizedDate, $timeFromDate] = $this->extractDateAndTime((string) $data['expiration_date']);
            $data['expiration_date'] = $normalizedDate;
            if (empty($data['expiration_time']) && $timeFromDate !== null) {
                $data['expiration_time'] = $timeFromDate;
            }
        }
        if (array_key_exists('expiration_time', $data)) {
            $data['expiration_time'] = $this->normalizeTimeValue($data['expiration_time']);
        }
        $this->validateCouponTargetFields($data);
        $couponCount = max(1, (int) ($data['coupon_no'] ?? $coupon?->total_coupon ?? 1));
        $organizationId = $this->resolveCouponOrganizationId($data);

        $masterPayload = [
            'name' => trim((string) $data['coupon_name']),
            'image' => trim((string) ($data['image'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'modal_image' => trim((string) ($data['modal_image'] ?? '')) ?: null,
            'modal_title' => trim((string) ($data['modal_title'] ?? '')) ?: null,
            'modal_main_text' => trim((string) ($data['modal_main_text'] ?? '')) ?: null,
            'modal_sub_text' => trim((string) ($data['modal_sub_text'] ?? '')) ?: null,
            'modal_placeholder_text' => trim((string) ($data['modal_placeholder_text'] ?? '')) ?: null,
            'modal_success_message' => trim((string) ($data['modal_success_message'] ?? '')) ?: null,
            'campaign_type' => 'standard',
            'organization_id' => $organizationId,
            'status' => $data['status'] ?? ($coupon?->status ?? 'draft'),
            'start_date' => $data['start_date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'expiration_date' => $data['expiration_date'] ?? null,
            'expiration_time' => $data['expiration_time'] ?? null,
            'total_coupon' => $couponCount,
            'usage_limit_per_user' => null,
            'updated_by' => $request->user()->id,
        ];

        if (!$coupon) {
            $masterPayload['created_by'] = $request->user()->id;
        }

        return [$data, [['quantity' => $couponCount]], $masterPayload, $organizationId];
    }

    private function saveCouponCampaign(?Coupon $coupon, array $masterPayload, array $tiers, array $data, Request $request, ?int $organizationId): Coupon
    {
        return DB::transaction(function () use ($coupon, $masterPayload, $tiers, $data, $request, $organizationId) {
            $master = $coupon;

            if ($master) {
                $lockedCodesQuery = $master->details()
                    ->where(function ($query) {
                        $query->whereNotNull('claimed_by_user_id')
                            ->orWhereNotNull('claimed_at')
                            ->orWhere('is_used', true)
                            ->orWhereNotNull('user_id')
                            ->orWhereNotNull('used_at');
                    });
                $hasLockedCodes = $lockedCodesQuery->exists();

                if ($hasLockedCodes) {
                    $existingCodeCount = $master->details()->count();
                    $requestedCodeCount = max(1, (int) ($tiers[0]['quantity'] ?? $masterPayload['total_coupon'] ?? $existingCodeCount));
                    $masterPayload['total_coupon'] = max($requestedCodeCount, $existingCodeCount);

                    $master->update($masterPayload);

                    $master->details()
                        ->whereNull('claimed_by_user_id')
                        ->whereNull('claimed_at')
                        ->where(function ($query) {
                            $query->where('is_used', false)->orWhereNull('is_used');
                        })
                        ->whereNull('user_id')
                        ->whereNull('used_at')
                        ->update([
                            'offer_id' => $data['offer_id'] ?? null,
                            'event_id' => $data['event_id'] ?? null,
                            'organization_id' => $organizationId,
                            'updated_by' => $request->user()->id,
                            'updated_at' => now(),
                        ]);

                    for ($codeIndex = $existingCodeCount; $codeIndex < $requestedCodeCount; $codeIndex++) {
                        CouponDetail::create([
                            'coupon_id' => $master->id,
                            'coupon_tier_id' => null,
                            'coupon' => $this->generateUniqueCouponCode($master->name),
                            'offer_id' => $data['offer_id'] ?? null,
                            'event_id' => $data['event_id'] ?? null,
                            'organization_id' => $organizationId,
                            'discount_type' => null,
                            'discount_value' => null,
                            'max_discount_amount' => null,
                            'min_order_amount' => null,
                            'referral_required_count' => 0,
                            'claimed_by_user_id' => null,
                            'claimed_at' => null,
                            'created_by' => $master->created_by ?: $request->user()->id,
                            'updated_by' => $request->user()->id,
                            'is_used' => false,
                            'used_at' => null,
                        ]);
                    }

                    return $master;
                }

                $master->update($masterPayload);
                $master->details()->delete();
                $master->tiers()->delete();
            } else {
                $master = Coupon::create($masterPayload);
            }

            $codeCount = max(1, (int) ($tiers[0]['quantity'] ?? $master->total_coupon ?? 1));

            for ($codeIndex = 0; $codeIndex < $codeCount; $codeIndex++) {
                CouponDetail::create([
                    'coupon_id' => $master->id,
                    'coupon_tier_id' => null,
                    'coupon' => $this->generateUniqueCouponCode($master->name),
                    'offer_id' => $data['offer_id'] ?? null,
                    'event_id' => $data['event_id'] ?? null,
                    'organization_id' => $organizationId,
                    'discount_type' => null,
                    'discount_value' => null,
                    'max_discount_amount' => null,
                    'min_order_amount' => null,
                    'referral_required_count' => 0,
                    'claimed_by_user_id' => null,
                    'claimed_at' => null,
                    'created_by' => $master->created_by ?: $request->user()->id,
                    'updated_by' => $request->user()->id,
                    'is_used' => false,
                    'used_at' => null,
                ]);
            }

            return $master;
        });
    }

    private function formatCouponForResponse(Coupon $coupon): array
    {
        $firstDetail = $coupon->details->first();
        $data = $coupon->toArray();
        unset($data['details']);

        $data['coupon_name'] = $coupon->name;
        $data['coupon_no'] = (int) ($coupon->total_coupon ?? 1);
        $data['status'] = $coupon->status ?: 'draft';
        $data['offer_id'] = $firstDetail?->offer_id;
        $data['event_id'] = $firstDetail?->event_id;
        $data['details'] = $coupon->details
            ->map(function (CouponDetail $detail) {
                $claimedBy = $detail->claimedBy;

                return [
                    'id' => $detail->id,
                    'coupon' => $detail->coupon,
                    'claimed_by_user_id' => $detail->claimed_by_user_id,
                    'claimed_by_name' => $claimedBy?->full_name ?: $claimedBy?->username,
                    'claimed_by_email' => $claimedBy?->email,
                    'claimed_by_phone' => $claimedBy?->phone,
                    'claimed_at' => optional($detail->claimed_at)?->toISOString(),
                    'is_used' => (bool) $detail->is_used,
                    'used_at' => optional($detail->used_at)?->toISOString(),
                ];
            })
            ->values()
            ->all();
        $data['claimed_count'] = (int) $coupon->details->filter(fn (CouponDetail $detail) => !empty($detail->claimed_by_user_id) || !empty($detail->claimed_at))->count();
        $data['used_count'] = (int) $coupon->details->filter(fn (CouponDetail $detail) => (bool) $detail->is_used)->count();
        $data['tiers'] = $coupon->tiers
            ->map(fn (CouponTier $tier) => [
                'id' => $tier->id,
                'label' => $tier->label,
                'quantity' => (int) ($tier->quantity ?? 0),
                'discount_type' => $tier->discount_type,
                'discount_value' => $tier->discount_value !== null ? (float) $tier->discount_value : null,
                'max_discount_amount' => $tier->max_discount_amount !== null ? (float) $tier->max_discount_amount : null,
                'min_order_amount' => $tier->min_order_amount !== null ? (float) $tier->min_order_amount : null,
                'referral_required_count' => (int) ($tier->referral_required_count ?? 0),
                'sort_order' => (int) ($tier->sort_order ?? 0),
            ])
            ->values()
            ->all();

        $firstTier = $coupon->tiers->first();
        $data['discount_type'] = $firstTier?->discount_type;
        $data['discount_value'] = $firstTier?->discount_value !== null ? (float) $firstTier->discount_value : null;
        $data['max_discount_amount'] = $firstTier?->max_discount_amount !== null ? (float) $firstTier->max_discount_amount : null;
        $data['min_order_amount'] = $firstTier?->min_order_amount !== null ? (float) $firstTier->min_order_amount : null;
        $data['referral_required_count'] = (int) ($firstTier?->referral_required_count ?? 0);

        if (empty($data['organization_id']) && $firstDetail?->organization_id) {
            $data['organization_id'] = $firstDetail->organization_id;
        }

        return $data;
    }

    private function validateCouponTargetFields(array $data): void
    {
        $targets = array_filter([
            $data['offer_id'] ?? null,
            $data['event_id'] ?? null,
        ], fn ($value) => !empty($value));

        if (count($targets) > 1) {
            throw ValidationException::withMessages([
                'target' => 'A coupon campaign can target either one offer or one event, not both at the same time.',
            ]);
        }
    }

    private function resolveCouponOrganizationId(array $data): ?int
    {
        $offerId = !empty($data['offer_id']) ? (int) $data['offer_id'] : null;
        if ($offerId) {
            return Offer::query()->whereKey($offerId)->value('organization_id');
        }

        $eventId = !empty($data['event_id']) ? (int) $data['event_id'] : null;
        if ($eventId) {
            return Event::query()->whereKey($eventId)->value('organization_id');
        }

        return null;
    }

    private function normalizeCouponTierPayload(array $data): array
    {
        $rawTiers = collect($data['tiers'] ?? [])->filter(fn ($tier) => is_array($tier))->values();

        if ($rawTiers->isEmpty()) {
            $rawTiers = collect([[
                'label' => 'Base tier',
                'quantity' => $data['coupon_no'] ?? 1,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'max_discount_amount' => $data['max_discount_amount'] ?? null,
                'min_order_amount' => $data['min_order_amount'] ?? null,
                'referral_required_count' => $data['referral_required_count'] ?? 0,
            ]]);
        }

        return $rawTiers
            ->map(function (array $tier, int $index) {
                return [
                    'label' => trim((string) ($tier['label'] ?? '')),
                    'quantity' => (int) ($tier['quantity'] ?? 0),
                    'discount_type' => $tier['discount_type'] ?? null,
                    'discount_value' => isset($tier['discount_value']) ? (float) $tier['discount_value'] : null,
                    'max_discount_amount' => isset($tier['max_discount_amount']) && $tier['max_discount_amount'] !== ''
                        ? (float) $tier['max_discount_amount']
                        : null,
                    'min_order_amount' => isset($tier['min_order_amount']) && $tier['min_order_amount'] !== ''
                        ? (float) $tier['min_order_amount']
                        : null,
                    'referral_required_count' => max(0, (int) ($tier['referral_required_count'] ?? 0)),
                    'sort_order' => $index,
                ];
            })
            ->all();
    }

    private function validateCouponTierPayload(array $tiers, ?string $campaignType = null, ?int $totalCouponLimit = null): void
    {
        $messages = [];

        foreach ($tiers as $index => $tier) {
            $path = "tiers.$index";
            if (($tier['quantity'] ?? 0) < 1) {
                $messages["$path.quantity"] = 'Each coupon tier must have at least one code.';
            }

            if (!in_array($tier['discount_type'] ?? null, ['percentage', 'flat'], true)) {
                $messages["$path.discount_type"] = 'Coupon discount type must be percentage or flat.';
            }

            if (($tier['discount_value'] ?? 0) <= 0) {
                $messages["$path.discount_value"] = 'Coupon discount value must be greater than zero.';
            }

            if (($tier['discount_type'] ?? null) === 'percentage' && ($tier['discount_value'] ?? 0) > 100) {
                $messages["$path.discount_value"] = 'Percentage discounts cannot exceed 100.';
            }
        }

        if ($tiers === []) {
            $messages['tiers'] = 'At least one coupon tier is required.';
        }

        $totalQuantity = (int) collect($tiers)->sum(fn ($tier) => (int) ($tier['quantity'] ?? 0));
        if ($totalCouponLimit !== null && $totalQuantity > $totalCouponLimit) {
            $messages['coupon_no'] = 'Tier code count cannot be greater than total codes.';
        }

        if ($campaignType === 'tiered' && $totalCouponLimit !== null && $totalQuantity !== $totalCouponLimit) {
            $messages['coupon_no'] = 'For tiered campaigns, total codes must match the sum of tier codes.';
        }

        if ($campaignType === 'referral' && !collect($tiers)->contains(fn ($tier) => (int) ($tier['referral_required_count'] ?? 0) > 0)) {
            $messages['tiers'] = 'Referral campaigns must require at least one referral on one tier.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function resolveCouponCampaignType(array $tiers, ?string $campaignType = null): string
    {
        if ($campaignType) {
            return $campaignType;
        }

        if (collect($tiers)->contains(fn ($tier) => (int) ($tier['referral_required_count'] ?? 0) > 0)) {
            return 'referral';
        }

        return count($tiers) > 1 ? 'tiered' : 'standard';
    }

    private function offerEventStatuses(): array
    {
        return ['draft', 'published', 'scheduled', 'expired', 'archived', 'canceled'];
    }

    private function contentBlockStatuses(): array
    {
        return ['draft', 'published', 'scheduled', 'expired', 'archived'];
    }

    private function normalizeContentBlockLifecycleFields(array $data, ?ContentBlock $contentBlock = null): array
    {
        $data = $this->normalizeDateAndTimeFields($data, 'start_date');

        $endDateWasProvided = array_key_exists('end_date', $data);
        $endTimeWasProvided = array_key_exists('end_time', $data);

        if ($endDateWasProvided && empty($data['expiration_date'])) {
            $data['expiration_date'] = $data['end_date'];
        }
        if ($endTimeWasProvided && empty($data['expiration_time'])) {
            $data['expiration_time'] = $data['end_time'];
        }

        $data = $this->normalizeExpirationFields($data, $contentBlock);

        if (!array_key_exists('status', $data) && array_key_exists('is_active', $data)) {
            $data['status'] = $data['is_active'] ? 'published' : 'draft';
        }

        $status = $data['status'] ?? $contentBlock?->status ?? 'published';
        $startDate = $data['start_date'] ?? $contentBlock?->start_date?->format('Y-m-d');
        $endDate = $data['end_date'] ?? $contentBlock?->end_date?->format('Y-m-d');
        $expirationDate = $data['expiration_date'] ?? $contentBlock?->expiration_date?->format('Y-m-d');

        if ($status === 'scheduled' && !$startDate) {
            throw ValidationException::withMessages([
                'start_date' => 'Start date is required for scheduled content blocks.',
            ]);
        }

        if ($expirationDate && !$endDate) {
            throw ValidationException::withMessages([
                'end_date' => 'End date is required when an expiration date is set.',
            ]);
        }

        $data['is_active'] = $status === 'published';

        return $data;
    }

    private function normalizeContentBlockItemLifecycleFields(array $item): array
    {
        $type = $item['type'] ?? 'custom';
        $targetId = $item['target_id'] ?? null;

        if ($type === 'offer' && $targetId) {
            $offer = Offer::find($targetId);
            return $this->applyContentBlockItemSourceLifecycle($item, $offer, 'start_date');
        }

        if ($type === 'event' && $targetId) {
            $event = Event::find($targetId);
            return $this->applyContentBlockItemSourceLifecycle($item, $event, 'starting_date');
        }

        if ($type !== 'custom') {
            return $this->applyContentBlockItemSourceLifecycle($item);
        }

        $item = $this->normalizeDateAndTimeFields($item, 'start_date');
        if (!empty($item['end_date']) && empty($item['expiration_date'])) {
            $item['expiration_date'] = $item['end_date'];
        }
        if (!empty($item['end_time']) && empty($item['expiration_time'])) {
            $item['expiration_time'] = $item['end_time'];
        }

        return $this->normalizeExpirationFields($item);
    }

    private function applyContentBlockItemSourceLifecycle(array $item, $source = null, string $startDateField = 'start_date'): array
    {
        $item['start_date'] = $source?->{$startDateField}?->format('Y-m-d');
        $item['start_time'] = $source?->start_time;
        $item['end_date'] = $source?->end_date?->format('Y-m-d');
        $item['end_time'] = $source?->end_time;
        $item['expiration_date'] = $source?->expiration_date?->format('Y-m-d');
        $item['expiration_time'] = $source?->expiration_time;

        return $item;
    }

    private function syncContentBlockLifecycle(?array $ids = null): void
    {
        $now = now('Asia/Dhaka')->toDateTimeString();

        ContentBlock::query()
            ->when($ids, fn ($query) => $query->whereIn('id', $ids))
            ->where('status', 'scheduled')
            ->whereNotNull('start_date')
            ->whereRaw("timestamp(start_date, coalesce(start_time, '00:00:00')) <= ?", [$now])
            ->update(['status' => 'published', 'is_active' => true, 'updated_at' => now()]);

        ContentBlock::query()
            ->when($ids, fn ($query) => $query->whereIn('id', $ids))
            ->where('status', 'published')
            ->whereNotNull('end_date')
            ->whereRaw("timestamp(end_date, coalesce(end_time, '23:59:59')) < ?", [$now])
            ->update(['status' => 'expired', 'is_active' => false, 'updated_at' => now()]);

        ContentBlock::query()
            ->when($ids, fn ($query) => $query->whereIn('id', $ids))
            ->where('status', 'expired')
            ->whereNotNull('expiration_date')
            ->whereRaw("timestamp(expiration_date, coalesce(expiration_time, '23:59:59')) < ?", [$now])
            ->update(['status' => 'archived', 'is_active' => false, 'updated_at' => now()]);
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

    private function expireCouponsIfNeeded(?array $couponIds = null): void
    {
        $now = $this->couponNow()->toDateTimeString();

        Coupon::query()
            ->when($couponIds, fn ($query) => $query->whereIn('id', $couponIds))
            ->whereNotIn('status', ['archived', 'canceled'])
            ->whereNotNull('expiration_date')
            ->whereRaw("timestamp(expiration_date, coalesce(expiration_time, '23:59:59')) < ?", [$now])
            ->update([
                'status' => 'inactive',
                'updated_at' => now(),
            ]);

        Coupon::query()
            ->when($couponIds, fn ($query) => $query->whereIn('id', $couponIds))
            ->whereNotIn('status', ['expired', 'inactive', 'archived', 'canceled'])
            ->whereNotNull('end_date')
            ->whereRaw("timestamp(end_date, coalesce(end_time, '23:59:59')) < ?", [$now])
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
    }

    private function couponNow()
    {
        return now('Asia/Dhaka');
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
            'items.*.video' => ['nullable', 'string', 'max:255'],
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
            'items.*.video' => ['nullable', 'string', 'max:255'],
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

            $image = $this->normalizeNullableString($item['image'] ?? null);
            $video = $this->normalizeNullableString($item['video'] ?? null);

            if ($video === null && $image !== null && $this->looksLikeVideoMedia($image)) {
                $video = $image;
                $image = null;
            }
            if ($video !== null) {
                $image = null;
            }

            $normalized[] = [
                'title' => $item['title'] ?? null,
                'subtitle' => $item['subtitle'] ?? null,
                'description' => $item['description'] ?? null,
                'offer_id' => $offerId,
                'event_id' => $eventId,
                'organization_id' => $organizationId,
                'image' => $image,
                'video' => $video,
                'external_link' => $item['external_link'] ?? null,
                'sort_order' => $item['sort_order'] ?? 0,
                'is_active' => $item['is_active'] ?? true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ];
        }

        return $normalized;
    }

    private function looksLikeVideoMedia(string $value): bool
    {
        $parsedPath = parse_url($value, PHP_URL_PATH);
        $candidate = is_string($parsedPath) ? $parsedPath : $value;
        $normalized = strtolower($candidate);

        return Str::endsWith($normalized, ['.mp4', '.webm', '.ogg', '.mov', '.m4v', '.avi', '.mkv']);
    }

    public function uploadHighlightMedia(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/*,video/*,application/octet-stream', 'max:153600'],
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
        $this->syncContentBlockLifecycle();

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
        $this->syncContentBlockLifecycle([$contentBlock->id]);
        $contentBlock->refresh();
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
            'status' => ['nullable', Rule::in($this->contentBlockStatuses())],
            'start_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:end_date'],
            'expiration_time' => ['nullable', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'teared_block' => ['nullable', 'boolean'],
            'thumbnail_image' => ['required', 'string', 'max:255'],
            'featured_sort_order' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $this->normalizeContentBlockLifecycleFields($data);

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

        $this->syncContentBlockLifecycle([$block->id]);
        $block->refresh();

        return response()->json(['success' => true, 'block' => $block], 201);
    }

    public function updateContentBlock(Request $request, ContentBlock $contentBlock)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in($this->contentBlockStatuses())],
            'start_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:end_date'],
            'expiration_time' => ['nullable', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'teared_block' => ['nullable', 'boolean'],
            'thumbnail_image' => ['nullable', 'string', 'max:255'],
            'featured_sort_order' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $this->normalizeContentBlockLifecycleFields($data, $contentBlock);

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

        $this->syncContentBlockLifecycle([$contentBlock->id]);
        $contentBlock->refresh();

        return response()->json(['success' => true, 'block' => $contentBlock]);
    }

    public function updateContentBlockItems(Request $request, ContentBlock $contentBlock)
    {
        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.type' => ['required', Rule::in(['custom', 'category', 'event', 'offer', 'store'])],
            'items.*.target_id' => ['nullable', 'integer'],
            'items.*.title' => ['nullable', 'string', 'max:200'],
            'items.*.subtitle' => ['nullable', 'string', 'max:255'],
            'items.*.image' => ['nullable', 'string', 'max:500'],
            'items.*.external_link' => ['nullable', 'string', 'max:500'],
            'items.*.start_date' => ['nullable', 'date'],
            'items.*.start_time' => ['nullable', 'date_format:H:i'],
            'items.*.end_date' => ['nullable', 'date', 'after_or_equal:items.*.start_date'],
            'items.*.end_time' => ['nullable', 'date_format:H:i'],
            'items.*.expiration_date' => ['nullable', 'date', 'after_or_equal:items.*.end_date'],
            'items.*.expiration_time' => ['nullable', 'date_format:H:i'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        $items = collect($data['items'] ?? [])
            ->map(fn (array $item) => $this->normalizeContentBlockItemLifecycleFields($item))
            ->all();

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
                    'start_date' => $item['start_date'] ?? null,
                    'start_time' => $item['start_time'] ?? null,
                    'end_date' => $item['end_date'] ?? null,
                    'end_time' => $item['end_time'] ?? null,
                    'expiration_date' => $item['expiration_date'] ?? null,
                    'expiration_time' => $item['expiration_time'] ?? null,
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
        $this->expireAttributesIfNeeded();

        $query = Attribute::query()
            ->with(['values' => fn ($q) => $q->orderBy('id')]);

        if ($request->boolean('with_categories')) {
            $query->with(['category:id,name', 'subcategory:id,name,parent_id']);
        }

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
        if ($request->filled('subcategory_id')) {
            $query->where('subcategory_id', $request->query('subcategory_id'));
        }
        if ($request->query('status')) {
            $status = $this->normalizeAttributeStatus($request->query('status'));
            $query->where('status', $status);
        }

        $attributes = $query
            ->orderByRaw('category_id IS NULL')
            ->orderBy('category_id')
            ->orderByDesc('sort_order')
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
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('parent_id', $request->input('category_id'))
                ),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'auto_expires' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['published', 'draft', 'expired'])],
            'values' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($data) {
            $status = $this->resolveAttributeStatusForSave(
                $data['status'] ?? 'published',
                $data['end_date'] ?? null,
                (bool) ($data['auto_expires'] ?? true)
            );
            $type = $data['type'];
            $categoryId = $type === 'offer' ? ($data['category_id'] ?? null) : null;
            $subcategoryId = $type === 'offer' && $categoryId ? ($data['subcategory_id'] ?? null) : null;
            $attribute = Attribute::create([
                'name' => $data['name'],
                'type' => $type,
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'auto_expires' => (bool) ($data['auto_expires'] ?? true),
                'sort_order' => $this->resolveAttributeSortOrder($data['sort_order'] ?? null),
                'status' => $status,
            ]);

            $values = $this->normalizeAttributeValues($data['values'] ?? []);
            if (!empty($values)) {
                $attribute->values()->createMany($values);
            }

            $attribute->load([
                'values' => fn ($q) => $q->orderBy('id'),
                'category:id,name',
                'subcategory:id,name,parent_id',
            ]);

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
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('parent_id', $request->input('category_id', $attribute->category_id))
                ),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'auto_expires' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(['published', 'draft', 'expired'])],
            'values' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($data, $attribute) {
            if (array_key_exists('name', $data)) {
                $attribute->name = $data['name'];
            }
            if (array_key_exists('type', $data)) {
                $attribute->type = $data['type'];
            }
            if (array_key_exists('category_id', $data)) {
                $attribute->category_id = $data['category_id'];
            }
            if (array_key_exists('subcategory_id', $data)) {
                $attribute->subcategory_id = $data['subcategory_id'];
            }
            if (($attribute->type ?? 'event') !== 'offer') {
                $attribute->category_id = null;
                $attribute->subcategory_id = null;
            } elseif (!$attribute->category_id) {
                $attribute->subcategory_id = null;
            }
            if (array_key_exists('sort_order', $data)) {
                $attribute->sort_order = (int) ($data['sort_order'] ?? 0);
            }
            $attribute->start_date = $data['start_date'];
            $attribute->end_date = $data['end_date'] ?? null;
            $attribute->auto_expires = (bool) ($data['auto_expires'] ?? true);
            if (array_key_exists('status', $data)) {
                $attribute->status = $this->resolveAttributeStatusForSave(
                    $data['status'],
                    $attribute->end_date,
                    (bool) $attribute->auto_expires
                );
            }
            $attribute->save();

            if (array_key_exists('values', $data)) {
                $values = $this->normalizeAttributeValues($data['values'] ?? []);
                $existingValues = $attribute->values()->get();
                $existingByKey = $existingValues->keyBy(
                    fn ($item) => $this->normalizeAttributeValueKey((string) ($item->value ?? ''))
                );
                $seen = [];
                $keepIds = [];

                foreach ($values as $valuePayload) {
                    $key = $this->normalizeAttributeValueKey((string) ($valuePayload['value'] ?? ''));
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $existing = $existingByKey->get($key);
                    if ($existing) {
                        $existing->update([
                            'value' => $valuePayload['value'],
                            'color_code' => $valuePayload['color_code'] ?? null,
                        ]);
                        $keepIds[] = $existing->id;
                        continue;
                    }

                    $created = $attribute->values()->create($valuePayload);
                    $keepIds[] = $created->id;
                }

                if (!empty($seen)) {
                    $attribute->values()
                        ->whereNotIn('id', $keepIds)
                        ->delete();
                } else {
                    $attribute->values()->delete();
                }
            }

            $attribute->load([
                'values' => fn ($q) => $q->orderBy('id'),
                'category:id,name',
                'subcategory:id,name,parent_id',
            ]);

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

    private function resolveAttributeStatusForSave(?string $status, $endDate, bool $autoExpires): string
    {
        $normalized = $this->normalizeAttributeStatus($status ?: 'published');
        if (
            $autoExpires &&
            $endDate &&
            $normalized === 'published' &&
            Carbon::parse($endDate, 'Asia/Dhaka')->lt(now('Asia/Dhaka')->startOfDay())
        ) {
            return 'expired';
        }

        return $normalized;
    }

    private function resolveAttributeSortOrder($sortOrder): int
    {
        $requestedOrder = (int) ($sortOrder ?? 0);
        if ($requestedOrder > 0) {
            return $requestedOrder;
        }

        return ((int) Attribute::max('sort_order')) + 1;
    }

    private function generateUniqueCouponCode(?string $couponName = null): string
    {
        $source = Str::lower(preg_replace('/[^a-zA-Z0-9]+/', '', (string) $couponName));
        $fallback = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $sourceCharacters = array_values(array_unique(str_split($source)));
        $pool = count($sourceCharacters) >= 6
            ? $sourceCharacters
            : array_values(array_unique(array_merge($sourceCharacters, str_split($fallback))));
        $sourceHasLetter = preg_match('/[a-z]/', $source) === 1;
        $sourceHasNumber = preg_match('/\d/', $source) === 1;

        do {
            $code = '';
            for ($index = 0; $index < 6; $index++) {
                $code .= $pool[random_int(0, count($pool) - 1)];
            }

            if ($sourceHasLetter && !preg_match('/[a-z]/', $code)) {
                $code[random_int(0, 5)] = $this->randomCharacterFromPattern($pool, '/[a-z]/');
            }
            if ($sourceHasNumber && !preg_match('/\d/', $code)) {
                $code[random_int(0, 5)] = $this->randomCharacterFromPattern($pool, '/\d/');
            }
        } while (CouponDetail::where('coupon', $code)->exists());

        return $code;
    }

    private function randomCharacterFromPattern(array $pool, string $pattern): string
    {
        $matches = array_values(array_filter($pool, fn ($character) => preg_match($pattern, $character) === 1));
        if (empty($matches)) {
            return $pool[random_int(0, count($pool) - 1)];
        }

        return $matches[random_int(0, count($matches) - 1)];
    }

    private function resolveOfferThumbnail($thumbnail, array $images): ?string
    {
        $thumbnailValue = is_string($thumbnail) ? trim($thumbnail) : '';
        if ($thumbnailValue !== '') {
            return $thumbnailValue;
        }

        foreach ($images as $image) {
            $imageValue = is_string($image) ? trim($image) : '';
            if ($imageValue !== '') {
                return $imageValue;
            }
        }

        return null;
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

    private function normalizeAreaSelection(array $data, string $table): array
    {
        $hasAreaIds = array_key_exists('area_ids', $data);
        $hasAreaId = array_key_exists('area_id', $data);

        if (!$hasAreaIds && !$hasAreaId) {
            return $data;
        }

        $areaIds = [];
        if ($hasAreaIds) {
            $areaIds = collect((array) ($data['area_ids'] ?? []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        if (empty($areaIds) && $hasAreaId && !empty($data['area_id'])) {
            $singleId = (int) $data['area_id'];
            if ($singleId > 0) {
                $areaIds = [$singleId];
            }
        }

        if (!Schema::hasColumn($table, 'area_ids')) {
            unset($data['area_ids']);
            $data['area_id'] = count($areaIds) > 0 ? $areaIds[0] : null;
            return $data;
        }

        $data['area_ids'] = $areaIds;
        $data['area_id'] = count($areaIds) > 0 ? $areaIds[0] : null;

        return $data;
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

    private function normalizeExpirationFields(array $data, $model = null): array
    {
        if (array_key_exists('expiration_date', $data) && $data['expiration_date']) {
            [$normalizedDate, $timeFromDate] = $this->extractDateAndTime((string) $data['expiration_date']);
            $data['expiration_date'] = $normalizedDate;
            if (empty($data['expiration_time']) && $timeFromDate !== null) {
                $data['expiration_time'] = $timeFromDate;
            }
        }

        if (array_key_exists('expiration_time', $data)) {
            $data['expiration_time'] = $this->normalizeTimeValue($data['expiration_time']);
        }

        $expirationDate = $data['expiration_date'] ?? $model?->expiration_date?->format('Y-m-d');
        if (!$expirationDate) {
            return $data;
        }

        $endDate = $data['end_date'] ?? $model?->end_date?->format('Y-m-d');
        if ($endDate && Carbon::parse($expirationDate)->startOfDay()->lt(Carbon::parse($endDate)->startOfDay())) {
            throw ValidationException::withMessages([
                'expiration_date' => 'Expiration date must be after or equal to end date.',
            ]);
        }

        return $data;
    }

    private function normalizeOfferRecurringFields(array $data): array
    {
        if (array_key_exists('is_recurring', $data)) {
            $data['is_recurring'] = filter_var($data['is_recurring'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        foreach (['recurring_start_date', 'recurring_end_date'] as $field) {
            if (!array_key_exists($field, $data) || empty($data[$field])) {
                continue;
            }

            [$normalizedDate] = $this->extractDateAndTime((string) $data[$field]);
            $data[$field] = $normalizedDate;
        }

        if (array_key_exists('recurring_days', $data)) {
            $rawRecurringDays = $this->normalizeJsonField($data['recurring_days']);
            $data['recurring_days'] = $this->normalizeRecurringDays(is_array($rawRecurringDays) ? $rawRecurringDays : []);
        }

        return $data;
    }

    private function validateOfferRecurringFields(array &$data, ?Offer $offer = null): void
    {
        $isRecurring = array_key_exists('is_recurring', $data)
            ? (bool) $data['is_recurring']
            : (bool) ($offer?->is_recurring);

        if (!$isRecurring) {
            $data['is_recurring'] = false;
            $data['recurring_start_date'] = null;
            $data['recurring_end_date'] = null;
            $data['recurring_days'] = [];
            return;
        }

        $startDate = $data['start_date'] ?? $offer?->start_date?->format('Y-m-d');
        $endDate = $data['end_date'] ?? $offer?->end_date?->format('Y-m-d');
        $recurringStart = $data['recurring_start_date'] ?? $offer?->recurring_start_date?->format('Y-m-d');
        $recurringEnd = $data['recurring_end_date'] ?? $offer?->recurring_end_date?->format('Y-m-d');
        $recurringDays = $this->normalizeRecurringDays($data['recurring_days'] ?? $offer?->recurring_days ?? []);

        $messages = [];

        if (!$recurringStart) {
            $messages['recurring_start_date'] = 'Recurring start date is required when recurring is enabled.';
        }

        if (!$recurringEnd) {
            $messages['recurring_end_date'] = 'Recurring end date is required when recurring is enabled.';
        }

        if ($recurringDays === []) {
            $messages['recurring_days'] = 'At least one recurring day is required when recurring is enabled.';
        }

        if (!$startDate || !$endDate) {
            $messages['start_date'] = 'Offer start date and end date are required for recurring offers.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        $offerStart = Carbon::parse($startDate)->startOfDay();
        $offerEnd = Carbon::parse($endDate)->startOfDay();
        $recurringStartDate = Carbon::parse($recurringStart)->startOfDay();
        $recurringEndDate = Carbon::parse($recurringEnd)->startOfDay();

        if ($recurringEndDate->lt($recurringStartDate)) {
            $messages['recurring_end_date'] = 'Recurring end date must be after or equal to recurring start date.';
        }

        if ($recurringStartDate->lt($offerStart)) {
            $messages['recurring_start_date'] = 'Recurring start date must be within the offer start and end dates.';
        }

        if ($recurringEndDate->gt($offerEnd)) {
            $messages['recurring_end_date'] = 'Recurring end date must be within the offer start and end dates.';
        }

        $invalidRecurringDays = array_diff($recurringDays, $this->allowedRecurringDays());
        if ($invalidRecurringDays !== []) {
            $messages['recurring_days'] = 'Recurring days must be valid weekday names.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        $data['is_recurring'] = true;
        $data['recurring_start_date'] = $recurringStartDate->format('Y-m-d');
        $data['recurring_end_date'] = $recurringEndDate->format('Y-m-d');
        $data['recurring_days'] = $recurringDays;
    }

    private function allowedRecurringDays(): array
    {
        return [
            'sunday',
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
        ];
    }

    private function normalizeRecurringDays(array $days): array
    {
        $normalized = [];

        foreach ($days as $day) {
            $value = strtolower(trim((string) $day));
            if ($value === '' || isset($normalized[$value])) {
                continue;
            }

            $normalized[$value] = true;
        }

        return array_keys($normalized);
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

    private function normalizeAttributeValueKey(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        return function_exists('mb_strtolower')
            ? mb_strtolower($trimmed, 'UTF-8')
            : strtolower($trimmed);
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

    private function syncOrganizationOfferContactDefaults(array $offerData): void
    {
        $organizationId = $offerData['organization_id'] ?? null;
        if (!$organizationId) {
            return;
        }

        $organization = User::query()
            ->where('id', $organizationId)
            ->where('role', 'organization')
            ->first();
        if (!$organization) {
            return;
        }

        $updates = $this->buildOrganizationContactUpdatesFromOffer($organization, $offerData);
        if (!empty($updates)) {
            $organization->update($updates);
        }
    }

    private function syncOrganizationEventContactDefaults(array $eventData): void
    {
        $organizationId = $eventData['organization_id'] ?? null;
        if (!$organizationId) {
            return;
        }

        $organization = User::query()
            ->where('id', $organizationId)
            ->where('role', 'organization')
            ->first();
        if (!$organization) {
            return;
        }

        $updates = $this->buildOrganizationContactUpdatesFromEvent($organization, $eventData);
        if (!empty($updates)) {
            $organization->update($updates);
        }
    }

    private function buildOrganizationContactUpdatesFromOffer(User $organization, array $offerData): array
    {
        $mapping = [
            'address' => 'address',
            'phone' => 'phone_number',
            'facebook_url' => 'facebook_url',
            'instagram_url' => 'instagram_url',
            'website_url' => 'website_url',
            'google_map_url' => 'google_map_url',
        ];

        $updates = [];
        foreach ($mapping as $userColumn => $offerColumn) {
            $currentValue = $organization->{$userColumn};
            $incomingValue = $this->normalizeNullableString($offerData[$offerColumn] ?? null);
            if ($incomingValue === null) {
                continue;
            }
            if ($this->isBlankValue($currentValue)) {
                $updates[$userColumn] = $incomingValue;
            }
        }

        return $updates;
    }

    private function buildOrganizationContactUpdatesFromEvent(User $organization, array $eventData): array
    {
        $mapping = [
            'address' => 'address',
            'phone' => 'phone_number',
            'facebook_url' => 'facebook_url',
            'instagram_url' => 'instagram_url',
        ];

        $updates = [];
        foreach ($mapping as $userColumn => $eventColumn) {
            $currentValue = $organization->{$userColumn};
            $incomingValue = $this->normalizeNullableString($eventData[$eventColumn] ?? null);
            if ($incomingValue === null) {
                continue;
            }
            if ($this->isBlankValue($currentValue)) {
                $updates[$userColumn] = $incomingValue;
            }
        }

        return $updates;
    }

    private function normalizeNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    private function isBlankValue($value): bool
    {
        return $this->normalizeNullableString($value) === null;
    }

    private function isSuperAdmin(Request $request): bool
    {
        return strtolower((string) ($request->user()?->role ?? '')) === 'superadmin';
    }

    private function promoteUserToAdmin(User $user): true|string
    {
        if (!in_array($user->role, ['user', 'organization', 'admin'], true)) {
            return 'Only user or organization accounts can be assigned as admin.';
        }

        $updates = ['role' => 'admin'];
        if (Schema::hasColumn('users', 'status')) {
            $updates['status'] = 'active';
        }

        $user->update($updates);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $user->syncRoles(['admin']);

        return true;
    }

    private function formatOrganization(User $user): array
    {
        return [
            'id' => $user->id,
            'parent_org_id' => $user->parent_org_id,
            'username' => $user->username,
            'email' => $user->email,
            'organization_name' => $user->organization_name,
            'organizationName' => $user->organization_name,
            'business_type' => $user->business_type,
            'public_subcategory' => $user->public_subcategory,
            'public_tag' => $user->public_tag,
            'is_verified' => (bool) $user->is_verified,
            'categories' => $user->categories ?? [],
            'subcategory_id' => $user->subcategory_id,
            'subcategory_ids' => $user->subcategory_ids ?? ($user->subcategory_id ? [$user->subcategory_id] : []),
            'businessType' => $user->business_type,
            'phone' => $user->phone,
            'whatsapp' => $user->whatsapp,
            'address' => $user->address,
            'about' => $user->about,
            'store_tags' => $user->store_tags ?? [],
            'avatar' => $user->avatar,
            'profile_banner' => $user->profile_banner,
            'interior_media' => $user->interior_media ?? [],
            'opening_hours' => $user->opening_hours,
            'business_hours' => $user->business_hours ?? [],
            'payment_methods' => $user->payment_methods ?? [],
            'facilities' => $user->facilities ?? [],
            'highlights' => $user->highlights ?? [],
            'catalog_sections' => $user->catalog_sections ?? [],
            'catalog_items' => $user->catalog_items ?? [],
            'facebook_url' => $user->facebook_url,
            'facebookUrl' => $user->facebook_url,
            'instagram_url' => $user->instagram_url,
            'instagramUrl' => $user->instagram_url,
            'website_url' => $user->website_url,
            'websiteUrl' => $user->website_url,
            'google_map_url' => $user->google_map_url,
            'googleMapUrl' => $user->google_map_url,
            'created_at' => $user->created_at,
            'createdAt' => $user->created_at,
        ];
    }

    private function validateOrganizationSubcategory(array $data): void
    {
        if (empty($data['subcategory_ids'])) return;

        $categoryName = trim((string) ($data['categories'][0] ?? ''));
        $subcategoryIds = array_values(array_unique(array_map('intval', $data['subcategory_ids'])));
        $valid = $categoryName !== '' && Category::query()
            ->whereKey($subcategoryIds)
            ->whereHas('parent', fn ($query) => $query->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($categoryName)]))
            ->count() === count($subcategoryIds);

        if (!$valid) {
            throw ValidationException::withMessages([
                'subcategory_ids' => ['Select valid subcategories for the selected category.'],
            ]);
        }
    }
}
