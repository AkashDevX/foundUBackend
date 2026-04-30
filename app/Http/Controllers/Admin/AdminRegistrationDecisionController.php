<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OrganizationPortalUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminRegistrationDecisionController extends Controller
{
    public function accept(Request $request, string $companySlug, string $publicId): RedirectResponse
    {
        $employee = $this->employeeForPortalSession($request, $companySlug, $publicId);

        if ($employee->employment_status !== 'pending') {
            return back()->with('error', 'This application is not awaiting approval.');
        }

        // No API access until they call POST /login with the same email + password (Sanctum token is only created there).
        $this->revokeEmployeeTokens($employee);

        $employee->forceFill([
            'employment_status' => 'active',
            'hired_at' => now()->toDateString(),
        ])->save();

        return back()->with('success', 'Registration approved. The employee must open the mobile app and sign in with their registration email and password — no automatic access.');
    }

    public function decline(Request $request, string $companySlug, string $publicId): RedirectResponse
    {
        $employee = $this->employeeForPortalSession($request, $companySlug, $publicId);

        if ($employee->employment_status !== 'pending') {
            return back()->with('error', 'This application is not awaiting approval.');
        }

        $employee->forceFill([
            'employment_status' => 'declined',
        ])->save();

        $this->revokeEmployeeTokens($employee);

        return back()->with('success', 'Registration declined. This person will not be able to sign in to the mobile app.');
    }

    private function employeeForPortalSession(Request $request, string $companySlug, string $publicId): Employee
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $sessionCompany = $portalUser->company()->firstOrFail();

        abort_unless($sessionCompany->slug === $companySlug, 403);

        /** @var Employee $employee */
        $employee = Employee::on($sessionCompany->tenant_connection)
            ->where('public_id', $publicId)
            ->firstOrFail();

        return $employee;
    }

    private function revokeEmployeeTokens(Employee $employee): void
    {
        $connection = $employee->getConnectionName();

        if (! is_string($connection) || $connection === '') {
            return;
        }

        if (! Schema::connection($connection)->hasTable('personal_access_tokens')) {
            return;
        }

        DB::connection($connection)
            ->table('personal_access_tokens')
            ->where('tokenable_type', Employee::class)
            ->where('tokenable_id', $employee->getKey())
            ->delete();
    }
}
