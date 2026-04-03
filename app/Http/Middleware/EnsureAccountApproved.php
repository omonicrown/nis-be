<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Handle both enum and raw string
        $status = $user->status instanceof UserStatus
            ? $user->status->value
            : $user->status;

        if ($status === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending approval.',
            ], 403);
        }

        if ($status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact the admin.',
            ], 403);
        }

        if ($status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Your registration was not approved.',
            ], 403);
        }

        if ($status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active.',
            ], 403);
        }

        return $next($request);
    }
}
