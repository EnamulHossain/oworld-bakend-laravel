<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{User, StoreLocation};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BranchLocationController extends Controller
{
    public function update(Request $request, User $user)
    {
        abort_unless($user->role === 'organization', 404, 'Store profile not found.');
        abort_unless((int) $request->user()->id === (int) $user->id || in_array(strtolower((string) $request->user()->role), ['admin', 'superadmin'], true), 403, 'You do not have permission to manage locations for this store.');
        abort_if($user->parent_org_id, 403, 'Only the mother profile manages locations.');
        $data = $request->validate([
            'branch_mode' => ['required', Rule::in(['unified', 'independent'])],
            'locations' => ['present', 'array', 'max:100'],
            'locations.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('store_locations', 'id')->where('organization_id', $user->id)],
            'locations.*.name' => ['required', 'string', 'max:255'],
            'locations.*.address' => ['nullable', 'string', 'max:255'],
            'locations.*.area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'locations.*.phone' => ['nullable', 'string', 'max:40'],
            'locations.*.whatsapp' => ['nullable', 'string', 'max:40'],
            'locations.*.google_map_url' => ['nullable', 'url:http,https', 'max:500'],
            'locations.*.opening_hours' => ['nullable', 'string', 'max:255'],
            'locations.*.status' => ['required', Rule::in(['open', 'temporarily_closed', 'relocating', 'coming_soon'])],
        ]);
        abort_if($data['branch_mode'] === 'unified' && $user->childOrganizations()->exists(), 422, 'Unlink existing branch profiles before switching to unified locations.');
        abort_if($data['branch_mode'] === 'independent' && count($data['locations']), 422, 'Remove unified locations before switching to independent profiles.');
        DB::transaction(function () use ($user, $data) {
            $user->update(['branch_mode' => $data['branch_mode']]);
            $ids = [];
            foreach ($data['locations'] as $row) {
                $location = StoreLocation::updateOrCreate(['id' => $row['id'] ?? null, 'organization_id' => $user->id], $row);
                $ids[] = $location->id;
            }
            $removed = $user->locations()->whereNotIn('id', $ids)->pluck('id')->all();
            // Never silently widen a selected-location offer after deleting a location.
            foreach ($user->organizationOffers()->whereNotNull('location_ids')->get() as $offer) {
                if (array_intersect($removed, $offer->location_ids ?? [])) {
                    abort(422, 'Update offers using a location before removing it.');
                }
            }
            $user->locations()->whereNotIn('id', $ids)->delete();
        });
        return response()->json(['success' => true, 'locations' => $user->locations()->get()]);
    }
}
