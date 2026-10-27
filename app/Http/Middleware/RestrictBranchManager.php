<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class RestrictBranchManager
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->bearerToken()) return $next($request);
        $user = Auth::guard('sanctum')->user();
        $token = $user?->currentAccessToken();
        $ability = collect($token?->abilities ?? [])->first(fn ($value) => str_starts_with($value, 'branch-manager:'));
        if (!$ability) return $next($request);
        $assignment = DB::table('branch_managers')->where('id', substr($ability, 15))->where('branch_id', $user->id)->whereNotNull('accepted_at')->first();
        abort_unless($assignment && \App\Models\User::whereKey($assignment->manager_id)->where('status', 'active')->exists() && (int) $assignment->parent_id === (int) $user->parent_org_id && $user->status === 'active', 403, 'Branch access has been revoked.');
        $path = $request->path();
        $allowed = str_starts_with($path, 'api/public/')
            || $path === 'api/auth/me'
            || preg_match('#^api/organization/(posts|offers|events)(/|$)#', $path)
            || ($request->isMethod('GET') && in_array($path, ['api/organization/profile', 'api/organization/categories', 'api/organization/attributes', 'api/organization/stats']))
            || ($path === 'api/organization/profile' && in_array($request->method(), ['GET', 'PUT']))
            || ($path === 'api/organization/profile/upload-image' && $request->isMethod('POST'))
            || ($path === 'api/store-profiles/'.$user->id && in_array($request->method(), ['GET', 'PUT']));
        // Managers cannot approve shared assignments or access parent settings.
        if (str_contains($path, 'branch-request')) $allowed = false;
        abort_unless($allowed, 403, 'This action requires the profile owner.');
        $request->attributes->set('branch_manager', true);
        return $next($request);
    }
}
