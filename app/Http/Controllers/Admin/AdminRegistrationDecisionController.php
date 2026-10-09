<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OrganizationPortalUser;
use App\Services\EmployeeApplicationMailer;
use App\Support\DisplayTimezone;
use App\Support\InductionEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminRegistrationDecisionController extends Controller
{
    public function __construct(
        private readonly EmployeeApplicationMailer $applicationMailer,
    ) {}

    public function accept(Request $request, string $companySlug, string $publicId): RedirectResponse
    {
        ['employee' => $employee, 'company' => $company] = $this->portalContext($request, $companySlug, $publicId);

        if ($employee->employment_status !== 'pending') {
            return back()->with('error', 'This application is not awaiting approval.');
        }

        // No API access until they call POST /login with the same email + password (Sanctum token is only created there).
        $this->revokeEmployeeTokens($employee);

        $employee->forceFill([
            'employment_status' => 'active',
            'hired_at' => DisplayTimezone::now()->toDateString(),
        ])->save();

        $this->applicationMailer->sendApproved($employee, $company);

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $message = InductionEligibility::requireForNewHire($employee, $portalUser->name ?: $portalUser->email);

        return back()->with('success', $message);
    }

    public function decline(Request $request, string $companySlug, string $publicId): RedirectResponse
    {
        ['employee' => $employee, 'company' => $company] = $this->portalContext($request, $companySlug, $publicId);

        if ($employee->employment_status !== 'pending') {
            return back()->with('error', 'This application is not awaiting approval.');
        }

        $employee->forceFill([
            'employment_status' => 'declined',
        ])->save();

        $this->revokeEmployeeTokens($employee);

        $this->applicationMailer->sendDeclined($employee, $company);

        return back()->with('success', 'Registration declined. This person will not be able to sign in to the mobile app.');
    }

    public function markInactive(Request $request, string $companySlug, string $publicId): RedirectResponse
    {
        $employee = $this->employeeForPortalSession($request, $companySlug, $publicId);

        if ($employee->employment_status !== 'active') {
            return back()->with('error', 'Only active employees can be marked inactive.');
        }

        $employee->forceFill([
            'employment_status' => 'inactive',
        ])->save();

        $this->revokeEmployeeTokens($employee);

        return back()->with('success', 'Employee marked inactive. They can no longer sign in, and will be hidden from payroll, schedules, and assignments.');
    }

    public function reactivate(Request $request, string $companySlug, string $publicId): RedirectResponse
    {
        $employee = $this->employeeForPortalSession($request, $companySlug, $publicId);

        if ($employee->employment_status !== 'inactive') {
            return back()->with('error', 'Only inactive employees can be reactivated.');
        }

        $this->revokeEmployeeTokens($employee);

        $employee->forceFill([
            'employment_status' => 'active',
        ])->save();

        return back()->with('success', 'Employee reactivated. They can sign in to the mobile app again and will appear in payroll, schedules, and assignments.');
    }

    public function overrideInduction(Request $request, string $companySlug, string $publicId): RedirectResponse
    {
        $employee = $this->employeeForPortalSession($request, $companySlug, $publicId);
        $data = $request->validate([
            'override_reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        InductionEligibility::grantOverride(
            $employee,
            $portalUser->name ?: $portalUser->email ?: 'Administrator',
            trim($data['override_reason']),
        );

        return back()->with('success', 'Induction override saved. This employee can now be assigned shifts and clock in.');
    }

    public function clearInductionOverride(Request $request, string $companySlug, string $publicId): RedirectResponse
    {
        $employee = $this->employeeForPortalSession($request, $companySlug, $publicId);
        InductionEligibility::clearOverride($employee);

        return back()->with('success', 'Induction override removed.');
    }

    private function employeeForPortalSession(Request $request, string $companySlug, string $publicId): Employee
    {
        return $this->portalContext($request, $companySlug, $publicId)['employee'];
    }

    /**
     * @return array{employee: Employee, company: Company}
     */
    private function portalContext(Request $request, string $companySlug, string $publicId): array
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $sessionCompany = $portalUser->company()->firstOrFail();

        abort_unless($sessionCompany->slug === $companySlug, 403);

        /** @var Employee $employee */
        $employee = Employee::on($sessionCompany->tenant_connection)
            ->where('public_id', $publicId)
            ->firstOrFail();

        return [
            'employee' => $employee,
            'company' => $sessionCompany,
        ];
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
