<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchManagerController extends Controller
{
    public function createBranch(Request $request)
    {
        $parent = $request->user();
        abort_unless($parent->role === 'organization' && !$parent->parent_org_id && $parent->branch_mode === 'independent', 403);
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash', 'max:100', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);
        $branch = DB::transaction(function () use ($parent, $data) {
            $branch = User::create(array_merge($data, [
                'parent_org_id' => $parent->id, 'branch_mode' => 'independent', 'role' => 'organization',
                'status' => 'active', 'categories' => $parent->categories, 'subcategory_ids' => $parent->subcategory_ids,
            ]));
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'organization', 'guard_name' => 'sanctum']);
            $branch->syncRoles(['organization']);
            return $branch;
        });
        return response()->json(['branch' => $branch], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $assignments = DB::table('branch_managers')->where(fn ($q) => $q->where('parent_id', $user->id)->orWhere('manager_id', $user->id))
            ->get()->filter(fn ($row) => User::where('id', $row->branch_id)->where('parent_org_id', $row->parent_id)->exists())
            ->map(function ($row) use ($user) {
                $row->branch_name = User::find($row->branch_id)?->organization_name;
                $row->manager_email = User::find($row->manager_id)?->email;
                $row->is_owner = (int) $row->parent_id === (int) $user->id;
                return $row;
            })->values();
        return response()->json(['assignments' => $assignments, 'can_create_branches' => $user->role === 'organization' && !$user->parent_org_id && $user->branch_mode === 'independent', 'branches' => $user->childOrganizations()->get(['id', 'organization_name'])]);
    }

    public function invite(Request $request)
    {
        $parent = $request->user();
        abort_unless($parent->role === 'organization' && !$parent->parent_org_id && $parent->branch_mode === 'independent', 403);
        $data = $request->validate(['branch_id' => ['required', 'integer'], 'email' => ['required', 'email']]);
        $branch = $parent->childOrganizations()->findOrFail($data['branch_id']);
        $manager = User::where('email', $data['email'])->first();
        abort_unless($manager && $manager->id !== $parent->id && $manager->id !== $branch->id, 422, 'Choose another registered O’World account.');
        DB::table('branch_managers')->updateOrInsert(['branch_id' => $branch->id, 'manager_id' => $manager->id], [
            'parent_id' => $parent->id, 'accepted_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['success' => true]);
    }

    public function accept(Request $request, int $assignment)
    {
        $row = DB::table('branch_managers')->where('id', $assignment)->where('manager_id', $request->user()->id)->first();
        abort_unless($row && User::where('id', $row->branch_id)->where('parent_org_id', $row->parent_id)->exists(), 404);
        DB::table('branch_managers')->where('id', $row->id)->update(['accepted_at' => now(), 'updated_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function revoke(Request $request, int $assignment)
    {
        $row = DB::table('branch_managers')->where('id', $assignment)->where(fn ($q) => $q->where('parent_id', $request->user()->id)->orWhere('manager_id', $request->user()->id))->first();
        abort_unless($row, 404);
        DB::table('branch_managers')->where('id', $row->id)->delete();
        return response()->json(['success' => true]);
    }

    public function session(Request $request, int $assignment)
    {
        $row = DB::table('branch_managers')->where('id', $assignment)->where('manager_id', $request->user()->id)->whereNotNull('accepted_at')->first();
        abort_unless($row, 403);
        $branch = User::where('parent_org_id', $row->parent_id)->where('status', 'active')->findOrFail($row->branch_id);
        $token = $branch->createToken('branch-manager', ['branch-manager:'.$row->id], now()->addHours(8));
        return response()->json(['user' => array_merge($branch->toArray(), ['branch_manager' => true]), 'token' => $token->plainTextToken]);
    }
}
