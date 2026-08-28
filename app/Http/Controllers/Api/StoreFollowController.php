<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreFollowController extends Controller
{
    public function status(Request $request, User $organization)
    {
        $following = DB::table('store_followers')
            ->where('user_id', $request->user()->id)
            ->where('organization_id', $organization->id)
            ->exists();

        return response()->json([
            'following' => $following,
            'follower_count' => (int) $organization->follower_count,
        ]);
    }

    public function toggle(Request $request, User $organization)
    {
        if ($request->user()->is($organization)) {
            return response()->json(['message' => 'You cannot follow your own store.'], 422);
        }

        $following = DB::transaction(function () use ($request, $organization) {
            $query = DB::table('store_followers')
                ->where('user_id', $request->user()->id)
                ->where('organization_id', $organization->id);

            if ($query->exists()) {
                $query->delete();
                $following = false;
            } else {
                DB::table('store_followers')->insert([
                    'user_id' => $request->user()->id,
                    'organization_id' => $organization->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $following = true;
            }

            $count = DB::table('store_followers')->where('organization_id', $organization->id)->count();
            $organization->forceFill(['follower_count' => $count])->save();

            return $following;
        });

        return response()->json([
            'following' => $following,
            'follower_count' => (int) $organization->fresh()->follower_count,
            'message' => $following ? 'You are now following this store.' : 'You unfollowed this store.',
        ]);
    }
}
