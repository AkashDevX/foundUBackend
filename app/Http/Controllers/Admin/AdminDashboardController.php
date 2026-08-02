<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OrganizationPortalUser;
use App\Support\AdminDashboardNotifications;
use App\Support\AdminEmployeeProfileView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Organization dashboard — landing page after portal login.
     */
    public function index(Request $request): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();

        $tenantError = null;
        $statsTotal = 0;
        $statsPending = 0;
        $statsActive = 0;
        $statsDeclined = 0;
        $notifications = ['sections' => [], 'alert_count' => 0];

        try {
            $conn = $company->tenant_connection;
            $statsTotal = Employee::on($conn)->count();
            $statsPending = Employee::on($conn)->where('employment_status', 'pending')->count();
            $statsActive = Employee::on($conn)->where('employment_status', 'active')->count();
            $statsDeclined = Employee::on($conn)->whereIn('employment_status', ['declined', 'rejected'])->count();
            $notifications = AdminDashboardNotifications::collect($company);
        } catch (\Throwable $e) {
            $tenantError = $e->getMessage();
        }

        return view('admin.dashboard', [
            'currentCompany' => $company,
            'tenantError' => $tenantError,
            'statsTotal' => $statsTotal,
            'statsPending' => $statsPending,
            'statsActive' => $statsActive,
            'statsDeclined' => $statsDeclined,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Lists registration submissions for the signed-in organization only (tenant DB).
     */
    public function registrations(Request $request): View
    {
        $statusFilter = $request->query('status'); // pending | active | null (all)

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();

        $rows = [];
        $tenantError = null;
        $statsTotal = 0;
        $statsPending = 0;
        $statsActive = 0;
        $statsDeclined = 0;

        try {
            $conn = $company->tenant_connection;
            $statsTotal = Employee::on($conn)->count();
            $statsPending = Employee::on($conn)->where('employment_status', 'pending')->count();
            $statsActive = Employee::on($conn)->where('employment_status', 'active')->count();
            $statsDeclined = Employee::on($conn)->whereIn('employment_status', ['declined', 'rejected'])->count();

            $query = Employee::on($conn)->orderByDesc('created_at');

            if ($statusFilter !== null && $statusFilter !== '') {
                $query->where('employment_status', $statusFilter);
            }

            foreach ($query->get() as $employee) {
                $rows[] = ['employee' => $employee];
            }
        } catch (\Throwable $e) {
            $tenantError = $e->getMessage();
        }

        return view('admin.registrations', [
            'currentCompany' => $company,
            'rows' => $rows,
            'tenantError' => $tenantError,
            'statusFilter' => $statusFilter,
            'statsTotal' => $statsTotal,
            'statsPending' => $statsPending,
            'statsActive' => $statsActive,
            'statsDeclined' => $statsDeclined,
        ]);
    }

    /**
     * Full submitted profile for one employee (same organization as portal session only).
     */
    public function show(Request $request, string $companySlug, string $publicId): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $sessionCompany = $portalUser->company()->firstOrFail();

        abort_unless($sessionCompany->slug === $companySlug, 403);

        /** @var Employee $employee */
        $employee = Employee::on($sessionCompany->tenant_connection)
            ->where('public_id', $publicId)
            ->firstOrFail();

        return view('admin.registration-show', AdminEmployeeProfileView::viewData($request, $sessionCompany, $employee));
    }

    /**
     * Header search — find applicants/employees by name, email, ID, or code.
     */
    public function searchApplicants(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $needle = '%'.addcslashes(mb_strtolower($query), '%_\\').'%';

        $employees = Employee::on($conn)
            ->where(function ($builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(COALESCE(full_legal_name, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(public_id) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(employee_code, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$needle]);
            })
            ->orderBy('full_legal_name')
            ->orderBy('email')
            ->limit(12)
            ->get(['public_id', 'full_legal_name', 'email', 'employment_status', 'employee_code']);

        $results = $employees->map(function (Employee $employee) use ($company): array {
            $name = trim((string) ($employee->full_legal_name ?: $employee->email ?: ''));
            $status = trim((string) ($employee->employment_status ?: ''));
            $statusSuffix = $status !== '' && $status !== 'active' ? ' ('.$status.')' : '';

            return [
                'public_id' => $employee->public_id,
                'label' => $name.$statusSuffix,
                'name' => $name,
                'email' => $employee->email,
                'employee_code' => $employee->employee_code,
                'status' => $status,
                'url' => route('admin.registrations.show', [
                    'companySlug' => $company->slug,
                    'publicId' => $employee->public_id,
                ]),
            ];
        })->values();

        return response()->json(['results' => $results]);
    }
}
