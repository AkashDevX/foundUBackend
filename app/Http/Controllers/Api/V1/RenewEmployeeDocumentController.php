<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\DocumentRenewal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RenewEmployeeDocumentController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $request->validate([
            'document_key' => ['required', 'string', 'max:160'],
            'expiry' => ['required', 'string', 'max:32'],
            'file' => ['required', 'file', 'max:15360'],
        ], [
            'document_key.required' => 'Choose which document to renew.',
            'expiry.required' => 'Set the new expiry date.',
            'file.required' => 'Choose the renewed document to upload.',
            'file.max' => 'Documents must be 15 MB or smaller.',
        ]);

        $company = $request->tenantCompany();
        $result = DocumentRenewal::apply(
            $employee,
            (string) $company->slug,
            (string) $request->input('document_key'),
            (string) $request->input('expiry'),
            $request->file('file'),
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $employee->refresh();

        return response()->json([
            'message' => $result['message'],
            'still_due' => $result['still_due'],
            'employee' => $employee->toMobileProfilePayload($company),
        ]);
    }
}
