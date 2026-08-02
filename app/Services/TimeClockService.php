<?php

namespace App\Services;

use App\Exceptions\TimeClockException;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use App\Models\WorkLocation;
use App\Support\TimeClockScheduledShift;
use App\Support\GeoDistance;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class TimeClockService
{
    public function geofenceRadiusMeters(): int
    {
        $radius = (int) config('time_clock.geofence_radius_meters', 300);

        return max(10, min($radius, 5_000));
    }

    /**
     * @return array<string, mixed>
     */
    public function statusFor(Employee $employee): array
    {
        $employee->loadMissing([
            'assignedDepartment',
            'workLocation',
            'assignedShift',
            'assignmentShifts.shiftTemplate',
        ]);

        $session = $this->resolveOpenSession($employee);
        $isClockedIn = $session !== null;
        $isOnBreak = $session !== null && $session['is_on_break'];
        $clockInEntry = $session['clock_in'] ?? null;
        $lastEntry = $session['last'] ?? $this->latestEntryFor($employee);
        $location = $employee->workLocation;
        $hasCoordinates = $this->workLocationHasCoordinates($location);
        $shiftIssue = $isClockedIn ? null : TimeClockScheduledShift::shiftIssue($employee);
        $assignmentReady = $employee->work_location_id !== null && $hasCoordinates;

        $breaksPayload = [];
        $totalBreakSeconds = 0;
        if ($session !== null) {
            foreach ($session['breaks'] as $break) {
                $start = $break['start'];
                $end = $break['end'] ?? null;
                $seconds = null;
                if ($start->clocked_at !== null) {
                    $endAt = $end?->clocked_at ?? ($isOnBreak && $end === null ? now('UTC') : null);
                    if ($endAt !== null) {
                        $seconds = (int) $start->clocked_at->diffInSeconds($endAt);
                        if ($end !== null) {
                            $totalBreakSeconds += $seconds;
                        }
                    }
                }
                $breaksPayload[] = [
                    'started_at' => $start->clocked_at?->toIso8601String(),
                    'ended_at' => $end?->clocked_at?->toIso8601String(),
                    'duration_seconds' => $seconds,
                    'is_open' => $end === null,
                ];
            }
            if ($isOnBreak) {
                $openBreak = $session['open_break_start'];
                if ($openBreak instanceof TimeClockEntry && $openBreak->clocked_at !== null) {
                    $totalBreakSeconds += (int) $openBreak->clocked_at->diffInSeconds(now('UTC'));
                }
            }
        }

        return [
            'is_clocked_in' => $isClockedIn,
            'is_on_break' => $isOnBreak,
            'can_clock_in' => ! $isClockedIn && $assignmentReady && $shiftIssue === null,
            'can_clock_out' => $isClockedIn,
            'can_break_in' => $isClockedIn && ! $isOnBreak,
            'can_break_out' => $isOnBreak,
            'geofence_radius_meters' => $this->geofenceRadiusMeters(),
            'open_session' => $isClockedIn && $clockInEntry instanceof TimeClockEntry
                ? [
                    'entry_id' => $clockInEntry->id,
                    'clocked_in_at' => $clockInEntry->clocked_at?->toIso8601String(),
                    'work_location_id' => $clockInEntry->work_location_id,
                    'within_geofence' => (bool) $clockInEntry->within_geofence,
                    'geofence_latitude' => $clockInEntry->expected_latitude !== null
                        ? (float) $clockInEntry->expected_latitude
                        : null,
                    'geofence_longitude' => $clockInEntry->expected_longitude !== null
                        ? (float) $clockInEntry->expected_longitude
                        : null,
                    // Live config radius (not the punch stamp) so raising
                    // TIME_CLOCK_GEOFENCE_RADIUS_METERS (e.g. 100 → 300) applies
                    // immediately to open sessions on the mobile client.
                    'allowed_radius_meters' => $this->geofenceRadiusMeters(),
                    'break_started_at' => $isOnBreak
                        ? ($session['open_break_start']?->clocked_at?->toIso8601String())
                        : null,
                    'breaks' => $breaksPayload,
                    'total_break_seconds' => $totalBreakSeconds,
                ]
                : null,
            'last_event' => $lastEntry instanceof TimeClockEntry ? $lastEntry->toMobilePayload() : null,
            'assignment_ready' => $assignmentReady,
            'assignment_issue' => $this->assignmentIssue($employee, $hasCoordinates),
            'shift_issue' => $shiftIssue,
            'scheduled_shift' => TimeClockScheduledShift::todayShiftForDisplay($employee),
            'work_assignment' => $employee->workAssignmentForApi(),
        ];
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null}  $device
     * @return array{entry: TimeClockEntry, time_clock: array<string, mixed>}
     */
    public function clockIn(Employee $employee, array $device): array
    {
        return DB::transaction(function () use ($employee, $device) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $employee->loadMissing([
                'workLocation',
                'assignedDepartment',
                'assignedShift',
                'assignmentShifts.shiftTemplate',
            ]);

            $this->assertCanClockIn($employee);

            $scheduledShift = $this->assertScheduledShiftForClockIn($employee);

            $location = $this->resolveClockInWorkLocation($employee, $scheduledShift);
            if (! $location instanceof WorkLocation) {
                throw new TimeClockException('work_location_not_found', 'Assigned work location not found.');
            }

            $geofence = $this->evaluateGeofence(
                $location,
                $device['latitude'],
                $device['longitude'],
                $device['accuracy_meters'] ?? null,
            );
            $this->assertWithinGeofence($geofence);

            $entry = $this->createEntry(
                $employee,
                TimeClockEntry::EVENT_CLOCK_IN,
                $device,
                $location,
                $geofence,
                TimeClockEntry::PUNCH_SOURCE_MANUAL,
                $scheduledShift->shift_id,
            );

            return [
                'entry' => $entry,
                'time_clock' => $this->statusFor($employee),
            ];
        });
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null, comment?: string|null}  $device
     * @return array{entry: TimeClockEntry, time_clock: array<string, mixed>}
     */
    public function clockOut(Employee $employee, array $device): array
    {
        return DB::transaction(function () use ($employee, $device) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $employee->loadMissing(['workLocation', 'assignedDepartment', 'assignedShift']);

            $session = $this->assertCanClockOut($employee);

            $location = $this->resolveSessionWorkLocation($employee, $session['clock_in']);
            if (! $location instanceof WorkLocation) {
                throw new TimeClockException('work_location_not_found', 'Assigned work location not found.');
            }

            $geofence = $this->evaluateSessionGeofence(
                $session['clock_in'],
                $location,
                $device['latitude'],
                $device['longitude'],
                $device['accuracy_meters'] ?? null,
            );
            $this->assertWithinGeofence($geofence);

            if ($session['is_on_break']) {
                $this->createEntry(
                    $employee,
                    TimeClockEntry::EVENT_BREAK_END,
                    $device,
                    $location,
                    $geofence,
                    TimeClockEntry::PUNCH_SOURCE_MANUAL,
                    $session['clock_in']->shift_id,
                );
            }

            $entry = $this->createEntry(
                $employee,
                TimeClockEntry::EVENT_CLOCK_OUT,
                $device,
                $location,
                $geofence,
                TimeClockEntry::PUNCH_SOURCE_MANUAL,
                $session['clock_in']->shift_id,
            );

            return [
                'entry' => $entry,
                'time_clock' => $this->statusFor($employee),
            ];
        });
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null}  $device
     * @return array{entry: TimeClockEntry, time_clock: array<string, mixed>}
     */
    public function breakStart(Employee $employee, array $device): array
    {
        return DB::transaction(function () use ($employee, $device) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $employee->loadMissing(['workLocation', 'assignedDepartment', 'assignedShift']);

            $session = $this->assertCanBreakStart($employee);

            $location = $this->resolveSessionWorkLocation($employee, $session['clock_in']);
            if (! $location instanceof WorkLocation) {
                throw new TimeClockException('work_location_not_found', 'Assigned work location not found.');
            }

            $geofence = $this->evaluateSessionGeofence(
                $session['clock_in'],
                $location,
                $device['latitude'],
                $device['longitude'],
                $device['accuracy_meters'] ?? null,
            );
            $this->assertWithinGeofence($geofence);

            $entry = $this->createEntry(
                $employee,
                TimeClockEntry::EVENT_BREAK_START,
                $device,
                $location,
                $geofence,
                TimeClockEntry::PUNCH_SOURCE_MANUAL,
                $session['clock_in']->shift_id,
            );

            return [
                'entry' => $entry,
                'time_clock' => $this->statusFor($employee),
            ];
        });
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null}  $device
     * @return array{entry: TimeClockEntry, time_clock: array<string, mixed>}
     */
    public function breakEnd(Employee $employee, array $device): array
    {
        return DB::transaction(function () use ($employee, $device) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $employee->loadMissing(['workLocation', 'assignedDepartment', 'assignedShift']);

            $session = $this->assertCanBreakEnd($employee);

            $location = $this->resolveSessionWorkLocation($employee, $session['clock_in']);
            if (! $location instanceof WorkLocation) {
                throw new TimeClockException('work_location_not_found', 'Assigned work location not found.');
            }

            $geofence = $this->evaluateSessionGeofence(
                $session['clock_in'],
                $location,
                $device['latitude'],
                $device['longitude'],
                $device['accuracy_meters'] ?? null,
            );
            $this->assertWithinGeofence($geofence);

            $entry = $this->createEntry(
                $employee,
                TimeClockEntry::EVENT_BREAK_END,
                $device,
                $location,
                $geofence,
                TimeClockEntry::PUNCH_SOURCE_MANUAL,
                $session['clock_in']->shift_id,
            );

            return [
                'entry' => $entry,
                'time_clock' => $this->statusFor($employee),
            ];
        });
    }

    /**
     * Clock out when the employee leaves the geofence while still clocked in.
     *
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null}  $device
     * @return array{entry: TimeClockEntry, time_clock: array<string, mixed>}
     */
    public function autoClockOutOnGeofenceExit(Employee $employee, array $device): array
    {
        return DB::transaction(function () use ($employee, $device) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $employee->loadMissing(['workLocation', 'assignedDepartment', 'assignedShift']);

            $session = $this->assertCanClockOut($employee);

            $location = $this->resolveSessionWorkLocation($employee, $session['clock_in']);
            if (! $location instanceof WorkLocation) {
                throw new TimeClockException('work_location_not_found', 'Assigned work location not found.');
            }

            $geofence = $this->evaluateSessionGeofence(
                $session['clock_in'],
                $location,
                $device['latitude'],
                $device['longitude'],
                $device['accuracy_meters'] ?? null,
            );
            $this->assertOutsideGeofenceForAutoClockOut($geofence, $device['accuracy_meters'] ?? null);

            if ($session['is_on_break']) {
                $this->createEntry(
                    $employee,
                    TimeClockEntry::EVENT_BREAK_END,
                    $device,
                    $location,
                    $geofence,
                    TimeClockEntry::PUNCH_SOURCE_AUTO_GEOFENCE_EXIT,
                    $session['clock_in']->shift_id,
                );
            }

            $entry = $this->createEntry(
                $employee,
                TimeClockEntry::EVENT_CLOCK_OUT,
                $device,
                $location,
                $geofence,
                TimeClockEntry::PUNCH_SOURCE_AUTO_GEOFENCE_EXIT,
                $session['clock_in']->shift_id,
            );

            return [
                'entry' => $entry,
                'time_clock' => $this->statusFor($employee),
            ];
        });
    }

    /**
     * Close an open session from a server-side process (scheduled shift-end / max-hours
     * safety net) — no device coordinates are required. Returns null when the employee is
     * not currently clocked in, so callers can safely run it over a batch.
     */
    public function systemClockOut(
        Employee $employee,
        CarbonInterface $clockOutAt,
        string $punchSource,
        ?string $comment = null,
    ): ?TimeClockEntry {
        return DB::transaction(function () use ($employee, $clockOutAt, $punchSource, $comment) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);

            $session = $this->resolveOpenSession($employee);
            if ($session === null) {
                return null;
            }

            $clockIn = $session['clock_in'];
            $employee->loadMissing('workLocation');
            $location = $employee->workLocation;
            $expectedLat = $this->workLocationHasCoordinates($location) ? (float) $location->latitude : null;
            $expectedLng = $this->workLocationHasCoordinates($location) ? (float) $location->longitude : null;

            // Keep clocked_at within [clock-in, now] so payroll totals never go negative
            // or land in the future when we backdate to the scheduled shift end.
            $at = $clockOutAt->copy()->utc();
            $clockInAt = $clockIn->clocked_at;
            if ($clockInAt !== null && $at->lessThan($clockInAt)) {
                $at = $clockInAt->copy();
            }
            $nowUtc = now('UTC');
            if ($at->greaterThan($nowUtc)) {
                $at = $nowUtc;
            }

            $baseAttributes = [
                'employee_id' => $employee->id,
                'clocked_at' => $at,
                'device_latitude' => $expectedLat ?? 0,
                'device_longitude' => $expectedLng ?? 0,
                'device_accuracy_meters' => null,
                'work_location_id' => $location?->id ?? $clockIn->work_location_id,
                'expected_latitude' => $expectedLat,
                'expected_longitude' => $expectedLng,
                'distance_from_site_meters' => $expectedLat !== null ? 0 : null,
                'allowed_radius_meters' => $this->geofenceRadiusMeters(),
                'within_geofence' => true,
                'punch_source' => $punchSource,
                'department_id' => $employee->department_id,
                'shift_id' => $clockIn->shift_id ?? $employee->shift_id,
            ];

            if ($session['is_on_break']) {
                TimeClockEntry::query()->create([
                    ...$baseAttributes,
                    'event_type' => TimeClockEntry::EVENT_BREAK_END,
                    'comment' => null,
                ]);
            }

            return TimeClockEntry::query()->create([
                ...$baseAttributes,
                'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
                'comment' => $comment !== null ? mb_substr($comment, 0, 2000) : null,
            ]);
        });
    }

    private function latestEntryFor(Employee $employee): ?TimeClockEntry
    {
        return TimeClockEntry::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('clocked_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{
     *     clock_in: TimeClockEntry,
     *     last: TimeClockEntry,
     *     is_on_break: bool,
     *     open_break_start: TimeClockEntry|null,
     *     breaks: list<array{start: TimeClockEntry, end: TimeClockEntry|null}>,
     * }|null
     */
    private function resolveOpenSession(Employee $employee): ?array
    {
        $entries = TimeClockEntry::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('clocked_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $last = $entries->first();
        if ($last === null || ! in_array($last->event_type, TimeClockEntry::ON_SHIFT_EVENTS, true)) {
            return null;
        }

        $sessionEntries = [];
        foreach ($entries as $entry) {
            if ($entry->event_type === TimeClockEntry::EVENT_CLOCK_OUT) {
                break;
            }
            $sessionEntries[] = $entry;
            if ($entry->event_type === TimeClockEntry::EVENT_CLOCK_IN) {
                break;
            }
        }

        $sessionEntries = array_reverse($sessionEntries);
        $clockIn = $sessionEntries[0] ?? null;
        if (! $clockIn instanceof TimeClockEntry || $clockIn->event_type !== TimeClockEntry::EVENT_CLOCK_IN) {
            return null;
        }

        $breaks = [];
        $openBreakStart = null;
        foreach ($sessionEntries as $entry) {
            if ($entry->event_type === TimeClockEntry::EVENT_BREAK_START) {
                $openBreakStart = $entry;
                continue;
            }
            if ($entry->event_type === TimeClockEntry::EVENT_BREAK_END && $openBreakStart instanceof TimeClockEntry) {
                $breaks[] = ['start' => $openBreakStart, 'end' => $entry];
                $openBreakStart = null;
            }
        }
        if ($openBreakStart instanceof TimeClockEntry) {
            $breaks[] = ['start' => $openBreakStart, 'end' => null];
        }

        return [
            'clock_in' => $clockIn,
            'last' => $last,
            'is_on_break' => $last->event_type === TimeClockEntry::EVENT_BREAK_START,
            'open_break_start' => $last->event_type === TimeClockEntry::EVENT_BREAK_START ? $last : null,
            'breaks' => $breaks,
        ];
    }

    private function assertCanClockIn(Employee $employee): void
    {
        if ($employee->work_location_id === null) {
            throw new TimeClockException(
                'no_work_location_assigned',
                'No work location has been assigned. Contact your administrator before clocking in.',
            );
        }

        if (! $this->workLocationHasCoordinates($employee->workLocation)) {
            throw new TimeClockException(
                'work_location_missing_coordinates',
                'Your assigned work site does not have map coordinates yet. Contact your administrator.',
            );
        }

        if ($this->resolveOpenSession($employee) !== null) {
            throw new TimeClockException(
                'already_clocked_in',
                'You are already clocked in. Clock out before starting another shift.',
            );
        }
    }

    private function assertScheduledShiftForClockIn(Employee $employee): EmployeeScheduleShift
    {
        $issue = TimeClockScheduledShift::shiftIssue($employee);
        if ($issue === TimeClockScheduledShift::ISSUE_NO_SHIFT_TODAY) {
            throw new TimeClockException(
                TimeClockScheduledShift::ISSUE_NO_SHIFT_TODAY,
                "You don't have any shifts today.",
            );
        }

        $shift = TimeClockScheduledShift::findShiftForClockIn($employee);
        if (! $shift instanceof EmployeeScheduleShift) {
            throw new TimeClockException(
                TimeClockScheduledShift::ISSUE_NO_SHIFT_TODAY,
                "You don't have any shifts today.",
            );
        }

        return $shift;
    }

    private function resolveClockInWorkLocation(Employee $employee, EmployeeScheduleShift $scheduledShift): ?WorkLocation
    {
        $scheduledShift->loadMissing('workLocation');
        $fromShift = $scheduledShift->workLocation;
        if ($fromShift instanceof WorkLocation && $this->workLocationHasCoordinates($fromShift)) {
            return $fromShift;
        }

        $assigned = $employee->workLocation;

        return $assigned instanceof WorkLocation ? $assigned : null;
    }

    /**
     * @return array{
     *     clock_in: TimeClockEntry,
     *     last: TimeClockEntry,
     *     is_on_break: bool,
     *     open_break_start: TimeClockEntry|null,
     *     breaks: list<array{start: TimeClockEntry, end: TimeClockEntry|null}>,
     * }
     */
    private function assertCanClockOut(Employee $employee): array
    {
        if ($employee->work_location_id === null) {
            throw new TimeClockException(
                'no_work_location_assigned',
                'No work location has been assigned.',
            );
        }

        if (! $this->workLocationHasCoordinates($employee->workLocation)) {
            throw new TimeClockException(
                'work_location_missing_coordinates',
                'Your assigned work site does not have map coordinates yet.',
            );
        }

        $session = $this->resolveOpenSession($employee);
        if ($session === null) {
            throw new TimeClockException(
                'not_clocked_in',
                'You are not clocked in.',
            );
        }

        return $session;
    }

    /**
     * @return array{
     *     clock_in: TimeClockEntry,
     *     last: TimeClockEntry,
     *     is_on_break: bool,
     *     open_break_start: TimeClockEntry|null,
     *     breaks: list<array{start: TimeClockEntry, end: TimeClockEntry|null}>,
     * }
     */
    private function assertCanBreakStart(Employee $employee): array
    {
        if ($employee->work_location_id === null) {
            throw new TimeClockException(
                'no_work_location_assigned',
                'No work location has been assigned.',
            );
        }

        if (! $this->workLocationHasCoordinates($employee->workLocation)) {
            throw new TimeClockException(
                'work_location_missing_coordinates',
                'Your assigned work site does not have map coordinates yet.',
            );
        }

        $session = $this->resolveOpenSession($employee);
        if ($session === null) {
            throw new TimeClockException(
                'not_clocked_in',
                'You must be clocked in before starting a break.',
            );
        }

        if ($session['is_on_break']) {
            throw new TimeClockException(
                'already_on_break',
                'You are already on break. End the current break before starting another.',
            );
        }

        return $session;
    }

    /**
     * @return array{
     *     clock_in: TimeClockEntry,
     *     last: TimeClockEntry,
     *     is_on_break: bool,
     *     open_break_start: TimeClockEntry|null,
     *     breaks: list<array{start: TimeClockEntry, end: TimeClockEntry|null}>,
     * }
     */
    private function assertCanBreakEnd(Employee $employee): array
    {
        if ($employee->work_location_id === null) {
            throw new TimeClockException(
                'no_work_location_assigned',
                'No work location has been assigned.',
            );
        }

        if (! $this->workLocationHasCoordinates($employee->workLocation)) {
            throw new TimeClockException(
                'work_location_missing_coordinates',
                'Your assigned work site does not have map coordinates yet.',
            );
        }

        $session = $this->resolveOpenSession($employee);
        if ($session === null || ! $session['is_on_break']) {
            throw new TimeClockException(
                'not_on_break',
                'You are not currently on break.',
            );
        }

        return $session;
    }

    /**
     * @param  array{
     *     distance_meters: float,
     *     allowed_radius_meters: int,
     *     within_geofence: bool,
     *     expected_latitude: float,
     *     expected_longitude: float,
     * }  $geofence
     */
    private function assertWithinGeofence(array $geofence): void
    {
        if ($geofence['within_geofence']) {
            return;
        }

        throw new TimeClockException(
            'outside_geofence',
            sprintf(
                'You must be at your assigned work site to clock in or out. You are about %.0f m away; the allowed radius is %d m.',
                $geofence['distance_meters'],
                $geofence['allowed_radius_meters'],
            ),
            422,
            [
                'distance_from_site_meters' => $geofence['distance_meters'],
                'allowed_radius_meters' => $geofence['allowed_radius_meters'],
                'expected_latitude' => $geofence['expected_latitude'],
                'expected_longitude' => $geofence['expected_longitude'],
            ],
        );
    }

    /**
     * @param  array{
     *     distance_meters: float,
     *     allowed_radius_meters: int,
     *     within_geofence: bool,
     *     expected_latitude: float,
     *     expected_longitude: float,
     * }  $geofence
     */
    private function assertOutsideGeofenceForAutoClockOut(array $geofence, ?float $accuracyMeters = null): void
    {
        $exitRadius = $geofence['allowed_radius_meters']
            + $this->geofenceExitExtraMeters()
            + $this->accuracyBufferMeters($accuracyMeters);

        if ($geofence['distance_meters'] > $exitRadius) {
            return;
        }

        throw new TimeClockException(
            'still_within_geofence',
            sprintf(
                'You are still within the work site geofence (about %.0f m away; auto clock-out requires leaving beyond %d m).',
                $geofence['distance_meters'],
                (int) round($exitRadius),
            ),
            422,
            [
                'distance_from_site_meters' => $geofence['distance_meters'],
                'allowed_radius_meters' => $geofence['allowed_radius_meters'],
                'exit_radius_meters' => $exitRadius,
                'expected_latitude' => $geofence['expected_latitude'],
                'expected_longitude' => $geofence['expected_longitude'],
            ],
        );
    }

    private function geofenceExitExtraMeters(): int
    {
        return max(0, (int) config('time_clock.geofence_exit_extra_meters', 50));
    }

    private function geofenceAccuracyBufferCapMeters(): int
    {
        return max(0, (int) config('time_clock.geofence_accuracy_buffer_cap_meters', 100));
    }

    private function accuracyBufferMeters(?float $accuracyMeters): float
    {
        if ($accuracyMeters === null || ! is_finite($accuracyMeters) || $accuracyMeters <= 0) {
            return 0.0;
        }

        return min($accuracyMeters, (float) $this->geofenceAccuracyBufferCapMeters());
    }

    /**
     * Prefer the work location used at clock-in so auto clock-out / breaks stay
     * anchored to the same site the employee punched into.
     */
    private function resolveSessionWorkLocation(Employee $employee, TimeClockEntry $clockIn): ?WorkLocation
    {
        if ($clockIn->work_location_id !== null) {
            $fromSession = WorkLocation::query()->find($clockIn->work_location_id);
            if ($fromSession instanceof WorkLocation && $this->workLocationHasCoordinates($fromSession)) {
                return $fromSession;
            }
        }

        $assigned = $employee->workLocation;

        return $assigned instanceof WorkLocation ? $assigned : null;
    }

    /**
     * @return array{
     *     distance_meters: float,
     *     allowed_radius_meters: int,
     *     within_geofence: bool,
     *     expected_latitude: float,
     *     expected_longitude: float,
     * }
     */
    private function evaluateSessionGeofence(
        TimeClockEntry $clockIn,
        WorkLocation $location,
        float $deviceLatitude,
        float $deviceLongitude,
        ?float $accuracyMeters = null,
    ): array {
        $expectedLat = $clockIn->expected_latitude !== null
            ? (float) $clockIn->expected_latitude
            : (float) $location->latitude;
        $expectedLng = $clockIn->expected_longitude !== null
            ? (float) $clockIn->expected_longitude
            : (float) $location->longitude;
        // Use current config for live enter/exit checks. The radius stamped on
        // the clock-in row remains historical audit data only.
        $radius = $this->geofenceRadiusMeters();
        $distance = GeoDistance::metersBetween(
            $deviceLatitude,
            $deviceLongitude,
            $expectedLat,
            $expectedLng,
        );
        $enterRadius = $radius + $this->accuracyBufferMeters($accuracyMeters);

        return [
            'distance_meters' => round($distance, 2),
            'allowed_radius_meters' => $radius,
            'within_geofence' => $distance <= $enterRadius,
            'expected_latitude' => $expectedLat,
            'expected_longitude' => $expectedLng,
        ];
    }

    /**
     * @return array{
     *     distance_meters: float,
     *     allowed_radius_meters: int,
     *     within_geofence: bool,
     *     expected_latitude: float,
     *     expected_longitude: float,
     * }
     */
    private function evaluateGeofence(
        WorkLocation $location,
        float $deviceLatitude,
        float $deviceLongitude,
        ?float $accuracyMeters = null,
    ): array {
        $expectedLat = (float) $location->latitude;
        $expectedLng = (float) $location->longitude;
        $radius = $this->geofenceRadiusMeters();
        $distance = GeoDistance::metersBetween(
            $deviceLatitude,
            $deviceLongitude,
            $expectedLat,
            $expectedLng,
        );
        $enterRadius = $radius + $this->accuracyBufferMeters($accuracyMeters);

        return [
            'distance_meters' => round($distance, 2),
            'allowed_radius_meters' => $radius,
            'within_geofence' => $distance <= $enterRadius,
            'expected_latitude' => $expectedLat,
            'expected_longitude' => $expectedLng,
        ];
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null, comment?: string|null}  $device
     * @param  array{
     *     distance_meters: float,
     *     allowed_radius_meters: int,
     *     within_geofence: bool,
     *     expected_latitude: float,
     *     expected_longitude: float,
     * }  $geofence
     */
    private function createEntry(
        Employee $employee,
        string $eventType,
        array $device,
        WorkLocation $location,
        array $geofence,
        string $punchSource = TimeClockEntry::PUNCH_SOURCE_MANUAL,
        ?int $shiftIdOverride = null,
    ): TimeClockEntry {
        $attributes = [
            'employee_id' => $employee->id,
            'event_type' => $eventType,
            'clocked_at' => now('UTC'),
            'device_latitude' => $device['latitude'],
            'device_longitude' => $device['longitude'],
            'device_accuracy_meters' => $device['accuracy_meters'] ?? null,
            'work_location_id' => $location->id,
            'expected_latitude' => $geofence['expected_latitude'],
            'expected_longitude' => $geofence['expected_longitude'],
            'distance_from_site_meters' => $geofence['distance_meters'],
            'allowed_radius_meters' => $geofence['allowed_radius_meters'],
            'within_geofence' => $geofence['within_geofence'],
            'punch_source' => $punchSource,
            'department_id' => $employee->department_id,
            'shift_id' => $shiftIdOverride ?? $employee->shift_id,
        ];

        if ($eventType === TimeClockEntry::EVENT_CLOCK_OUT) {
            $comment = isset($device['comment']) ? trim((string) $device['comment']) : '';
            if ($comment !== '') {
                $attributes['comment'] = mb_substr($comment, 0, 2000);
            }
        }

        return TimeClockEntry::query()->create($attributes);
    }

    private function workLocationHasCoordinates(?WorkLocation $location): bool
    {
        if (! $location instanceof WorkLocation) {
            return false;
        }

        return $location->latitude !== null
            && $location->longitude !== null
            && is_finite((float) $location->latitude)
            && is_finite((float) $location->longitude);
    }

    private function assignmentIssue(Employee $employee, bool $hasCoordinates): ?string
    {
        if ($employee->work_location_id === null) {
            return 'no_work_location_assigned';
        }

        if (! $hasCoordinates) {
            return 'work_location_missing_coordinates';
        }

        return null;
    }
}
