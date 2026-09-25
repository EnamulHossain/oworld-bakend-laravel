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
            'owner_full_name' => ['nullable', 'string', 'max:120'],
            'owner_phone' => ['nullable', 'string', 'max:30'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'nid_no' => ['nullable', 'string', 'max:50'],
            'trade_license_no' => ['nullable', 'string', 'max:100'],
            'trade_license_valid_until' => ['nullable', 'date'],
            'organization_valid_until' => ['nullable', 'date'],
            'established_date' => ['nullable', 'date', 'before_or_equal:today'],
            'bin_vat_no' => ['nullable', 'string', 'max:100'],
            'tin_no' => ['nullable', 'string', 'max:100'],
            'business_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'supporting_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'nid_front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'nid_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'trade_license' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $organization = Organization::where('user_id', $request->user()->id)->firstOrFail();
        $storedPaths = [];

        try {
            foreach (['nid_front', 'nid_back', 'trade_license', 'business_photo', 'supporting_document'] as $type) {
                if (!$request->hasFile($type)) continue;
                $storedPaths[$type] = $request->file($type)->store(
                    "organization-documents/{$organization->id}",
                    'local'
                );
            }

            DB::transaction(function () use ($data, $request, $organization, $storedPaths) {
                $verification = OrganizationVerification::updateOrCreate(
                    ['organization_id' => $organization->id],
                    [
                        'established_date' => $data['established_date'] ?? null,
                        'bin_vat_no' => $data['bin_vat_no'] ?? null,
                        'tin_no' => $data['tin_no'] ?? null,
                        'owner_full_name' => isset($data['owner_full_name']) ? trim($data['owner_full_name']) : null,
                        'owner_phone' => isset($data['owner_phone']) ? trim($data['owner_phone']) : null,
                        'owner_email' => isset($data['owner_email']) ? trim($data['owner_email']) : null,
                        'nid_no' => isset($data['nid_no']) ? trim($data['nid_no']) : null,
                        'trade_license_no' => isset($data['trade_license_no']) ? trim($data['trade_license_no']) : null,
                        'trade_license_valid_until' => $data['trade_license_valid_until'] ?? null,
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
        $branchFamily = $this->branchFamily($user)->map(fn (User $branch) => $this->formatOrganizationProfile($branch));

        return response()->json([
            'success' => true,
            'profile' => $this->formatOrganizationProfile($user),
            'branches' => $branches,
            'branch_family' => $branchFamily,
            'is_parent_branch' => $user->parent_org_id === null,
            'parent_branch_id' => $user->parent_org_id,
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
            'store_filters' => ['nullable', 'array'],
            'store_filters.*.attribute_id' => ['required', 'integer', 'distinct', 'exists:attributes,id'],
            'store_filters.*.value_ids' => ['nullable', 'array'],
            'store_filters.*.value_ids.*' => ['integer', 'distinct', 'exists:attribute_values,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
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
            'contact_email' => ['nullable', 'email', 'max:255'],
            'messenger_url' => ['nullable', 'url:http,https', 'max:500'],
            'tiktok_url' => ['nullable', 'url:http,https', 'max:500'],
            'linkedin_url' => ['nullable', 'url:http,https', 'max:500'],
            'youtube_url' => ['nullable', 'url:http,https', 'max:500'],
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
            $categoryNames = array_values(array_filter(array_map(fn ($name) => Str::lower(trim((string) $name)), $data['categories'] ?? [])));
            $valid = !empty($categoryNames) && Category::query()
                ->whereKey($subcategoryIds)
                ->whereHas('parent', fn ($query) => $query->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(name))'), $categoryNames))
                ->count() === count($subcategoryIds);
            if (!$valid) {
                throw ValidationException::withMessages([
                    'subcategory_ids' => ['Select valid subcategories for the selected categories.'],
                ]);
            }
        }

        if (array_key_exists('subcategory_ids', $data)) {
            $data['subcategory_ids'] = $subcategoryIds;
            $data['subcategory_id'] = $subcategoryIds[0] ?? null;
        }

        if (array_key_exists('store_filters', $data)) {
            $data['store_filters'] = $this->normalizeAttributes($data['store_filters']);
        }

        $request->user()->update($data);
        if (array_key_exists('area_id', $data)) {
            Offer::where('organization_id', $request->user()->id)->update(['area_id' => $data['area_id']]);
        }

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
            'subcategory_ids' => $user->subcategory_ids ?? [],
            'store_filters' => $user->store_filters ?? [],
            'phone' => $user->phone,
            'whatsapp' => $user->whatsapp,
            'email' => $user->email,
            'contact_email' => $user->contact_email,
            'messenger_url' => $user->messenger_url,
            'tiktok_url' => $user->tiktok_url,
            'linkedin_url' => $user->linkedin_url,
            'youtube_url' => $user->youtube_url,

            'address' => $user->address,
            'area_id' => $user->area_id,
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

    private function branchFamily(User $user)
    {
        $parentId = (int) ($user->parent_org_id ?: $user->id);
        return User::query()
            ->where('role', 'organization')
            ->where(function ($query) use ($parentId) {
                $query->whereKey($parentId)->orWhere('parent_org_id', $parentId);
            })
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$parentId])
            ->orderBy('organization_name')
            ->get();
    }

    private function resolveOfferBranchAssignment(User $user, array $requestedIds): array
    {
        $branchIds = collect($requestedIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        if ($branchIds->isEmpty()) {
            $branchIds = collect([(int) $user->id]);
        }
        $familyIds = $this->branchFamily($user)->pluck('id')->map(fn ($id) => (int) $id);
        if ($branchIds->diff($familyIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['branch_ids' => ['Select branches belonging to this store family only.']]);
        }
        $isParent = $user->parent_org_id === null;
        return [
            'branch_ids' => $branchIds->all(),
            'branch_assignment_status' => $isParent ? 'approved' : 'pending',
            'branch_requested_by' => $user->id,
            'branch_approved_by' => $isParent ? $user->id : null,
            'branch_approved_at' => $isParent ? now() : null,
        ];
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
        return $this->saveEventAndPost($request);
    }

    public function updateEvent(Request $request, Event $event)
    {
        return $this->saveEventAndPost($request, $event);
    }

    private function saveEventAndPost(Request $request, ?Event $event = null)
    {
        $actor = $request->user();
        $isAdmin = in_array($actor->role, ['admin', 'superAdmin'], true);
        abort_unless($isAdmin || $actor->role === 'organization', 403);
        if (!$isAdmin && $event && (int) $event->organization_id !== (int) $actor->id) abort(403);
        $storeId = $isAdmin ? $request->input('organization_id', $event?->organization_id) : $actor->id;
        $store = User::where('role', 'organization')->find($storeId);
        if (!$store) throw ValidationException::withMessages(['organization_id' => ['Select a valid store.']]);
        $request->merge(['organization_id' => $store->id]);
        $extra = $request->validate([
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:categories,id'],
            'create_post' => ['nullable', 'boolean'],
            'pin_event' => ['nullable', 'boolean'],
        ]);
        $categoryIds = array_map('intval', $extra['category_ids'] ?? (
            $request->has('category_id') && (string) $request->input('category_id') !== (string) $event?->category_id
                ? array_filter([$request->input('category_id')]) : ($event?->category_ids ?: array_filter([$request->input('category_id', $event?->category_id)]))
        ));
        $subcategoryIds = array_map('intval', $extra['subcategory_ids'] ?? (
            $request->has('subcategory_id') && (string) $request->input('subcategory_id') !== (string) $event?->subcategory_id
                ? array_filter([$request->input('subcategory_id')]) : ($event?->subcategory_ids ?: array_filter([$request->input('subcategory_id', $event?->subcategory_id)]))
        ));
        if (Category::whereIn('id', $subcategoryIds)->whereIn('parent_id', $categoryIds)->count() !== count($subcategoryIds)) {
            throw ValidationException::withMessages(['subcategory_ids' => ['Choose subcategories belonging to the selected categories.']]);
        }
        // Validate all parent relationships above rather than only the first category.
        $request->merge(['category_id' => $categoryIds[0] ?? null, 'subcategory_id' => null]);
        if ($request->input('status') === 'cancelled') $request->merge(['status' => 'canceled']);
        if ($request->input('status') === 'completed') $request->merge(['status' => 'expired']);

        return DB::transaction(function () use ($request, $event, $store, $extra, $categoryIds, $subcategoryIds) {
            if ($event) $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $writer = app(AdminController::class);
            $response = $event ? $writer->updateEvent($request, $event) : $writer->storeEvent($request);
            $saved = Event::findOrFail($response->getData(true)['event']['id']);
            $saved->update(['category_ids' => $categoryIds, 'subcategory_ids' => $subcategoryIds, 'subcategory_id' => $subcategoryIds[0] ?? null]);
            $post = StorePost::where('type', 'event')->where('source_id', $saved->id)->first();
            $media = collect($saved->banner ?? [])->filter()->unique()->map(fn ($url) => [
                'url' => $url, 'type' => preg_match('/\.(mp4|webm|mov|m4v|ogg)(?:\?.*)?$/i', (string) $url) ? 'video' : 'image', 'caption' => null,
            ])->values()->all();
            $pinned = $extra['pin_event'] ?? $post?->is_pinned ?? false;
            $sameStore = $post && (int) $post->organization_id === (int) $store->id;
            $pinOrder = $pinned ? (($sameStore ? $post->pin_order : null) ?? ((int) StorePost::where('organization_id', $store->id)->max('pin_order') + 1)) : null;
            $post = StorePost::updateOrCreate(['type' => 'event', 'source_id' => $saved->id], [
                'organization_id' => $store->id, 'title' => $saved->name,
                'description' => $saved->description, 'image' => $saved->thumbnail ?: collect($media)->firstWhere('type', 'image')['url'] ?? null,
                'media' => $media, 'is_pinned' => $pinned, 'pin_order' => $pinOrder,
            ]);
            return response()->json(['success' => true, 'event' => $saved->fresh(), 'post' => $post], $event ? 200 : 201);
        });
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
        return $this->saveOfferAndPost($request);
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
            'branch_ids'        => ['nullable', 'array'],
            'branch_ids.*'      => ['integer', 'distinct', 'exists:users,id'],
            'category_id'      => ['nullable', 'exists:categories,id'],
            'subcategory_id'   => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('parent_id', $request->input('category_id')))],
            'category_ids'     => ['nullable', 'array'],
            'category_ids.*'   => ['integer', 'distinct', 'exists:categories,id'],
            'subcategory_ids'   => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'event_id'         => ['nullable', 'exists:events,id'],
            'area_id'          => ['nullable', 'exists:areas,id'],
            'status'           => ['nullable', Rule::in(['draft', 'scheduled', 'published', 'expired', 'archived', 'cancelled'])],
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

        // Offer posts inherit their area from the store profile (About section).
        $data['area_id'] = $request->user()->area_id;

        $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order'] ?? []);
        if (!is_array($gallerySortOrder)) {
            $gallerySortOrder = [];
        }

        $branchAssignment = $this->resolveOfferBranchAssignment($request->user(), $data['branch_ids'] ?? []);

        $result = DB::transaction(function () use ($data, $gallerySortOrder, $userId, $branchAssignment) {
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
                ...$branchAssignment,
                'category_id'      => ($data['category_ids'] ?? [])[0] ?? ($data['category_id'] ?? null),
                'subcategory_id'   => ($data['subcategory_ids'] ?? [])[0] ?? ($data['subcategory_id'] ?? null),
                'category_ids'     => array_values($data['category_ids'] ?? array_filter([$data['category_id'] ?? null])),
                'subcategory_ids'  => array_values($data['subcategory_ids'] ?? array_filter([$data['subcategory_id'] ?? null])),
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
        return $this->saveOfferAndPost($request, $offer);
    }

    private function saveOfferAndPost(Request $request, ?Offer $offer = null)
    {
        $actor = $request->user();
        $isAdmin = in_array($actor->role, ['admin', 'superAdmin'], true);
        abort_unless($isAdmin || $actor->role === 'organization', 403);
        if (!$isAdmin && $offer && (int) $offer->organization_id !== (int) $actor->id) abort(403);

        $storeId = $isAdmin ? $request->input('organization_id', $offer?->organization_id) : $actor->id;
        $store = User::where('role', 'organization')->find($storeId);
        if (!$store) throw ValidationException::withMessages(['organization_id' => ['Select a valid store.']]);
        $request->merge(['organization_id' => $store->id]);
        $request->merge(['area_id' => $store->area_id, 'area_ids' => $store->area_id ? [$store->area_id] : []]);
        if ($request->has('is_exclusive') && !$request->has('offer_type')) {
            $request->merge(['offer_type' => $request->boolean('is_exclusive') ? 'exclusive' : 'regular']);
        }
        $extra = $request->validate([
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:categories,id'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.value_ids' => ['nullable', 'array'],
            'attributes.*.value_ids.*' => ['integer', 'distinct', 'exists:attribute_values,id'],
            'create_post' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);
        $categoryIds = array_map('intval', $extra['category_ids'] ?? ($request->has('category_id') ? array_filter([$request->input('category_id')]) : ($offer?->category_ids ?: array_filter([$offer?->category_id]))));
        $subcategoryIds = array_map('intval', $extra['subcategory_ids'] ?? ($request->has('subcategory_id') ? array_filter([$request->input('subcategory_id')]) : ($offer?->subcategory_ids ?: array_filter([$offer?->subcategory_id]))));
        $allowedCategoryIds = Category::whereNull('parent_id')->where('status', 'active')
            ->whereIn('name', $store->categories ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allowedSubcategoryIds = array_map('intval', $store->subcategory_ids ?? array_filter([$store->subcategory_id]));
        if (array_diff($categoryIds, $allowedCategoryIds)) {
            throw ValidationException::withMessages(['category_ids' => ['Choose categories assigned to this store in About.']]);
        }
        if (array_diff($subcategoryIds, $allowedSubcategoryIds)
            || Category::whereIn('id', $subcategoryIds)->whereIn('parent_id', $categoryIds)->where('status', 'active')->count() !== count($subcategoryIds)) {
            throw ValidationException::withMessages(['subcategory_ids' => ['Choose subcategories assigned to this store and the selected categories.']]);
        }
        foreach ($extra['attributes'] ?? [] as $selection) {
            $storeSelection = collect($store->store_filters ?? [])->first(fn ($filter) => (int) $filter['attribute_id'] === (int) $selection['attribute_id']);
            if (!$storeSelection || array_diff(array_map('intval', $selection['value_ids'] ?? []), array_map('intval', $storeSelection['value_ids'] ?? []))) {
                throw ValidationException::withMessages(['attributes' => ['Choose only filters and values selected in the store profile.']]);
            }
            $attribute = Attribute::find($selection['attribute_id'] ?? null);
            if (!$attribute || !in_array((int) $attribute->category_id, $categoryIds, true)
                || ($attribute->subcategory_id && !in_array((int) $attribute->subcategory_id, $subcategoryIds, true))
                || $attribute->values()->whereIn('id', $selection['value_ids'] ?? [])->count() !== count(array_unique($selection['value_ids'] ?? []))) {
                throw ValidationException::withMessages(['attributes' => ['Choose filters and values belonging to the selected subcategories.']]);
            }
        }
        $branchAssignment = $this->resolveOfferBranchAssignment($store, $extra['branch_ids'] ?? ($offer?->branch_ids ?? []));
        if (!$isAdmin && $offer && collect($branchAssignment['branch_ids'])->sort()->values()->all() === collect($offer->branch_ids ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all()) {
            $branchAssignment = array_merge($branchAssignment, [
                'branch_assignment_status' => $offer->branch_assignment_status,
                'branch_requested_by' => $offer->branch_requested_by,
                'branch_approved_by' => $offer->branch_approved_by,
                'branch_approved_at' => $offer->branch_approved_at,
            ]);
        }
        if ($isAdmin) {
            $branchAssignment = array_merge($branchAssignment, [
                'branch_assignment_status' => 'approved', 'branch_requested_by' => $actor->id,
                'branch_approved_by' => $actor->id, 'branch_approved_at' => now(),
            ]);
        }
        $request->merge(['category_id' => $categoryIds[0] ?? null]);

        return DB::transaction(function () use ($request, $offer, $extra, $categoryIds, $subcategoryIds, $branchAssignment, $store, $isAdmin) {
            // Keep the existing scheduling, media, ordering and lifecycle handling for both callers.
            $writer = app(AdminController::class);
            $response = $offer ? $writer->updateOffer($request, $offer) : $writer->storeOffer($request);
            $saved = Offer::findOrFail($response->getData(true)['offer']['id']);
            $saved->update(array_merge($branchAssignment, [
                'category_ids' => $categoryIds, 'subcategory_ids' => $subcategoryIds,
                'subcategory_id' => $subcategoryIds[0] ?? null,
            ]));
            $post = StorePost::where('type', 'offer')->where('source_id', $saved->id)->first();
            if ($post || $isAdmin || $request->boolean('create_post')) {
                $media = collect([$saved->thumbnail, $saved->cover])->merge($saved->images ?? [])->filter()->unique()
                    ->map(fn ($url) => ['url' => $url, 'type' => 'image', 'caption' => null])
                    ->merge(collect($saved->videos ?? [])->filter()->unique()->map(fn ($url) => ['url' => $url, 'type' => 'video', 'caption' => null]))->values()->all();
                $pinned = $extra['is_pinned'] ?? $post?->is_pinned ?? false;
                $pinOrder = $pinned ? ($post?->pin_order ?? ((int) StorePost::where('organization_id', $store->id)->max('pin_order') + 1)) : null;
                $post = StorePost::updateOrCreate(['type' => 'offer', 'source_id' => $saved->id], [
                    'organization_id' => $store->id, 'title' => $saved->name,
                    'description' => $saved->details, 'image' => $saved->thumbnail ?: $saved->cover,
                    'media' => $media, 'is_pinned' => $pinned, 'pin_order' => $pinOrder,
                ]);
            }
            return response()->json(['success' => true, 'offer' => $saved->fresh(), 'post' => $post], $offer ? 200 : 201);
        });
    }

    public function deleteOffer(Request $request, Offer $offer)
    {
        if ($offer->organization_id !== $request->user()->id) {
            return response()->json(['error' => 'You are not allowed to manage this offer.'], 403);
        }

        $offer->delete();
        return response()->json(['success' => true]);
    }

    public function branchOfferRequests(Request $request)
    {
        $parent = $request->user();
        if ($parent->parent_org_id !== null) {
            return response()->json(['success' => true, 'offers' => []]);
        }
        $childIds = User::query()->where('parent_org_id', $parent->id)->pluck('id');
        $offers = Offer::query()
            ->whereIn('organization_id', $childIds)
            ->where('branch_assignment_status', 'pending')
            ->with('organization:id,organization_name,parent_org_id')
            ->latest()
            ->get();
        return response()->json(['success' => true, 'offers' => $offers]);
    }

    public function decideBranchOfferRequest(Request $request, Offer $offer, string $decision)
    {
        $parent = $request->user();
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return response()->json(['error' => 'Invalid branch request decision.'], 422);
        }
        $requestingBranch = User::query()->find($offer->organization_id);
        if ($parent->parent_org_id !== null || !$requestingBranch || (int) $requestingBranch->parent_org_id !== (int) $parent->id) {
            return response()->json(['error' => 'You are not allowed to review this branch request.'], 403);
        }
        $offer->update([
            'branch_assignment_status' => $decision === 'approve' ? 'approved' : 'rejected',
            'branch_approved_by' => $parent->id,
            'branch_approved_at' => now(),
        ]);
        return response()->json(['success' => true, 'offer' => $offer->fresh()]);
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
