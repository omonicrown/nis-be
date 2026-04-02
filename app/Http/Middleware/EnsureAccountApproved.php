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

        if ($user->status === UserStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending approval. Please wait for an admin to verify your account.',
            ], 403);
        }

        if ($user->status === UserStatus::SUSPENDED) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact the admin.',
            ], 403);
        }

        if ($user->status === UserStatus::REJECTED) {
            return response()->json([
                'success' => false,
                'message' => 'Your registration was not approved. Please contact the admin for details.',
            ], 403);
        }

        if ($user->status !== UserStatus::ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active.',
            ], 403);
        }

        return $next($request);
    }
}
