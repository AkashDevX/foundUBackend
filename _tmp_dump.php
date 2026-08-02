<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLeaveEntitlement;
use App\Models\LeaveType;
use App\Models\OrganizationPortalUser;
use App\Support\AdminEmployeeProfileView;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

$company = Company::first();
$conn = $company->tenant_connection;
$portalUser = OrganizationPortalUser::where('company_id', $company->id)->first();
auth('portal')->setUser($portalUser);

$emp = Employee::on($conn)->where('employment_status', 'active')->first();

// ensure at least one entitlement + one available
$type = LeaveType::on($conn)->where('is_active', true)->first();
EmployeeLeaveEntitlement::on($conn)->firstOrCreate(
    ['employee_id' => $emp->id, 'leave_type_id' => $type->id],
    ['entitlement_hours' => 100, 'created_by' => 'dump']
);

$req = Request::create('/admin/employees/profiles?employee='.$emp->public_id, 'GET');
$session = app('session')->driver();
$session->start();
$req->setLaravelSession($session);
$req->setUserResolver(fn () => $portalUser);
app()->instance('request', $req);
view()->share('errors', new ViewErrorBag);

$data = AdminEmployeeProfileView::viewData($req, $company, $emp);
$html = view('admin.partials.employee-profile-detail', array_merge($data, ['showApprovalActions' => false]))->render();

file_put_contents(base_path('_tmp_modal.html'), $html);
echo "wrote _tmp_modal.html bytes=".strlen($html)."\n";

// find the Leaves section boundaries
$pos = strpos($html, 'Leave entitlements');
echo "Leave entitlements at offset: ".var_export($pos, true)."\n";
