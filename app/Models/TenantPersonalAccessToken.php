<?php

namespace App\Models;

use Illuminate\Support\Facades\App;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum stores tokens in the tenant DB (via {@see Employee::tokens()}), but the stock model
 * resolves queries on the app default connection (e.g. foundu_masterdb), where there is no
 * `personal_access_tokens` table.
 *
 * This model always uses the tenant connection for `personal_access_tokens` rows. It resolves
 * the tenant from {@see Request::tenantCompany()} when set, otherwise from the same
 * `X-Company-Slug` header as {@see \App\Http\Middleware\ResolveTenantFromMaster} so lookups work
 * even if attribute wiring differs by middleware order.
 *
 * Note: Bearer auth still reads the token row in `personal_access_tokens`; the **profile** body
 * comes from the {@see Employee} model in {@see CurrentEmployeeController}.
 */
class TenantPersonalAccessToken extends SanctumPersonalAccessToken
{
    /** Must match Sanctum migration — Laravel would otherwise use `tenant_personal_access_tokens` from this class name. */
    protected $table = 'personal_access_tokens';

    public function getConnectionName(): ?string
    {
        if (App::runningInConsole()) {
            return $this->connection ?? config('database.default');
        }

        if (! app()->bound('request')) {
            return $this->connection ?? config('database.default');
        }

        $request = request();
        $company = $request->attributes->get('tenant_company');

        if (! $company instanceof Company) {
            $headerName = config('tenants.identifier_header', 'X-Company-Slug');
            $slug = $request->header($headerName);
            if (is_string($slug) && $slug !== '') {
                $company = Company::query()
                    ->where('slug', $slug)
                    ->where('is_active', true)
                    ->first();
            }
        }

        if ($company instanceof Company && is_string($company->tenant_connection) && $company->tenant_connection !== '') {
            return $company->tenant_connection;
        }

        return $this->connection ?? config('database.default');
    }
}
