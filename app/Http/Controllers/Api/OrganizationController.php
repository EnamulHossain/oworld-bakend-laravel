<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Offer;
use App\Models\Attribute;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class OrganizationController extends Controller
{
    private const MEDIA_UPLOAD_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov', 'm4v', 'ogg', 'avi', 'mkv'];
    private const MEDIA_UPLOAD_MAX_BYTES = 20 * 1024 * 1024;

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
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'profile_banner' => ['nullable', 'string', 'max:500'],
            'opening_hours' => ['nullable', 'string', 'max:120'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
        ]);

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
            'phone' => $user->phone,
            'address' => $user->address,
            'about' => $user->about,
            'avatar' => $user->avatar,
            'profile_banner' => $user->profile_banner,
            'opening_hours' => $user->opening_hours,
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

    public function categories()
    {
        $categories = Category::query()
            ->where('status', 'active')
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);

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
            'starting_date' => ['required', 'date'],
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
        ]);

        $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order'] ?? []);
        if (!is_array($gallerySortOrder)) {
            $gallerySortOrder = [];
        }

        $data = $this->normalizeDateAndTimeFields($data, 'starting_date');

        $event = Event::create([
            ...$data,
            'banner' => $this->toArrayField($data['banner'] ?? []),
            'gallery_sort_order' => $gallerySortOrder,
            'attributes' => $this->normalizeAttributes($data['attributes'] ?? []),
            'created_by' => $request->user()->id,
            'organization_id' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'event' => $event], 201);
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
            'event_id' => ['nullable', 'exists:events,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive', 'expired'])],
        ]);

        $gallerySortOrder = $this->normalizeJsonField($data['gallery_sort_order'] ?? []);
        if (!is_array($gallerySortOrder)) {
            $gallerySortOrder = [];
        }

        $offer = Offer::create([
            ...$data,
            'images' => $this->toArrayField($data['images'] ?? []),
            'gallery_sort_order' => $gallerySortOrder,
            'videos' => $this->toArrayField($data['videos'] ?? []),
            'attributes' => $this->normalizeAttributes($data['attributes'] ?? []),
            'created_by' => $request->user()->id,
            'organization_id' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'offer' => $offer], 201);
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
