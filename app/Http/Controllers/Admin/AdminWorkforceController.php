<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\OrganizationPortalUser;
use App\Models\Shift;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminWorkforceController extends Controller
{
    public function index(Request $request): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $departments = Department::on($conn)->orderBy('name')->get();
        $locations = WorkLocation::on($conn)->orderBy('name')->get();
        $shifts = Shift::on($conn)->orderBy('name')->get();

        return view('admin.workforce', [
            'company' => $company,
            'departments' => $departments,
            'workLocations' => $locations,
            'shifts' => $shifts,
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        Department::on($conn)->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('admin.workforce')->with('status', 'Department created.');
    }

    public function storeWorkLocation(Request $request): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        WorkLocation::on($conn)->create([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('admin.workforce')->with('status', 'Work location created.');
    }

    public function storeShift(Request $request): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'breaks_summary' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        Shift::on($conn)->create([
            'name' => $data['name'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'breaks_summary' => $data['breaks_summary'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('admin.workforce')->with('status', 'Shift created.');
    }
}
