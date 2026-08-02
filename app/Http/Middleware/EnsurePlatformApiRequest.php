<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures mobile platform-scoped API calls target the CruLynk platform controller only.
 */
class EnsurePlatformApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = config('platform.identifier_header', 'X-Platform-Slug');
        $expectedSlug = mb_strtolower(trim((string) config('platform.slug', 'crulynk')));
        $providedSlug = mb_strtolower(trim((string) $request->header($headerName, '')));

        if ($providedSlug === '' || $providedSlug !== $expectedSlug) {
            return response()->json([
                'message' => 'Invalid or missing platform scope.',
            ], 403);
        }

        $platformCompany = Company::query()
            ->platformController()
            ->where('slug', $expectedSlug)
            ->where('is_active', true)
            ->first();

        if ($platformCompany === null) {
            return response()->json([
                'message' => 'Platform is not configured.',
            ], 503);
        }

        $request->attributes->set('platformCompany', $platformCompany);

        return $next($request);
    }
}
