<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\RoleManagementController;
use Closure;
use Illuminate\Http\Request;

class EnforceStaffPermissions
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') return $next($request);
        $custom = $user->roles->filter(fn ($role) => !in_array($role->name, RoleManagementController::BUILTIN, true));
        if ($custom->isEmpty()) return $next($request);
        $path = $request->path();
        if ($path === 'api/admin/staff-access') return $next($request);
        if (!str_starts_with($path, 'api/admin/')) {
            // Restricted staff cannot use alternate store editing endpoints.
            if (str_starts_with($path, 'api/store-profiles/')) {
                $action = $request->isMethod('GET') ? 'view' : 'manage';
                $names = $custom->flatMap(fn ($role) => $role->permissions->pluck('name'));
                abort_unless($names->contains("staff.organizations.$action") || ($action === 'view' && $names->contains('staff.organizations.manage')), 403, 'Your staff role does not allow this action.');
            }
            abort_if(str_starts_with($path, 'api/branch-access'), 403, 'Use the permitted admin sections to manage stores.');
            return $next($request);
        }
        $section = $request->segment(3);
        // Account/role management is reserved for unrestricted administrators.
        abort_unless(in_array($section, RoleManagementController::SECTIONS, true), 403, 'You do not have access to this admin section.');
        $action = $request->isMethod('GET') || $request->isMethod('HEAD') ? 'view' : 'manage';
        $names = $custom->flatMap(fn ($role) => $role->permissions->pluck('name'));
        abort_unless($names->contains("staff.$section.$action") || ($action === 'view' && $names->contains("staff.$section.manage")), 403, 'Your staff role does not allow this action.');
        return $next($request);
    }
}
