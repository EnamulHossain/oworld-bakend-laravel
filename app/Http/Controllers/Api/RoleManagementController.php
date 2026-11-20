<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\{Role, Permission};

class RoleManagementController extends Controller
{
    public const BUILTIN = ['user', 'organization', 'admin', 'superAdmin'];
    public const SECTIONS = ['stats', 'organizations', 'offers', 'events', 'coupons', 'highlights', 'categories', 'facilities', 'areas', 'attributes', 'settings', 'content-blocks', 'analytics', 'store-onboardings'];

    public static function permissions(): array
    {
        $names = [];
        foreach (self::SECTIONS as $section) {
            foreach (['view', 'manage'] as $action) $names[] = "staff.$section.$action";
        }
        return $names;
    }

    public function index()
    {
        return response()->json([
            'roles' => Role::where('guard_name', 'sanctum')->with('permissions:id,name')->withCount('users')->orderBy('name')->get(),
            'permissions' => self::permissions(),
            'admins' => User::where('role', 'admin')->with('roles:id,name')->orderBy('username')->get(['id', 'username', 'email']),
            'builtin' => self::BUILTIN,
        ]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new Role(['guard_name' => 'sanctum']));
    }

    public function update(Request $request, Role $role)
    {
        $this->editable($role);
        return $this->save($request, $role);
    }

    private function save(Request $request, Role $role)
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[\pL\pN _-]+$/u', Rule::notIn(self::BUILTIN), Rule::unique('roles')->where('guard_name', 'sanctum')->ignore($role->id)],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'distinct', Rule::in(self::permissions())],
        ]);
        DB::transaction(function () use ($role, $data) {
            $role->name = $data['name'];
            $role->save();
            foreach ($data['permissions'] as $name) Permission::findOrCreate($name, 'sanctum');
            $role->syncPermissions($data['permissions']);
        });
        return response()->json(['success' => true, 'role' => $role->load('permissions')]);
    }

    public function destroy(Role $role)
    {
        $this->editable($role);
        abort_if($role->users()->exists(), 422, 'Reassign the admins using this role before deleting it.');
        $role->delete();
        return response()->json(['success' => true]);
    }

    public function assign(Request $request, User $user)
    {
        abort_unless($user->role === 'admin', 422, 'Staff roles can only be assigned to admins.');
        $data = $request->validate(['role_id' => ['present', 'nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'sanctum')]]);
        $roles = [Role::findOrCreate('admin', 'sanctum')];
        if ($data['role_id']) {
            $role = Role::findOrFail($data['role_id']);
            $this->editable($role);
            $roles[] = $role;
        }
        $user->syncRoles($roles);
        return response()->json(['success' => true]);
    }

    private function editable(Role $role): void
    {
        abort_unless($role->guard_name === 'sanctum' && !in_array($role->name, self::BUILTIN, true), 422, 'Built-in roles are protected.');
    }
}
