<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\OrganizationRequest;
use App\Support\DisplayTimezone;
use App\Support\PlatformOrganizationSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PlatformDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $organizations = PlatformOrganizationSummary::forAllTenants();
        $activeCount = $organizations->filter(fn (array $row): bool => $row['company']->is_active)->count();
        $pendingOrgRequests = OrganizationRequest::query()->pending()->count();

        return view('platform.dashboard', [
            'organizations' => $organizations,
            'stats' => [
                'total' => $organizations->count(),
                'active' => $activeCount,
                'pending_org_requests' => $pendingOrgRequests,
            ],
            'displayNow' => DisplayTimezone::now(),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $company = Company::query()
            ->tenantOrganizations()
            ->where('slug', $slug)
            ->firstOrFail();

        $summary = PlatformOrganizationSummary::forCompany($company);

        return view('platform.organization-show', [
            'summary' => $summary,
            'displayNow' => DisplayTimezone::now(),
        ]);
    }
}
