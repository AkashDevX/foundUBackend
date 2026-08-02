<?php

namespace App\Http\Middleware;

use App\Models\OrganizationPortalUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts routes to CruLynk platform controller portal users only.
 */
class EnsurePlatformPortalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var OrganizationPortalUser|null $user */
        $user = $request->user('portal');

        if ($user === null) {
            return redirect()->route('portal.login');
        }

        $company = $user->company;

        if ($company === null || ! $company->isPlatformController()) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
