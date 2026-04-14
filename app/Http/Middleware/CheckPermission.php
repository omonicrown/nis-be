<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // Super admin bypasses all permission checks
        if ($user->role?->slug === 'super_admin') {
            return $next($request);
        }

        // Check if user's role has any of the required permissions
        $userPermissions = $user->role?->permissions->pluck('slug')->toArray() ?? [];

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to access this resource.',
            'required_permissions' => $permissions,
        ], 403);
    }
}
