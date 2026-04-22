<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Looks up the active company in the master DB, then switches the default
 * database connection so models and DB:: facade use that company's schema
 * for the rest of the request. Models that must stay on master (e.g.
 * Company) declare $connection = 'master' explicitly.
 */
class ResolveTenantFromMaster
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = config('tenants.identifier_header', 'X-Company-Slug');
        $slug = $request->header($headerName);

        if ($slug === null || $slug === '') {
            return response()->json([
                'message' => 'Tenant not specified.',
                'hint' => sprintf('Send header %s with the company slug from the master registry.', $headerName),
            ], 422);
        }

        /** @var Company|null $company */
        $company = Company::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($company === null) {
            return response()->json([
                'message' => 'Unknown or inactive tenant.',
            ], 404);
        }

        $request->attributes->set('tenant_company', $company);

        DB::setDefaultConnection($company->tenant_connection);

        return $next($request);
    }
}
