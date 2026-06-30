<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequestRequest;
use App\Models\Company;
use App\Models\OrganizationRequest;
use Illuminate\Http\JsonResponse;

/**
 * Public mobile endpoint: new organisation access request (master DB, CruLynk platform only).
 */
class RequestOrganizationController extends Controller
{
    public function __invoke(StoreOrganizationRequestRequest $request): JsonResponse
    {
        /** @var Company $platformCompany */
        $platformCompany = $request->attributes->get('platformCompany');

        $validated = $request->validated();

        OrganizationRequest::query()->create([
            'platform_company_id' => $platformCompany->id,
            'company_name' => $validated['company_name'],
            'industry' => $validated['industry'],
            'industry_other' => $validated['industry'] === 'Other' ? ($validated['industry_other'] ?? null) : null,
            'employee_band' => $validated['employee_band'],
            'employee_band_other' => $validated['employee_band'] === 'Other'
                ? ($validated['employee_band_other'] ?? null)
                : null,
            'postcode' => $validated['postcode'],
            'contact_full_name' => $validated['contact_full_name'],
            'contact_email' => $validated['contact_email'],
            'contact_telephone' => $validated['contact_telephone'],
            'status' => OrganizationRequest::STATUS_PENDING,
            'source' => 'mobile_app',
        ]);

        return response()->json([
            'message' => 'Your organisation request has been received.',
        ], 201);
    }
}
