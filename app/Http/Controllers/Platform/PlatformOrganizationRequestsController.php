<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\OrganizationRequest;
use App\Support\DisplayTimezone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * CruLynk platform portal — new organisation access requests (master DB only).
 */
class PlatformOrganizationRequestsController extends Controller
{
    public function index(Request $request): View
    {
        $requests = OrganizationRequest::query()
            ->with('platformCompany')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $pendingCount = OrganizationRequest::query()->pending()->count();

        return view('platform.organization-requests.index', [
            'requests' => $requests,
            'pendingCount' => $pendingCount,
            'displayNow' => DisplayTimezone::now(),
        ]);
    }

    public function show(Request $request, OrganizationRequest $organizationRequest): View
    {
        $organizationRequest->load('platformCompany');

        return view('platform.organization-requests.show', [
            'organizationRequest' => $organizationRequest,
            'displayNow' => DisplayTimezone::now(),
        ]);
    }
}
