<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Offer;
use App\Models\Attribute;
use App\Models\User;
use App\Models\StorePost;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\OrganizationVerification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class OrganizationController extends Controller
{
    private const MEDIA_UPLOAD_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov', 'm4v', 'ogg', 'avi', 'mkv'];
    private const MEDIA_UPLOAD_MAX_BYTES = 20 * 1024 * 1024;

    public function submitVerification(Request $request)
    {
        abort_unless($request->user()?->role === 'organization', 403, 'Only organization accounts can submit verification.');

        $data = $request->validate([
            'owner_full_name' => ['required', 'string', 'max:120'],
            'owner_phone' => ['required', 'string', 'max:30'],
            'owner_email' => ['required', 'email', 'max:255'],
            'nid_no' => ['required', 'string', 'max:50'],
            'trade_license_no' => ['required', 'string', 'max:100'],
            'trade_license_valid_until' => ['required', 'date'],
            'organization_valid_until' => ['nullable', 'date'],
            'nid_front' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'nid_back' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'trade_license' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $organization = Organization::where('user_id', $request->user()->id)->firstOrFail();
        $storedPaths = [];

        try {
            foreach (['nid_front', 'nid_back', 'trade_license'] as $type) {
                $storedPaths[$type] = $request->file($type)->store(
                    "organization-documents/{$organization->id}",
                    'local'
                );
            }

            DB::transaction(function () use ($data, $request, $organization, $storedPaths) {
                $verification = OrganizationVerification::updateOrCreate(
                    ['organization_id' => $organization->id],
                    [
                        'owner_full_name' => trim($data['owner_full_name']),
                        'owner_phone' => trim($data['owner_phone']),
                        'owner_email' => trim($data['owner_email']),
                        'nid_no' => trim($data['nid_no']),
                        'trade_license_no' => trim($data['trade_license_no']),
                        'trade_license_valid_until' => $data['trade_license_valid_until'],
                        'organization_valid_until' => $data['organization_valid_until'] ?? now()->addYears(100)->toDateString(),
                        'status' => 'pending',
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'rejection_reason' => null,
                    ]
                );

                foreach ($storedPaths as $type => $path) {
                    $file = $request->file($type);
                    OrganizationDocument::updateOrCreate(
                        ['organization_id' => $organization->id, 'document_type' => $type],
                        [
                            'verification_id' => $verification->id,
                            'disk' => 'local',
                            'file_path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType(),
                            'file_size' => $file->getSize(),
                            'ocr_status' => 'not_started',
                            'ocr_data' => null,
                        ]
                    );
                }

                $organization->update(['verification_status' => 'pending', 'is_verified' => false]);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) Storage::disk('local')->delete($path);
            throw $exception;
        }

        return response()->json(['success' => true, 'message' => 'Verification documents submitted for administrator review.']);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        $totalEvents = Event::where('organization_id', $userId)->count();
        $publishedEvents = Event::where('organization_id', $userId)->where('status', 'published')->count();
        $upcomingEvents = Event::where('organization_id', $userId)->where('starting_date', '>', now())->count();
        $totalOffers = Offer::where('organization_id', $userId)->count();

        return response()->json([
            'success' => true,
            'stats' => [
                'totalEvents' => $totalEvents,
                'publishedEvents' => $publishedEvents,
                'upcomingEvents' => $upcomingEvents,
                'totalOffers' => $totalOffers,
            ],
        ]);
    }

    public function listPosts(Request $request)
    {
        return response()->json(['success' => true, 'posts' => StorePost::where('organization_id', $request->user()->id)
            ->orderByDesc('is_pinned')->orderBy('pin_order')->orderByDesc('created_at')->get()]);
    }

    public function storePost(Request $request)
    {
        $data = $this->validatePost($request);
        $data['organization_id'] = $request->user()->id;
        $this->ensureSingleMenuPost($request->user()->id, $data['type']);
        if ($data['type'] === 'menu') {
            $data['is_pinned'] = true;
            $data['pin_order'] = 0;
        }
        if (!empty($data['is_pinned'])) {
            $data['pin_order'] ??= ((int) StorePost::where('organization_id', $request->user()->id)->where('is_pinned', true)->max('pin_order')) + 1;
        }
        $post = StorePost::create($data);
        return response()->json(['success' => true, 'post' => $post], 201);
    }

    public function updatePost(Request $request, StorePost $post)
    {
        abort_unless((int) $post->organization_id === (int) $request->user()->id, 404);
        $data = $this->validatePost($request);
        $this->ensureSingleMenuPost($request->user()->id, $data['type'], $post->id);
        if ($data['type'] === 'menu') {
            $data['is_pinned'] = true;
            $data['pin_order'] = 0;
        }
        if (!empty($data['is_pinned']) && !$post->is_pinned) {
            $data['pin_order'] ??= ((int) StorePost::where('organization_id', $request->user()->id)->where('is_pinned', true)->max('pin_order')) + 1;
        } elseif (empty($data['is_pinned'])) {
            $data['pin_order'] = null;
        }
        $post->update($data);
        return response()->json(['success' => true, 'post' => $post->fresh()]);
    }

    public function deletePost(Request $request, StorePost $post)
    {
        abort_unless((int) $post->organization_id === (int) $request->user()->id, 404);
        $post->delete();
        return response()->json(['success' => true]);
    }

    private function validatePost(Request $request): array
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

    public function uploadPostMedia(Request $request)
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

    public function profile(Request $request)
    {
        $user = $request->user();
        $branches = $this->organizationBranches($user)->get()->map(fn (User $branch) => $this->formatOrganizationProfile($branch));

        return response()->json([
            'success' => true,
            'profile' => $this->formatOrganizationProfile($user),
            'branches' => $branches,
            'branch_candidates' => $this->branchCandidates($user)->get()->map(fn (User $store) => $this->formatOrganizationProfile($store)),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'organization_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'public_subcategory' => ['nullable', 'string', 'max:100'],
            'public_tag' => ['nullable', 'string', 'max:50'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:100'],
            'subcategory_id' => ['nullable', 'integer', 'exists:categories,id'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
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
            'business_hours.*.day' => ['required', 'string', 'max:12'],
            'business_hours.*.open' => ['nullable', 'date_format:H:i'],
            'business_hours.*.close' => ['nullable', 'date_format:H:i'],
            'business_hours.*.closed' => ['required', 'boolean'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', 'max:100'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', 'max:100'],
            'highlights' => ['nullable', 'array'],
            'highlights.*' => ['string', 'max:100'],
            'catalog_sections' => ['nullable', 'array'],
            'catalog_sections.*.title' => ['required', 'string', 'max:150'],
            'catalog_sections.*.type' => ['required', 'in:Menu,Products,Services'],
            'catalog_sections.*.order_no' => ['nullable', 'integer', 'min:1'],
            'catalog_sections.*.items' => ['nullable', 'array'],
            'catalog_sections.*.items.*.name' => ['required', 'string', 'max:150'],
            'catalog_sections.*.items.*.image' => ['nullable', 'string', 'max:500'],
            'catalog_sections.*.items.*.description' => ['nullable', 'string', 'max:500'],
            'catalog_sections.*.items.*.price' => ['nullable', 'string', 'max:60'],
            'catalog_sections.*.items.*.category' => ['nullable', 'string', 'max:100'],
            'catalog_sections.*.items.*.item_tag' => ['nullable', 'string', 'max:100'],
            'catalog_sections.*.items.*.item_tags' => ['nullable', 'array', 'max:20'],
            'catalog_sections.*.items.*.item_tags.*' => ['string', 'max:100'],
            'catalog_sections.*.items.*.tag_colors' => ['nullable', 'array'],
            'catalog_sections.*.items.*.tag_colors.*' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'catalog_sections.*.items.*.tag_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'catalog_items' => ['nullable', 'array'],
            'catalog_items.*.type' => ['required', Rule::in(['Menu', 'Product', 'Service'])],
            'catalog_items.*.name' => ['required', 'string', 'max:150'],
            'catalog_items.*.description' => ['nullable', 'string', 'max:500'],
            'catalog_items.*.price' => ['nullable', 'string', 'max:60'],
            'catalog_items.*.category' => ['nullable', 'string', 'max:100'],
            'catalog_items.*.item_tag' => ['nullable', 'string', 'max:100'],
            'catalog_items.*.item_tags' => ['nullable', 'array', 'max:20'],
            'catalog_items.*.item_tags.*' => ['string', 'max:100'],
            'catalog_items.*.tag_colors' => ['nullable', 'array'],
            'catalog_items.*.tag_colors.*' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'catalog_items.*.tag_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'catalog_items.*.section_name' => ['nullable', 'string', 'max:150'],
            'catalog_items.*.section_order' => ['nullable', 'integer', 'min:1'],
            'catalog_items.*.order_no' => ['nullable', 'integer', 'min:1'],
            'catalog_items.*.is_pinned' => ['nullable', 'boolean'],
            'catalog_items.*.media' => ['nullable', 'array', 'max:20'],
            'catalog_items.*.media.*.url' => ['required', 'string', 'max:500'],
            'catalog_items.*.media.*.type' => ['required', Rule::in(['image', 'video'])],
        ]);

        $subcategoryIds = array_values(array_unique(array_map('intval', $data['subcategory_ids'] ?? array_filter([$data['subcategory_id'] ?? null]))));
        if (!empty($subcategoryIds)) {
            $categoryName = trim((string) ($data['categories'][0] ?? ''));
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

        if (array_key_exists('subcategory_ids', $data)) {
            $data['subcategory_ids'] = $subcategoryIds;
            $data['subcategory_id'] = $subcategoryIds[0] ?? null;
        }

        $request->user()->update($data);

        return response()->json([
            'success' => true,
            'profile' => $this->formatOrganizationProfile($request->user()->fresh()),
        ]);
    }

    public function uploadProfileImage(Request $request)
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

    public function syncBranches(Request $request)
    {
        $parent = $request->user();
        if ($parent->parent_org_id !== null) {
            return response()->json(['error' => 'Only parent stores can manage branches.'], 422);
        }

        $data = $request->validate([
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer'],
        ]);

        $branchIds = collect($data['branch_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== (int) $parent->id)
            ->unique()
            ->values();

        $validBranchIds = $this->branchCandidates($parent)
            ->whereIn('id', $branchIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($validBranchIds->count() !== $branchIds->count()) {
            return response()->json(['error' => 'One or more selected stores cannot be linked as branches.'], 422);
        }

        User::query()
            ->where('role', 'organization')
            ->where('parent_org_id', $parent->id)
            ->whereNotIn('id', $validBranchIds)
            ->update(['parent_org_id' => null]);

        User::query()
            ->where('role', 'organization')
            ->whereIn('id', $validBranchIds)
            ->update(['parent_org_id' => $parent->id]);

        $branches = $this->organizationBranches($parent)->get()->map(fn (User $branch) => $this->formatOrganizationProfile($branch));

        return response()->json([
            'success' => true,
            'branches' => $branches,
            'branch_candidates' => $this->branchCandidates($parent)->get()->map(fn (User $store) => $this->formatOrganizationProfile($store)),
        ]);
    }

    public function removeBranch(Request $request, User $branch)
    {
        $parent = $request->user();
        if ($branch->role !== 'organization' || (int) $branch->parent_org_id !== (int) $parent->id) {
            return response()->json(['error' => 'Branch not found.'], 404);
        }

        $branch->parent_org_id = null;
        $branch->save();

        return response()->json(['success' => true]);
    }

    private function formatOrganizationProfile($user): array
    {
        return [
            'id' => $user->id,
            'parent_org_id' => $user->parent_org_id,
            'username' => $user->username,
            'organization_name' => $user->organization_name,
            'organizationName' => $user->organization_name,
            'business_type' => $user->business_type,
            'public_subcategory' => $user->public_subcategory,
            'public_tag' => $user->public_tag,
            'is_verified' => (bool) $user->is_verified,
            'categories' => $user->categories ?? [],
            'subcategory_id' => $user->subcategory_id,
            'phone' => $user->phone,
            'whatsapp' => $user->whatsapp,
            'email' => $user->email,
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
            'instagram_url' => $user->instagram_url,
            'website_url' => $user->website_url,
            'google_map_url' => $user->google_map_url,
            'follower_count' => (int) ($user->follower_count ?? 0),
            'rating_average' => (float) ($user->rating_average ?? 0),
            'review_count' => (int) ($user->review_count ?? 0),
        ];
    }

    private function organizationBranches(User $user)
    {
        return User::query()
            ->where('role', 'organization')
            ->where('parent_org_id', $user->id)
            ->orderBy('organization_name')
            ->orderBy('id');
    }

    private function branchCandidates(User $user)
    {
        return User::query()
            ->where('role', 'organization')
            ->whereKeyNot($user->id)
            ->where(function ($query) use ($user) {
                $query->whereNull('parent_org_id')
                    ->orWhere('parent_org_id', $user->id);
            })
            ->orderBy('organization_name')
            ->orderBy('id');
    }

    public function categories(Request $request)
    {
        $categories = Category::query()
            ->where('status', 'active')
            ->when($request->query('type') === 'event', fn ($query) => $query->where('is_event_category', true))
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'is_event_category']);

        return response()->json(['success' => true, 'categories' => $categories]);
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
        if ($request->filled('subcategory_id')) {
            $query->where('subcategory_id', $request->query('subcategory_id'));
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

    public function listEvents(Request $request)
    {
        $userId = $request->user()->id;
        $query = Event::query()
            ->where('organization_id', $userId)
            ->with(['category:id,name', 'area:id,name'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('location', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at');

        $events = $query->paginate((int)$request->query('limit', 10));
        return response()->json($events);
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
            'starting_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['required', 'date', 'after:starting_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('parent_id', $request->input('category_id')))],
            'create_post' => ['nullable', 'boolean'],
            'pin_event' => ['nullable', 'boolean'],
        ]);

        $createPost = (bool) ($data['create_post'] ?? false);
        $pinEvent = (bool) ($data['pin_event'] ?? false);
        unset($data['create_post'], $data['pin_event']);

        $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order'] ?? []);
        if (!is_array($gallerySortOrder)) {
            $gallerySortOrder = [];
        }

        $data = $this->normalizeDateAndTimeFields($data, 'starting_date');

        [$event, $post] = DB::transaction(function () use ($data, $gallerySortOrder, $request, $createPost, $pinEvent) {
            $banner = $this->toArrayField($data['banner'] ?? []);
            $event = Event::create([
                ...$data,
                'banner' => $banner,
                'gallery_sort_order' => $gallerySortOrder,
                'attributes' => $this->normalizeAttributes($data['attributes'] ?? []),
                'created_by' => $request->user()->id,
                'organization_id' => $request->user()->id,
            ]);

            $post = null;
            if ($createPost) {
                $media = collect($banner)->map(fn ($url) => [
                    'url' => $url,
                    'type' => preg_match('/\.(mp4|webm|mov|m4v|ogg)(?:\?.*)?$/i', (string) $url) ? 'video' : 'image',
                    'caption' => null,
                ])->values()->all();
                $pinOrder = $pinEvent
                    ? ((int) StorePost::where('organization_id', $request->user()->id)->where('is_pinned', true)->max('pin_order')) + 1
                    : null;
                $post = StorePost::create([
                    'organization_id' => $request->user()->id,
                    'type' => 'event',
                    'source_id' => $event->id,
                    'title' => $event->name,
                    'description' => $event->description,
                    'image' => $event->thumbnail ?: ($banner[0] ?? null),
                    'media' => $media,
                    'is_pinned' => $pinEvent,
                    'pin_order' => $pinOrder,
                ]);
            }

            return [$event, $post];
        });

        return response()->json(['success' => true, 'event' => $event, 'post' => $post], 201);
    }

    public function updateEvent(Request $request, Event $event)
    {
        if ($event->organization_id !== $request->user()->id) {
            return response()->json(['error' => 'You are not allowed to manage this event.'], 403);
        }

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
            'end_date' => ['nullable', 'date', 'after:starting_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('parent_id', $request->input('category_id', $event->category_id)))],
        ]);

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
        $data = $this->normalizeDateAndTimeFields($data, 'starting_date');

        $event->update($data);
        return response()->json(['success' => true, 'event' => $event]);
    }

    public function deleteEvent(Request $request, Event $event)
    {
        if ($event->organization_id !== $request->user()->id) {
            return response()->json(['error' => 'You are not allowed to manage this event.'], 403);
        }

        $event->delete();
        return response()->json(['success' => true]);
    }

    public function uploadBanner(Request $request)
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
            Log::error('Organization event banner upload failed', ['error' => $exception->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to store uploaded media.'], 500);
        }
    }

    public function uploadThumbnail(Request $request)
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
        $userId = $request->user()->id;
        $query = Offer::query()
            ->where('organization_id', $userId)
            ->with(['category:id,name', 'event:id,name', 'area:id,name'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('details', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at');

        $offers = $query->paginate((int)$request->query('limit', 10));
        return response()->json($offers);
    }

    public function storeOffer(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
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
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('parent_id', $request->input('category_id')))],
            'event_id' => ['nullable', 'exists:events,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive', 'expired'])],
            'create_post' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $createPost = (bool) ($data['create_post'] ?? false);
        $isPinned = (bool) ($data['is_pinned'] ?? false);
        unset($data['create_post'], $data['is_pinned']);

        $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order'] ?? []);
        if (!is_array($gallerySortOrder)) {
            $gallerySortOrder = [];
        }

        [$offer, $post] = DB::transaction(function () use ($data, $gallerySortOrder, $request, $createPost, $isPinned) {
            $images = $this->toArrayField($data['images'] ?? []);
            $videos = $this->toArrayField($data['videos'] ?? []);
            $offer = Offer::create([
                ...$data,
                'images' => $images,
                'gallery_sort_order' => $gallerySortOrder,
                'videos' => $videos,
                'attributes' => $this->normalizeAttributes($data['attributes'] ?? []),
                'created_by' => $request->user()->id,
                'organization_id' => $request->user()->id,
            ]);

            $post = null;
            if ($createPost) {
                $mediaUrls = collect([$offer->thumbnail, $offer->cover])
                    ->merge($images)
                    ->filter()
                    ->unique()
                    ->map(fn ($url) => ['url' => $url, 'type' => 'image', 'caption' => null]);
                $media = $mediaUrls->merge(collect($videos)->filter()->unique()->map(
                    fn ($url) => ['url' => $url, 'type' => 'video', 'caption' => null]
                ))->values()->all();
                $pinOrder = $isPinned
                    ? ((int) StorePost::where('organization_id', $request->user()->id)->where('is_pinned', true)->max('pin_order')) + 1
                    : null;

                $post = StorePost::create([
                    'organization_id' => $request->user()->id,
                    'type' => 'offer',
                    'source_id' => $offer->id,
                    'title' => $offer->name,
                    'description' => $offer->details,
                    'image' => $offer->thumbnail ?: $offer->cover,
                    'media' => $media,
                    'is_pinned' => $isPinned,
                    'pin_order' => $pinOrder,
                ]);
            }

            return [$offer, $post];
        });

        return response()->json(['success' => true, 'offer' => $offer, 'post' => $post], 201);
    }

    public function storeOfferWithPost(Request $request)
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            // Offer fields
            'name'             => ['required', 'string', 'max:200'],
            'details'          => ['nullable', 'string'],
            'start_date'       => ['required', 'date'],
            'end_date'         => ['required', 'date', 'after:start_date'],
            'address'          => ['nullable', 'string', 'max:255'],
            'phone_number'     => ['nullable', 'string', 'max:50'],
            'facebook_url'     => ['nullable', 'string', 'max:500'],
            'instagram_url'    => ['nullable', 'string', 'max:500'],
            'website_url'      => ['nullable', 'string', 'max:500'],
            'google_map_url'   => ['nullable', 'string', 'max:500'],
            'discount_type'    => ['nullable', Rule::in(['percentage', 'flat', 'bogo', 'custom'])],
            'discount_value'   => ['nullable', 'numeric', 'min:0'],
            'thumbnail'        => ['nullable', 'string', 'max:500'],
            'cover'            => ['nullable', 'string', 'max:500'],
            'images'           => ['nullable'],
            'gallery_sort_order' => ['nullable'],
            'videos'           => ['nullable'],
            'attributes'       => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.value_ids'    => ['nullable', 'array'],
            'attributes.*.value_ids.*'  => ['integer', 'exists:attribute_values,id'],
            'category_id'      => ['nullable', 'exists:categories,id'],
            'subcategory_id'   => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('parent_id', $request->input('category_id')))],
            'event_id'         => ['nullable', 'exists:events,id'],
            'area_id'          => ['nullable', 'exists:areas,id'],
            'status'           => ['nullable', Rule::in(['draft', 'active', 'inactive', 'expired'])],
            // Post fields
            'post_title'       => ['required', 'string', 'max:180'],
            'post_description' => ['nullable', 'string', 'max:5000'],
            'post_image'       => ['nullable', 'string', 'max:500'],
            'post_media'       => ['nullable', 'array', 'max:20'],
            'post_media.*.url'     => ['required', 'string', 'max:500'],
            'post_media.*.type'    => ['required', Rule::in(['image', 'video'])],
            'post_media.*.caption' => ['nullable', 'string', 'max:500'],
            'is_pinned'        => ['nullable', 'boolean'],
        ]);

        $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order'] ?? []);
        if (!is_array($gallerySortOrder)) {
            $gallerySortOrder = [];
        }

        $result = DB::transaction(function () use ($data, $gallerySortOrder, $userId) {
            $offer = Offer::create([
                'name'             => $data['name'],
                'details'          => $data['details'] ?? null,
                'start_date'       => $data['start_date'],
                'end_date'         => $data['end_date'],
                'address'          => $data['address'] ?? null,
                'phone_number'     => $data['phone_number'] ?? null,
                'facebook_url'     => $data['facebook_url'] ?? null,
                'instagram_url'    => $data['instagram_url'] ?? null,
                'website_url'      => $data['website_url'] ?? null,
                'google_map_url'   => $data['google_map_url'] ?? null,
                'discount_type'    => $data['discount_type'] ?? null,
                'discount_value'   => isset($data['discount_value']) ? (float) $data['discount_value'] : null,
                'thumbnail'        => $data['thumbnail'] ?? null,
                'cover'            => $data['cover'] ?? null,
                'images'           => $this->toArrayField($data['images'] ?? []),
                'gallery_sort_order' => $gallerySortOrder,
                'videos'           => $this->toArrayField($data['videos'] ?? []),
                'attributes'       => $this->normalizeAttributes($data['attributes'] ?? []),
                'category_id'      => $data['category_id'] ?? null,
                'subcategory_id'   => $data['subcategory_id'] ?? null,
                'event_id'         => $data['event_id'] ?? null,
                'area_id'          => $data['area_id'] ?? null,
                'status'           => $data['status'] ?? 'active',
                'created_by'       => $userId,
                'organization_id'  => $userId,
            ]);

            $isPinned = !empty($data['is_pinned']);
            $pinOrder = null;
            if ($isPinned) {
                $pinOrder = ((int) StorePost::where('organization_id', $userId)
                    ->where('is_pinned', true)
                    ->max('pin_order')) + 1;
            }

            $post = StorePost::create([
                'organization_id' => $userId,
                'type'            => 'offer',
                'source_id'       => $offer->id,
                'title'           => $data['post_title'],
                'description'     => $data['post_description'] ?? null,
                'image'           => $data['post_image'] ?? null,
                'media'           => $data['post_media'] ?? null,
                'is_pinned'       => $isPinned,
                'pin_order'       => $pinOrder,
            ]);

            return compact('offer', 'post');
        });

        $post = $result['post'];
        $offer = $result['offer'];

        return response()->json([
            'success' => true,
            'offer'   => $offer,
            'post'    => array_merge($post->toArray(), ['post_type' => 'offer']),
        ], 201);
    }

    public function updateOffer(Request $request, Offer $offer)
    {
        if ($offer->organization_id !== $request->user()->id) {
            return response()->json(['error' => 'You are not allowed to manage this offer.'], 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'details' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
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
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('parent_id', $request->input('category_id', $offer->category_id)))],
            'event_id' => ['nullable', 'exists:events,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive', 'expired'])],
        ]);

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

        $offer->update($data + ['updated_by' => $request->user()->id]);
        return response()->json(['success' => true, 'offer' => $offer->fresh()]);
    }

    public function deleteOffer(Request $request, Offer $offer)
    {
        if ($offer->organization_id !== $request->user()->id) {
            return response()->json(['error' => 'You are not allowed to manage this offer.'], 403);
        }

        $offer->delete();
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
            Log::error('Organization offer media upload failed', ['error' => $exception->getMessage()]);
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
}
