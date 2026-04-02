<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCronToken
{
    /**
     * Verify the cron secret token.
     *
     * Accepts token via:
     *   - Query param: ?cron_token=xxx
     *   - Header: X-Cron-Token: xxx
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('nis.cron_token');

        if (empty($expectedToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Cron token not configured. Set CRON_SECRET_TOKEN in .env',
            ], 500);
        }

        $providedToken = $request->get('cron_token')
            ?? $request->header('X-Cron-Token');

        if (!$providedToken || !hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing cron token.',
            ], 401);
        }

        return $next($request);
    }
}
