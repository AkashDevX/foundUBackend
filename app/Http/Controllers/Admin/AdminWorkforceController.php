<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\LeaveType;
use App\Models\OrganizationPortalUser;
use App\Models\Shift;
use App\Models\WorkLocation;
use App\Support\AdminWeeklySchedule;
use App\Support\EmployeeJobTitles;
use App\Support\JobTitleDefaults;
use App\Support\PayrollAwardRateSeeder;
use App\Support\PayrollRateTypes;
use App\Support\ShiftBreaks;
use App\Support\WorkforceShifts;
use Illuminate\Support\Str;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class AdminWorkforceController extends Controller
{
    /**
     * @return array<int, string>
     */
    private function allowedShiftDays(): array
    {
        return ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    }

    /**
     * @param mixed $input
     * @return array<int, string>|null
     */
    private function normalizeShiftDays(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $allowed = $this->allowedShiftDays();
        $days = collect($input)
            ->map(fn ($d) => is_string($d) ? strtolower(trim($d)) : null)
            ->filter(fn ($d) => is_string($d) && in_array($d, $allowed, true))
            ->unique()
            ->values()
            ->all();

        return $days === [] ? null : $days;
    }

    /**
     * @return array{breaks: list<array{label: string, minutes: int, paid: bool}>|null, breaks_summary: string|null}
     */
    private function normalizeShiftBreaksPayload(Request $request): array
    {
        $breaks = ShiftBreaks::normalize($request->input('shift_breaks'));

        return [
            'breaks' => $breaks === [] ? null : $breaks,
            'breaks_summary' => ShiftBreaks::summaryFrom($breaks),
        ];
    }

    private function nominatimUserAgent(): string
    {
        return sprintf(
            '%s Workforce Admin (%s)',
            config('app.name', 'Laravel'),
            parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'
        );
    }

    public function index(Request $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('admin.workforce.departments');
    }

    public function departments(Request $request): View
    {
        return $this->renderSection($request, 'departments');
    }

    public function jobTitles(Request $request): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        PayrollAwardRateSeeder::ensureDefaults($conn);
        JobTitleDefaults::ensureDefaults($conn);

        $archived = $request->boolean('archived');

        $jobTitles = JobTitle::on($conn)
            ->withCount('assignedEmployees as employees_count')
            ->where('is_active', ! $archived)
            ->orderBy('name')
            ->get();

        $employees = Employee::on($conn)
            ->where('employment_status', 'active')
            ->orderBy('full_legal_name')
            ->orderBy('first_name')
            ->get(['id', 'full_legal_name', 'first_name', 'last_name', 'email']);

        return view('admin.job-titles.index', [
            'company' => $company,
            'jobTitles' => $jobTitles,
            'archived' => $archived,
            'employees' => $employees,
        ]);
    }

    public function showJobTitle(Request $request, int $jobTitle): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $title = JobTitle::on($conn)->withCount('assignedEmployees as employees_count')->whereKey($jobTitle)->firstOrFail();
        $tab = (string) $request->query('tab', 'employees');
        if (! in_array($tab, ['employees', 'settings', 'wages'], true)) {
            $tab = 'employees';
        }

        $employees = collect();
        $effectiveFrom = (string) config('payroll.default_rates_effective_from', '2025-07-01');
        $siblingIds = JobTitle::on($conn)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('id')
            ->all();
        $currentIndex = array_search($title->id, $siblingIds, true);
        $prevId = is_int($currentIndex) && $currentIndex > 0 ? $siblingIds[$currentIndex - 1] : null;
        $nextId = is_int($currentIndex) && $currentIndex < count($siblingIds) - 1 ? $siblingIds[$currentIndex + 1] : null;

        $assignableEmployees = collect();

        if ($tab === 'employees') {
            $employees = Employee::on($conn)
                ->with('workLocation')
                ->where(function ($q) use ($title) {
                    $q->where('job_title_id', $title->id)
                        ->orWhereHas('jobTitles', fn ($jq) => $jq->where('job_titles.id', $title->id));
                })
                ->orderBy('full_legal_name')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();

            $assignedIds = $employees->pluck('id');
            $assignableEmployees = Employee::on($conn)
                ->where('employment_status', 'active')
                ->when($assignedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $assignedIds))
                ->orderBy('full_legal_name')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'full_legal_name', 'first_name', 'last_name', 'email']);
        }

        return view('admin.job-titles.show', [
            'company' => $company,
            'jobTitle' => $title,
            'tab' => $tab,
            'employees' => $employees,
            'assignableEmployees' => $assignableEmployees,
            'effectiveFrom' => $effectiveFrom,
            'prevJobTitleId' => $prevId,
            'nextJobTitleId' => $nextId,
        ]);
    }

    public function assignJobTitleEmployees(Request $request, int $jobTitle): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $title = JobTitle::on($conn)->whereKey($jobTitle)->where('is_active', true)->firstOrFail();

        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer'],
        ]);

        EmployeeJobTitles::attachToTitle($conn, $title, $data['employee_ids']);

        return redirect()
            ->route('admin.workforce.job-titles.show', ['jobTitle' => $title->id])
            ->with('status', 'Employees added to this job title.');
    }

    public function archiveJobTitle(Request $request, int $jobTitle): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $target = JobTitle::on($conn)->whereKey($jobTitle)->firstOrFail();
        $target->forceFill(['is_active' => false])->save();

        return redirect()->route('admin.workforce.job-titles')->with('status', 'Job title archived.');
    }

    public function restoreJobTitle(Request $request, int $jobTitle): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $target = JobTitle::on($conn)->whereKey($jobTitle)->firstOrFail();
        $target->forceFill(['is_active' => true])->save();

        return redirect()->route('admin.workforce.job-titles', ['archived' => 1])->with('status', 'Job title restored.');
    }

    public function workLocations(Request $request): View
    {
        return $this->renderSection($request, 'work-locations');
    }

    public function shifts(Request $request): View
    {
        return $this->renderSection($request, 'shifts');
    }

    public function leaveTypes(Request $request): View
    {
        return $this->renderSection($request, 'leave-types');
    }

    /**
     * Normalize a free-text leave code into a stable machine key.
     */
    private function normalizeLeaveCode(?string $input, string $fallbackName): string
    {
        $source = is_string($input) && trim($input) !== '' ? $input : $fallbackName;
        $code = Str::of($source)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

        return $code === '' ? 'leave' : Str::limit($code, 32, '');
    }

    private function renderSection(Request $request, string $section): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $departments = Department::on($conn)->orderBy('name')->get();
        $jobTitles = JobTitle::on($conn)->orderBy('name')->get();
        $locations = WorkLocation::on($conn)->orderBy('name')->get();
        $shifts = Shift::on($conn)->orderBy('name')->get();
        $leaveTypes = LeaveType::on($conn)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.workforce', [
            'company' => $company,
            'departments' => $departments,
            'jobTitles' => $jobTitles,
            'workLocations' => $locations,
            'shifts' => $shifts,
            'leaveTypes' => $leaveTypes,
            'mapDefaultLat' => config('workforce.default_map_lat'),
            'mapDefaultLng' => config('workforce.default_map_lng'),
            'mapDefaultZoom' => config('workforce.default_map_zoom'),
            'section' => $section,
        ]);
    }

    /**
     * Forward geocode a free-text address via Nominatim search.
     *
     * @return array{lat: float, lng: float, display_name: string}|null
     */
    private function geocodeAddress(string $address): ?array
    {
        $query = trim($address);
        if ($query === '') {
            return null;
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => $this->nominatimUserAgent(),
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 1,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();
        if (! is_array($json) || ! isset($json[0]) || ! is_array($json[0])) {
            return null;
        }

        $first = $json[0];
        $display = $first['display_name'] ?? null;
        $lat = isset($first['lat']) ? (float) $first['lat'] : null;
        $lng = isset($first['lon']) ? (float) $first['lon'] : null;

        if (! is_string($display) || $display === '' || $lat === null || $lng === null) {
            return null;
        }

        return [
            'display_name' => $display,
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * Reverse geocode via OpenStreetMap Nominatim (server-side; polite User-Agent).
     *
     * @see https://operations.osmfoundation.org/policies/nominatim/
     */
    public function reverseGeocode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => $this->nominatimUserAgent(),
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $data['lat'],
                    'lon' => $data['lng'],
                    'format' => 'jsonv2',
                ]);
        } catch (\Throwable) {
            return response()->json([
                'ok' => false,
                'message' => 'Could not reach the geocoding service. Try again in a moment.',
            ], 502);
        }

        if (! $response->successful()) {
            return response()->json([
                'ok' => false,
                'message' => 'Geocoding service returned an error.',
            ], 502);
        }

        $json = $response->json();
        $display = is_array($json) ? ($json['display_name'] ?? null) : null;

        if (! is_string($display) || $display === '') {
            return response()->json([
                'ok' => false,
                'message' => 'No address found for this pin.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'display_name' => $display,
            'lat' => (float) $data['lat'],
            'lng' => (float) $data['lng'],
        ]);
    }

    public function searchGeocode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => $this->nominatimUserAgent(),
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $data['q'],
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 5,
                ]);
        } catch (\Throwable) {
            return response()->json([
                'ok' => false,
                'message' => 'Could not reach the geocoding service. Try again in a moment.',
                'suggestions' => [],
            ], 502);
        }

        if (! $response->successful()) {
            return response()->json([
                'ok' => false,
                'message' => 'Geocoding service returned an error.',
                'suggestions' => [],
            ], 502);
        }

        $json = $response->json();
        if (! is_array($json)) {
            return response()->json([
                'ok' => true,
                'suggestions' => [],
            ]);
        }

        $suggestions = collect($json)
            ->filter(fn ($row) => is_array($row) && is_string($row['display_name'] ?? null))
            ->take(5)
            ->map(fn (array $row) => [
                'display_name' => (string) $row['display_name'],
                'lat' => isset($row['lat']) ? (float) $row['lat'] : null,
                'lng' => isset($row['lon']) ? (float) $row['lon'] : null,
            ])
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'suggestions' => $suggestions,
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'department_name' => ['required', 'string', 'max:160'],
            'department_code' => ['nullable', 'string', 'max:32'],
        ]);

        Department::on($conn)->create([
            'name' => $data['department_name'],
            'code' => $data['department_code'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->back()->with('status', 'Department created.');
    }

    public function updateDepartment(Request $request, int $department): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'department_name' => ['required', 'string', 'max:160'],
            'department_code' => ['nullable', 'string', 'max:32'],
        ]);

        $target = Department::on($conn)->whereKey($department)->firstOrFail();
        $target->forceFill([
            'name' => $data['department_name'],
            'code' => $data['department_code'] ?? null,
        ])->save();

        return redirect()->back()->with('status', 'Department updated.');
    }

    public function storeJobTitle(Request $request): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'job_title_name' => ['required', 'string', 'max:160'],
            'hourly_wage' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'wage_effective_from' => ['required', 'date'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer'],
        ]);

        $color = strtolower($data['color']);
        if (! in_array($color, array_map('strtolower', JobTitle::colorPalette()), true)) {
            $color = JobTitle::colorPalette()[0];
        }

        $title = JobTitle::on($conn)->create([
            'name' => $data['job_title_name'],
            'hourly_wage' => round((float) $data['hourly_wage'], 2),
            'wage_effective_from' => $data['wage_effective_from'],
            'color' => $color,
            'is_active' => true,
        ]);

        $employeeIds = collect($data['employee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($employeeIds->isNotEmpty()) {
            $validIds = Employee::on($conn)->whereIn('id', $employeeIds)->pluck('id');
            $now = now();
            foreach ($validIds as $employeeId) {
                DB::connection($conn)->table('employee_job_title')->updateOrInsert(
                    ['employee_id' => $employeeId, 'job_title_id' => $title->id],
                    ['is_primary' => false, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        return redirect()->route('admin.workforce.job-titles')->with('status', 'Job title created.');
    }

    public function updateJobTitle(Request $request, int $jobTitle): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'job_title_name' => ['required', 'string', 'max:160'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $color = strtolower($data['color']);

        $target = JobTitle::on($conn)->whereKey($jobTitle)->firstOrFail();

        $target->forceFill([
            'name' => $data['job_title_name'],
            'color' => $color,
        ])->save();

        return redirect()
            ->route('admin.workforce.job-titles.show', ['jobTitle' => $target->id, 'tab' => 'settings'])
            ->with('status', 'Job title updated.');
    }

    public function updateJobTitleRates(Request $request, int $jobTitle): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $target = JobTitle::on($conn)->whereKey($jobTitle)->firstOrFail();

        $data = $request->validate([
            'hourly_wage' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        $target->forceFill([
            'hourly_wage' => round((float) $data['hourly_wage'], 2),
        ])->save();

        return redirect()
            ->route('admin.workforce.job-titles.show', ['jobTitle' => $target->id, 'tab' => 'wages'])
            ->with('status', 'Wage saved for '.$target->name.'.');
    }

    public function storeWorkLocation(Request $request): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $request->merge([
            'latitude' => $request->filled('latitude') ? $request->input('latitude') : null,
            'longitude' => $request->filled('longitude') ? $request->input('longitude') : null,
        ]);

        $data = $request->validate([
            'location_name' => ['required', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:2000'],
            'location_notes' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lng = isset($data['longitude']) ? (float) $data['longitude'] : null;

        // If user typed a manual address but did not choose a suggestion,
        // resolve one best-match coordinate pair server-side before saving.
        if ($lat === null && $lng === null && is_string($data['address'] ?? null) && trim((string) $data['address']) !== '') {
            $resolved = $this->geocodeAddress((string) $data['address']);
            if ($resolved !== null) {
                $lat = $resolved['lat'];
                $lng = $resolved['lng'];
                $data['address'] = $resolved['display_name'];
            }
        }

        if ($lat === null xor $lng === null) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['latitude' => 'Provide both latitude and longitude from the map, or leave both empty.']);
        }

        WorkLocation::on($conn)->create([
            'name' => $data['location_name'],
            'address' => $data['address'] ?? null,
            'latitude' => $lat,
            'longitude' => $lng,
            'notes' => $data['location_notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->back()->with('status', 'Work location created.');
    }

    public function updateWorkLocation(Request $request, int $location): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $request->merge([
            'latitude' => $request->filled('latitude') ? $request->input('latitude') : null,
            'longitude' => $request->filled('longitude') ? $request->input('longitude') : null,
        ]);

        $data = $request->validate([
            'location_name' => ['required', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:2000'],
            'location_notes' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lng = isset($data['longitude']) ? (float) $data['longitude'] : null;

        if ($lat === null && $lng === null && is_string($data['address'] ?? null) && trim((string) $data['address']) !== '') {
            $resolved = $this->geocodeAddress((string) $data['address']);
            if ($resolved !== null) {
                $lat = $resolved['lat'];
                $lng = $resolved['lng'];
                $data['address'] = $resolved['display_name'];
            }
        }

        if ($lat === null xor $lng === null) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['latitude' => 'Provide both latitude and longitude, or leave both empty.']);
        }

        $target = WorkLocation::on($conn)->whereKey($location)->firstOrFail();
        $target->forceFill([
            'name' => $data['location_name'],
            'address' => $data['address'] ?? null,
            'latitude' => $lat,
            'longitude' => $lng,
            'notes' => $data['location_notes'] ?? null,
        ])->save();

        return redirect()->back()->with('status', 'Work location updated.');
    }

    public function storeShift(Request $request): RedirectResponse|JsonResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $shift = WorkforceShifts::createOnConnection($conn, $request);

        if ($request->expectsJson()) {
            return response()->json(WorkforceShifts::optionPayload($shift));
        }

        return redirect()->back()->with('status', 'Shift created.');
    }

    public function updateShift(Request $request, int $shift): RedirectResponse|JsonResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $target = Shift::on($conn)->whereKey($shift)->firstOrFail();
        $result = WorkforceShifts::updateOnConnection($conn, $target, $request);
        /** @var Shift $updated */
        $updated = $result['shift'];

        if ($request->expectsJson()) {
            $catalog = WorkforceShifts::catalogForConnection($conn, collect([$updated]));
            $entry = $catalog[0] ?? WorkforceShifts::catalogEntry($updated);

            return response()->json([
                ...$entry,
                'times_changed' => $result['times_changed'],
                'schedule_rows_synced' => $result['schedule_rows_synced'],
            ]);
        }

        return redirect()->back()->with('status', 'Shift updated.');
    }

    public function storeLeaveType(Request $request): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'leave_type_name' => ['required', 'string', 'max:160'],
            'leave_type_code' => ['nullable', 'string', 'max:32'],
            'leave_type_is_paid' => ['nullable', 'boolean'],
            'leave_type_annual_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'leave_type_requires_approval' => ['nullable', 'boolean'],
            'leave_type_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $code = $this->normalizeLeaveCode($data['leave_type_code'] ?? null, $data['leave_type_name']);

        if (LeaveType::on($conn)->where('code', $code)->exists()) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['leave_type_code' => 'A leave type with this code already exists.']);
        }

        $nextSort = (int) LeaveType::on($conn)->max('sort_order') + 1;

        LeaveType::on($conn)->create([
            'name' => $data['leave_type_name'],
            'code' => $code,
            'is_paid' => $request->boolean('leave_type_is_paid'),
            'default_annual_hours' => isset($data['leave_type_annual_hours']) ? (float) $data['leave_type_annual_hours'] : null,
            'requires_approval' => $request->boolean('leave_type_requires_approval'),
            'is_active' => true,
            'sort_order' => $nextSort,
            'notes' => $data['leave_type_notes'] ?? null,
            'created_by' => $portalUser->name ?: $portalUser->email,
        ]);

        return redirect()->back()->with('status', 'Leave type created.');
    }

    public function updateLeaveType(Request $request, int $leaveType): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'leave_type_name' => ['required', 'string', 'max:160'],
            'leave_type_is_paid' => ['nullable', 'boolean'],
            'leave_type_annual_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'leave_type_requires_approval' => ['nullable', 'boolean'],
            'leave_type_is_active' => ['nullable', 'boolean'],
            'leave_type_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $target = LeaveType::on($conn)->whereKey($leaveType)->firstOrFail();
        $target->forceFill([
            'name' => $data['leave_type_name'],
            'is_paid' => $request->boolean('leave_type_is_paid'),
            'default_annual_hours' => isset($data['leave_type_annual_hours']) ? (float) $data['leave_type_annual_hours'] : null,
            'requires_approval' => $request->boolean('leave_type_requires_approval'),
            'is_active' => $request->boolean('leave_type_is_active'),
            'notes' => $data['leave_type_notes'] ?? null,
        ])->save();

        return redirect()->back()->with('status', 'Leave type updated.');
    }
}
