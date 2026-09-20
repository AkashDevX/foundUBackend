<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OrganizationPortalUser;
use App\Models\TimeClockEntry;
use App\Models\WorkLocation;
use App\Services\LocationTrackingService;
use App\Services\TimeClockService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminLocationTrackingController extends Controller
{
    /** Distinct trail/pin colours for multi-employee site maps. */
    private const EMPLOYEE_PALETTE = [
        '#0f766e',
        '#2563eb',
        '#c2410c',
        '#7c3aed',
        '#be123c',
        '#0891b2',
        '#ca8a04',
        '#15803d',
    ];

    public function index(Request $request, LocationTrackingService $tracking, TimeClockService $timeClock): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;
        DB::setDefaultConnection($conn);

        $employees = Employee::on($conn)
            ->where('employment_status', 'active')
            ->with(['workLocation', 'assignedShift', 'assignmentShifts.shiftTemplate'])
            ->orderBy('full_legal_name')
            ->orderBy('email')
            ->get();

        $livePositions = $tracking->livePositionsForEmployees($employees);
        $listUrl = route('admin.employees.location-tracking');

        $selectedEmployeePublicId = is_string($request->query('employee'))
            ? trim($request->query('employee'))
            : '';
        $selectedClockInEntryId = (int) $request->query('clock_in_entry_id', 0);
        $selectedWorkLocationId = (int) $request->query('work_location_id', 0);

        if ($selectedWorkLocationId > 0 && $selectedEmployeePublicId === '') {
            return $this->siteView(
                $company,
                $conn,
                $livePositions,
                $selectedWorkLocationId,
                $tracking,
                $listUrl,
            );
        }

        if ($selectedEmployeePublicId === '') {
            $siteCards = $this->siteCardsFromPositions($livePositions);

            return view('admin.employees-location-tracking', [
                'company' => $company,
                'mode' => 'list',
                'clockedInRows' => $livePositions,
                'siteCards' => $siteCards,
                'selected' => null,
                'site' => null,
                'siteEmployees' => [],
                'trail' => [],
                'idleAlerts' => [],
                'movementStats' => null,
                'listUrl' => $listUrl,
            ]);
        }

        $selectedEmployee = $employees->firstWhere('public_id', $selectedEmployeePublicId);
        if (! $selectedEmployee instanceof Employee) {
            return redirect()->route('admin.employees.location-tracking');
        }

        $selectedRow = collect($livePositions)
            ->firstWhere('employee_public_id', $selectedEmployeePublicId);

        $session = $timeClock->openSessionFor($selectedEmployee);
        $clockInEntry = $session['clock_in'] ?? null;

        if ($selectedClockInEntryId > 0 && (! $clockInEntry instanceof TimeClockEntry || (int) $clockInEntry->id !== $selectedClockInEntryId)) {
            if ($clockInEntry === null) {
                $clockInEntry = TimeClockEntry::on($conn)
                    ->where('employee_id', $selectedEmployee->id)
                    ->where('event_type', TimeClockEntry::EVENT_CLOCK_IN)
                    ->whereKey($selectedClockInEntryId)
                    ->first();
            }
        }

        if ($selectedRow === null && $clockInEntry instanceof TimeClockEntry) {
            $clockInEntry->loadMissing(['workLocation', 'shift']);
            $scheduleLocation = $clockInEntry->workLocation;
            $selectedRow = [
                'employee_public_id' => $selectedEmployee->public_id,
                'employee_name' => $selectedEmployee->full_legal_name ?: ($selectedEmployee->email ?? 'Employee'),
                'clock_in_entry_id' => $clockInEntry->id,
                'clocked_in_at' => $clockInEntry->clocked_at?->toIso8601String(),
                'status' => 'ended',
                'status_label' => 'Shift ended',
                'work_location_id' => $scheduleLocation?->id ?? $clockInEntry->work_location_id,
                'work_location_name' => $scheduleLocation?->name ?? $selectedEmployee->workLocation?->name,
                'work_location_address' => $scheduleLocation?->address ?? $selectedEmployee->workLocation?->address,
                'shift_label' => null,
                'shift_name' => $clockInEntry->shift?->name,
                'shift_start' => null,
                'shift_end' => null,
                'is_on_break' => false,
                'has_idle_alert' => false,
                'latitude' => null,
                'longitude' => null,
                'recorded_at' => null,
                'site_latitude' => $clockInEntry->expected_latitude !== null ? (float) $clockInEntry->expected_latitude : null,
                'site_longitude' => $clockInEntry->expected_longitude !== null ? (float) $clockInEntry->expected_longitude : null,
                'allowed_radius_meters' => $clockInEntry->allowed_radius_meters !== null
                    ? (int) $clockInEntry->allowed_radius_meters
                    : $timeClock->geofenceRadiusMeters(),
                'within_geofence' => null,
                'distance_from_site_meters' => null,
            ];
        }

        if ($selectedRow === null) {
            return redirect()->route('admin.employees.location-tracking');
        }

        $clockInId = (int) ($selectedRow['clock_in_entry_id'] ?? 0);
        $trail = $clockInId > 0
            ? $tracking->trailForSession((int) $selectedEmployee->id, $clockInId)
            : [];
        $idleAlerts = $clockInId > 0
            ? $tracking->idleAlertsForSession((int) $selectedEmployee->id, $clockInId)
            : [];
        $movementStats = $tracking->movementStatsForTrail($trail, $idleAlerts);

        return view('admin.employees-location-tracking', [
            'company' => $company,
            'mode' => 'detail',
            'clockedInRows' => $livePositions,
            'siteCards' => [],
            'selected' => $selectedRow,
            'site' => null,
            'siteEmployees' => [],
            'trail' => $trail,
            'idleAlerts' => $idleAlerts,
            'movementStats' => $movementStats,
            'livePollUrl' => route('admin.employees.location-tracking.live', [
                'employee' => $selectedEmployee->public_id,
            ]),
            'listUrl' => $listUrl,
            'trailUrl' => route('admin.employees.location-tracking.trail'),
        ]);
    }

    public function live(Request $request, LocationTrackingService $tracking): JsonResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;
        DB::setDefaultConnection($conn);

        $employees = Employee::on($conn)
            ->where('employment_status', 'active')
            ->with(['workLocation', 'assignedShift', 'assignmentShifts.shiftTemplate'])
            ->orderBy('full_legal_name')
            ->get();

        $positions = $tracking->livePositionsForEmployees($employees);

        $filterPublicId = is_string($request->query('employee'))
            ? trim($request->query('employee'))
            : '';
        if ($filterPublicId !== '') {
            $positions = array_values(array_filter(
                $positions,
                static fn (array $row): bool => ($row['employee_public_id'] ?? '') === $filterPublicId,
            ));
        }

        $filterLocationId = (int) $request->query('work_location_id', 0);
        if ($filterLocationId > 0) {
            $positions = array_values(array_filter(
                $positions,
                static fn (array $row): bool => (int) ($row['work_location_id'] ?? 0) === $filterLocationId,
            ));
        }

        $includeTrails = $request->boolean('include_trails');
        if ($includeTrails) {
            $positions = array_map(function (array $row) use ($tracking): array {
                $employeeId = (int) ($row['employee_id'] ?? 0);
                $clockInId = (int) ($row['clock_in_entry_id'] ?? 0);
                if ($employeeId > 0 && $clockInId > 0) {
                    $trail = $tracking->trailForSession($employeeId, $clockInId);
                    $idle = $tracking->idleAlertsForSession($employeeId, $clockInId);
                    $row['trail'] = $trail;
                    $row['idle_alerts'] = $idle;
                    $row['movement_stats'] = $tracking->movementStatsForTrail($trail, $idle);
                } else {
                    $row['trail'] = [];
                    $row['idle_alerts'] = [];
                    $row['movement_stats'] = null;
                }

                return $row;
            }, $positions);
        }

        return response()->json([
            'positions' => $positions,
            'polled_at' => now('UTC')->toIso8601String(),
        ]);
    }

    public function trail(Request $request, LocationTrackingService $tracking): JsonResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;
        DB::setDefaultConnection($conn);

        $validated = $request->validate([
            'employee' => ['required', 'string', 'max:64'],
            'clock_in_entry_id' => ['required', 'integer', 'min:1'],
        ]);

        $employee = Employee::on($conn)
            ->where('public_id', $validated['employee'])
            ->firstOrFail();

        $clockIn = TimeClockEntry::on($conn)
            ->where('employee_id', $employee->id)
            ->where('event_type', TimeClockEntry::EVENT_CLOCK_IN)
            ->whereKey((int) $validated['clock_in_entry_id'])
            ->firstOrFail();

        $trail = $tracking->trailForSession((int) $employee->id, (int) $clockIn->id);
        $idleAlerts = $tracking->idleAlertsForSession((int) $employee->id, (int) $clockIn->id);

        return response()->json([
            'employee' => [
                'public_id' => $employee->public_id,
                'name' => $employee->full_legal_name ?: ($employee->email ?? 'Employee'),
            ],
            'clock_in_entry_id' => $clockIn->id,
            'clocked_in_at' => $clockIn->clocked_at?->toIso8601String(),
            'site_latitude' => $clockIn->expected_latitude !== null ? (float) $clockIn->expected_latitude : null,
            'site_longitude' => $clockIn->expected_longitude !== null ? (float) $clockIn->expected_longitude : null,
            'allowed_radius_meters' => $clockIn->allowed_radius_meters !== null
                ? (int) $clockIn->allowed_radius_meters
                : (int) config('time_clock.geofence_radius_meters', 300),
            'trail' => $trail,
            'idle_alerts' => $idleAlerts,
            'movement_stats' => $tracking->movementStatsForTrail($trail, $idleAlerts),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $livePositions
     */
    private function siteView(
        object $company,
        string $conn,
        array $livePositions,
        int $workLocationId,
        LocationTrackingService $tracking,
        string $listUrl,
    ): View {
        $location = WorkLocation::on($conn)->find($workLocationId);
        if (! $location instanceof WorkLocation) {
            return redirect()->route('admin.employees.location-tracking');
        }

        $atSite = array_values(array_filter(
            $livePositions,
            static fn (array $row): bool => (int) ($row['work_location_id'] ?? 0) === $workLocationId,
        ));

        $siteEmployees = [];
        foreach ($atSite as $index => $row) {
            $color = self::EMPLOYEE_PALETTE[$index % count(self::EMPLOYEE_PALETTE)];
            $employeeId = (int) ($row['employee_id'] ?? 0);
            $clockInId = (int) ($row['clock_in_entry_id'] ?? 0);
            $trail = ($employeeId > 0 && $clockInId > 0)
                ? $tracking->trailForSession($employeeId, $clockInId)
                : [];
            $idle = ($employeeId > 0 && $clockInId > 0)
                ? $tracking->idleAlertsForSession($employeeId, $clockInId)
                : [];
            $stats = $tracking->movementStatsForTrail($trail, $idle);

            $siteEmployees[] = array_merge($row, [
                'color' => $color,
                'trail' => $trail,
                'idle_alerts' => $idle,
                'movement_stats' => $stats,
            ]);
        }

        $siteLat = $location->latitude !== null ? (float) $location->latitude : null;
        $siteLng = $location->longitude !== null ? (float) $location->longitude : null;
        if ($siteLat === null && $siteEmployees !== []) {
            $siteLat = $siteEmployees[0]['site_latitude'] ?? null;
            $siteLng = $siteEmployees[0]['site_longitude'] ?? null;
        }

        $site = [
            'id' => $location->id,
            'name' => $location->name,
            'address' => $location->address,
            'latitude' => $siteLat,
            'longitude' => $siteLng,
            'allowed_radius_meters' => (int) config('time_clock.geofence_radius_meters', 300),
            'staff_count' => count($siteEmployees),
            'idle_count' => count(array_filter($siteEmployees, static fn (array $e): bool => ! empty($e['has_idle_alert']))),
        ];

        return view('admin.employees-location-tracking', [
            'company' => $company,
            'mode' => 'site',
            'clockedInRows' => $livePositions,
            'siteCards' => [],
            'selected' => null,
            'site' => $site,
            'siteEmployees' => $siteEmployees,
            'trail' => [],
            'idleAlerts' => [],
            'movementStats' => null,
            'livePollUrl' => route('admin.employees.location-tracking.live', [
                'work_location_id' => $location->id,
                'include_trails' => 1,
            ]),
            'listUrl' => $listUrl,
            'trailUrl' => route('admin.employees.location-tracking.trail'),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $positions
     * @return list<array<string, mixed>>
     */
    private function siteCardsFromPositions(array $positions): array
    {
        $grouped = [];
        foreach ($positions as $row) {
            $id = (int) ($row['work_location_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (! isset($grouped[$id])) {
                $grouped[$id] = [
                    'work_location_id' => $id,
                    'name' => $row['work_location_name'] ?: 'Work location',
                    'address' => $row['work_location_address'] ?? null,
                    'staff_count' => 0,
                    'idle_count' => 0,
                    'url' => $row['site_url'] ?? route('admin.employees.location-tracking', [
                        'work_location_id' => $id,
                    ]),
                    'names' => [],
                ];
            }
            $grouped[$id]['staff_count']++;
            if (! empty($row['has_idle_alert'])) {
                $grouped[$id]['idle_count']++;
            }
            if (count($grouped[$id]['names']) < 3) {
                $grouped[$id]['names'][] = $row['employee_name'] ?? 'Employee';
            }
        }

        usort($grouped, static fn (array $a, array $b): int => $b['staff_count'] <=> $a['staff_count']);

        return array_values($grouped);
    }
}
