<?php

namespace App\Services;

use App\Exceptions\TimeClockException;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use App\Models\TimeClockIdleAlert;
use App\Models\TimeClockLocationSample;
use App\Models\TimesheetApproval;
use App\Models\WorkLocation;
use App\Support\AutoClockOut;
use App\Support\BreakWindow;
use App\Support\ClockInGraceGate;
use App\Support\DisplayTimezone;
use App\Support\EarlyClockOutGate;
use App\Support\GeoDistance;
use App\Support\InductionEligibility;
use App\Support\TimeClockScheduledShift;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TimeClockService
{
    public function geofenceRadiusMeters(): int
    {
        $radius = (int) config('time_clock.geofence_radius_meters', WorkLocation::GEOFENCE_RADIUS_DEFAULT);

        return max(WorkLocation::GEOFENCE_RADIUS_MIN, min($radius, WorkLocation::GEOFENCE_RADIUS_MAX));
    }

    /**
     * Live radius for a site. Admin edits apply immediately, including open sessions.
     */
    public function radiusForWorkLocation(?WorkLocation $location): int
    {
        if ($location instanceof WorkLocation) {
            return $location->resolvedGeofenceRadiusMeters();
        }

        return $this->geofenceRadiusMeters();
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
        // Live site is today's allocated shift location, not the work assignment.
        // An open punch with no live site still uses the site they clocked into,
        // so a smaller geofence is not replaced by the 300 m fallback.
        $geofenceSite = $employee->effectiveWorkLocationForMobile();
        $hasCoordinates = $this->workLocationHasCoordinates($geofenceSite);
        if ($isClockedIn && $clockInEntry instanceof TimeClockEntry && ! $hasCoordinates) {
            $sessionSite = $this->resolveSessionWorkLocation($employee, $clockInEntry);
            if ($this->workLocationHasCoordinates($sessionSite)) {
                $geofenceSite = $sessionSite;
                $hasCoordinates = true;
            }
        }
        $liveRadius = $this->radiusForWorkLocation($geofenceSite instanceof WorkLocation ? $geofenceSite : null);
        $shiftIssue = $isClockedIn ? null : TimeClockScheduledShift::shiftIssue($employee);
        $grace = $isClockedIn
            ? ['blocks_clock_in' => false, 'issue' => null, 'window' => null]
            : ClockInGraceGate::assess($employee);
        $graceIssue = $grace['blocks_clock_in'] ? $grace['issue'] : null;
        $induction = InductionEligibility::mobileSummary($employee);
        $inductionIssue = ($induction['required'] ?? false) === true ? InductionEligibility::CLOCK_IN_CODE : null;
        $assignmentReady = $geofenceSite instanceof WorkLocation && $hasCoordinates;

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

        $breakWindow = BreakWindow::forEmployee(
            $employee,
            $isOnBreak,
            $session !== null && $session['breaks'] !== [],
        );

        $home = $inductionIssue !== null
            ? ['scheduled_shift' => null, 'scheduled_shifts' => []]
            : TimeClockScheduledShift::homePayload($employee);
        $hasSchedulableShift = ($home['scheduled_shift'] ?? null) !== null;

        return [
            'is_clocked_in' => $isClockedIn,
            'is_on_break' => $isOnBreak,
            'can_clock_in' => ! $isClockedIn
                && $assignmentReady
                && $shiftIssue === null
                && $graceIssue === null
                && $inductionIssue === null
                && $hasSchedulableShift,
            'can_clock_out' => $isClockedIn,
            'can_break_in' => $isClockedIn && ! $isOnBreak && BreakWindow::allowsBreakStart($breakWindow),
            'can_break_out' => $isOnBreak,
            'geofence_radius_meters' => $liveRadius,
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
                    // Live site radius (not the punch stamp) so an admin edit
                    // applies immediately to open sessions on the mobile client.
                    'allowed_radius_meters' => $liveRadius,
                    'break_started_at' => $isOnBreak
                        ? ($session['open_break_start']?->clocked_at?->toIso8601String())
                        : null,
                    'breaks' => $breaksPayload,
                    'total_break_seconds' => $totalBreakSeconds,
                ]
                : null,
            'last_event' => $lastEntry instanceof TimeClockEntry ? $lastEntry->toMobilePayload() : null,
            'assignment_ready' => $assignmentReady,
            'assignment_issue' => $this->assignmentIssue($geofenceSite, $hasCoordinates),
            'shift_issue' => $shiftIssue ?? $graceIssue,
            'induction_required' => $inductionIssue !== null,
            'induction_message' => $induction['message'] ?? null,
            'clock_in_window' => $grace['window'],
            'early_clock_out' => $isClockedIn
                ? EarlyClockOutGate::mobileStatus($employee)
                : [
                    'needs_approval' => false,
                    'approved' => false,
                    'shift_end_label' => null,
                ],
            'break_window' => $breakWindow,
            'scheduled_shift' => $home['scheduled_shift'],
            'scheduled_shifts' => $home['scheduled_shifts'],
            'work_location' => TimeClockScheduledShift::workLocationPayload(
                $geofenceSite instanceof WorkLocation ? $geofenceSite : null,
            ),
            'work_assignment' => $employee->workAssignmentForApi(),
        ];
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null}  $device
     * @return array{entry: TimeClockEntry, time_clock: array<string, mixed>}
     */
    public function clockIn(Employee $employee, array $device): array
    {
        try {
            return $this->clockInWithinTransaction($employee, $device);
        } catch (TimeClockException $e) {
            try {
                ClockInGraceGate::persistBlockedAttempt($e);
            } catch (\Throwable) {
                // The punch stays blocked even if the exception row cannot be saved.
            }

            throw $e;
        }
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null}  $device
     * @return array{entry: TimeClockEntry, time_clock: array<string, mixed>}
     */
    private function clockInWithinTransaction(Employee $employee, array $device): array
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

            $requestedShiftId = isset($device['schedule_shift_id']) ? (int) $device['schedule_shift_id'] : 0;
            $scheduledShift = $this->assertScheduledShiftForClockIn(
                $employee,
                $requestedShiftId > 0 ? $requestedShiftId : null,
            );

            $location = $this->resolveClockInWorkLocation($scheduledShift);
            if (! $location instanceof WorkLocation || ! $this->workLocationHasCoordinates($location)) {
                throw new TimeClockException(
                    'work_location_not_found',
                    'Today\'s shift does not have a work location with map coordinates. Contact your administrator.',
                );
            }

            $geofence = $this->evaluateGeofence(
                $location,
                $device['latitude'],
                $device['longitude'],
                $device['accuracy_meters'] ?? null,
            );
            $this->assertWithinGeofence($geofence);

            ClockInGraceGate::assertAllowsClockIn($employee, $scheduledShift);

            $entry = $this->createEntry(
                $employee,
                TimeClockEntry::EVENT_CLOCK_IN,
                $device,
                $location,
                $geofence,
                TimeClockEntry::PUNCH_SOURCE_MANUAL,
                $scheduledShift->shift_id,
                $scheduledShift->id ? (int) $scheduledShift->id : null,
            );

            ClockInGraceGate::noteSuccessfulClockIn($employee, $scheduledShift);

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
        try {
            return $this->clockOutWithinTransaction($employee, $device);
        } catch (TimeClockException $e) {
            try {
                EarlyClockOutGate::persistBlockedAttempt($e);
            } catch (\Throwable) {
                // The punch stays blocked even if the request row cannot be saved.
            }

            throw $e;
        }
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null, comment?: string|null}  $device
     * @return array{entry: TimeClockEntry, time_clock: array<string, mixed>}
     */
    private function clockOutWithinTransaction(Employee $employee, array $device): array
    {
        return DB::transaction(function () use ($employee, $device) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $employee->loadMissing(['workLocation', 'assignedDepartment', 'assignedShift']);

            if ($this->resolveOpenSession($employee) === null && InductionEligibility::blocksWork($employee)) {
                throw new TimeClockException(
                    InductionEligibility::CLOCK_IN_CODE,
                    InductionEligibility::BLOCK_MESSAGE,
                );
            }

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

            EarlyClockOutGate::assertAllowsClockOut(
                $employee,
                isset($device['comment']) ? (string) $device['comment'] : null,
            );

            if ($session['is_on_break']) {
                $this->createEntry(
                    $employee,
                    TimeClockEntry::EVENT_BREAK_END,
                    $device,
                    $location,
                    $geofence,
                    TimeClockEntry::PUNCH_SOURCE_MANUAL,
                    $session['clock_in']->shift_id,
                    $this->scheduleShiftIdFromClockIn($session['clock_in']),
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
                $this->scheduleShiftIdFromClockIn($session['clock_in']),
            );

            $this->clearIdleAlertsForSession($employee, $session['clock_in']);
            EarlyClockOutGate::noteSuccessfulClockOut($employee);

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
                $this->scheduleShiftIdFromClockIn($session['clock_in']),
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
                $this->scheduleShiftIdFromClockIn($session['clock_in']),
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
            $match = $this->matchingAutoClockOutTarget($employee, $session, $device);
            if ($match === null) {
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

                throw new TimeClockException(
                    'still_within_geofence',
                    'You are still within the work site geofence.',
                );
            }

            $location = $match['location'];
            $geofence = $match['geofence'];

            if ($session['is_on_break']) {
                $this->createEntry(
                    $employee,
                    TimeClockEntry::EVENT_BREAK_END,
                    $device,
                    $location,
                    $geofence,
                    TimeClockEntry::PUNCH_SOURCE_AUTO_GEOFENCE_EXIT,
                    $session['clock_in']->shift_id,
                    $this->scheduleShiftIdFromClockIn($session['clock_in']),
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
                $this->scheduleShiftIdFromClockIn($session['clock_in']),
            );

            $this->clearIdleAlertsForSession($employee, $session['clock_in']);

            return [
                'entry' => $entry,
                'time_clock' => $this->statusFor($employee),
            ];
        });
    }

    /**
     * Close an open session when the latest stored GPS sample is outside the
     * site. Used by the scheduler so a missed phone request still clocks them out.
     *
     * @return array{at: CarbonInterface, device: array<string, mixed>}|null
     */
    public function openSessionLeftSite(Employee $employee): ?array
    {
        $session = $this->resolveOpenSession($employee);
        if ($session === null) {
            return null;
        }

        /** @var TimeClockEntry $clockIn */
        $clockIn = $session['clock_in'];
        $sample = TimeClockLocationSample::query()
            ->where('employee_id', $employee->id)
            ->where('clock_in_entry_id', $clockIn->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        if (! $sample instanceof TimeClockLocationSample || $sample->latitude === null || $sample->longitude === null) {
            return null;
        }

        $device = [
            'latitude' => (float) $sample->latitude,
            'longitude' => (float) $sample->longitude,
            'accuracy_meters' => $sample->accuracy_meters !== null ? (float) $sample->accuracy_meters : null,
        ];
        $match = $this->matchingAutoClockOutTarget($employee, $session, $device);
        if ($match === null || $sample->recorded_at === null) {
            return null;
        }

        return [
            'at' => $sample->recorded_at,
            'device' => [
                'latitude' => $device['latitude'],
                'longitude' => $device['longitude'],
                'accuracy_meters' => $device['accuracy_meters'],
                'distance_meters' => $match['geofence']['distance_meters'],
                'allowed_radius_meters' => $match['geofence']['allowed_radius_meters'],
                'within_geofence' => false,
                'expected_latitude' => $match['geofence']['expected_latitude'],
                'expected_longitude' => $match['geofence']['expected_longitude'],
                'work_location_id' => $match['location']->id,
            ],
        ];
    }

    /**
     * Admin closes an in-progress shift at a chosen time. The employee is clocked out.
     */
    public function adminCloseOpenSession(Employee $employee, int $clockInEntryId, CarbonInterface $clockOutAt): TimeClockEntry
    {
        return DB::transaction(function () use ($employee, $clockInEntryId, $clockOutAt) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $session = $this->resolveOpenSession($employee);
            if ($session === null || (int) $session['clock_in']->id !== $clockInEntryId) {
                throw new TimeClockException(
                    'not_in_progress',
                    'That shift is not in progress, so it cannot be clocked out.',
                );
            }

            $at = $clockOutAt->copy()->utc();
            $clockInAt = $session['clock_in']->clocked_at;
            if ($clockInAt !== null && $at->lessThanOrEqualTo($clockInAt)) {
                throw new TimeClockException(
                    'clock_out_before_clock_in',
                    'Clock out must be after clock in.',
                );
            }

            $lastAt = $session['last']->clocked_at;
            if ($lastAt !== null && $at->lessThan($lastAt)) {
                throw new TimeClockException(
                    'clock_out_before_last_punch',
                    'Clock out must be at or after the latest punch on this shift.',
                );
            }

            if ($at->greaterThan(now('UTC')->addMinute())) {
                throw new TimeClockException(
                    'clock_out_in_future',
                    'Clock out time cannot be in the future.',
                );
            }

            $entry = $this->insertSystemClockOut(
                $employee,
                $session,
                $at,
                TimeClockEntry::PUNCH_SOURCE_ADMIN,
                'Clocked out by an administrator.',
                null,
            );

            if (! $entry instanceof TimeClockEntry) {
                throw new TimeClockException(
                    'not_in_progress',
                    'That shift is not in progress, so it cannot be clocked out.',
                );
            }

            return $entry;
        });
    }

    /**
     * Remove an accidental clock-out so the shift is in progress again.
     * The employee can then clock out from the mobile app.
     */
    public function reopenClosedSession(Employee $employee, int $clockInEntryId, int $clockOutEntryId): void
    {
        DB::transaction(function () use ($employee, $clockInEntryId, $clockOutEntryId): void {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);

            $clockIn = TimeClockEntry::query()
                ->where('employee_id', $employee->id)
                ->whereKey($clockInEntryId)
                ->first();

            if (! $clockIn instanceof TimeClockEntry || $clockIn->event_type !== TimeClockEntry::EVENT_CLOCK_IN) {
                throw new TimeClockException(
                    'invalid_clock_in',
                    'The selected clock-in record is invalid.',
                );
            }

            $clockOut = $this->closingClockOutFor($employee, $clockIn);
            if (! $clockOut instanceof TimeClockEntry) {
                throw new TimeClockException(
                    'already_in_progress',
                    'This shift is already in progress.',
                );
            }

            if ((int) $clockOut->id !== $clockOutEntryId) {
                throw new TimeClockException(
                    'clock_out_mismatch',
                    'That clock-out no longer matches this shift. Refresh the page and try again.',
                );
            }

            $latest = $this->latestEntryFor($employee);
            if (! $latest instanceof TimeClockEntry || (int) $latest->id !== (int) $clockOut->id) {
                throw new TimeClockException(
                    'later_clock_activity',
                    'This shift cannot be reopened because the employee has clocked in or out again after it. Only the latest clock-out can be put back in progress.',
                );
            }

            $clockOut->delete();

            if ($this->entriesHaveReopenedAt($employee)) {
                $clockIn->reopened_at = now('UTC');
                $clockIn->save();
            }

            $this->resetTimesheetApprovalForReopen($employee, $clockIn);
        });
    }

    public function systemClockOut(
        Employee $employee,
        CarbonInterface $clockOutAt,
        string $punchSource,
        ?string $comment = null,
        ?array $device = null,
    ): ?TimeClockEntry {
        return DB::transaction(function () use ($employee, $clockOutAt, $punchSource, $comment, $device) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $session = $this->resolveOpenSession($employee);
            if ($session === null) {
                return null;
            }

            $at = $clockOutAt->copy()->utc();
            $clockInAt = $session['clock_in']->clocked_at;
            if ($clockInAt !== null && $at->lessThan($clockInAt)) {
                $at = $clockInAt->copy();
            }
            $nowUtc = now('UTC');
            if ($at->greaterThan($nowUtc)) {
                $at = $nowUtc;
            }

            return $this->insertSystemClockOut($employee, $session, $at, $punchSource, $comment, $device);
        });
    }

    /**
     * @param  array{
     *     clock_in: TimeClockEntry,
     *     last: TimeClockEntry,
     *     is_on_break: bool,
     *     open_break_start: TimeClockEntry|null,
     *     breaks: list<array{start: TimeClockEntry, end: TimeClockEntry|null}>,
     * }  $session
     * @param  array<string, mixed>|null  $device
     */
    private function insertSystemClockOut(
        Employee $employee,
        array $session,
        CarbonInterface $at,
        string $punchSource,
        ?string $comment,
        ?array $device,
    ): TimeClockEntry {
        $clockIn = $session['clock_in'];
        $location = $clockIn->work_location_id !== null
            ? WorkLocation::query()->find($clockIn->work_location_id)
            : null;
        if (! $location instanceof WorkLocation) {
            $location = $employee->effectiveWorkLocationForMobile();
        }
        if (is_array($device) && isset($device['work_location_id'])) {
            $fromDevice = WorkLocation::query()->find((int) $device['work_location_id']);
            if ($fromDevice instanceof WorkLocation) {
                $location = $fromDevice;
            }
        }
        $expectedLat = $this->workLocationHasCoordinates($location) ? (float) $location->latitude : null;
        $expectedLng = $this->workLocationHasCoordinates($location) ? (float) $location->longitude : null;

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
            'allowed_radius_meters' => $this->radiusForWorkLocation(
                $location instanceof WorkLocation ? $location : null,
            ),
            'within_geofence' => true,
            'punch_source' => $punchSource,
            'department_id' => $employee->department_id,
            'shift_id' => $clockIn->shift_id ?? $employee->shift_id,
            'schedule_shift_id' => $this->scheduleShiftIdFromClockIn($clockIn),
        ];
        if (! $this->timeClockEntriesHaveScheduleShiftId($employee)) {
            unset($baseAttributes['schedule_shift_id']);
        }

        if (is_array($device) && isset($device['latitude'], $device['longitude'])) {
            $baseAttributes['device_latitude'] = (float) $device['latitude'];
            $baseAttributes['device_longitude'] = (float) $device['longitude'];
            $baseAttributes['device_accuracy_meters'] = $device['accuracy_meters'] ?? null;
            if (isset($device['distance_meters'])) {
                $baseAttributes['distance_from_site_meters'] = $device['distance_meters'];
            }
            if (isset($device['allowed_radius_meters'])) {
                $baseAttributes['allowed_radius_meters'] = $device['allowed_radius_meters'];
            }
            if (array_key_exists('within_geofence', $device)) {
                $baseAttributes['within_geofence'] = (bool) $device['within_geofence'];
            }
            if (isset($device['expected_latitude'], $device['expected_longitude'])) {
                $baseAttributes['expected_latitude'] = $device['expected_latitude'];
                $baseAttributes['expected_longitude'] = $device['expected_longitude'];
            }
        }

        if ($session['is_on_break']) {
            TimeClockEntry::query()->create([
                ...$baseAttributes,
                'event_type' => TimeClockEntry::EVENT_BREAK_END,
                'comment' => null,
            ]);
        }

        $entry = TimeClockEntry::query()->create([
            ...$baseAttributes,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'comment' => $comment !== null ? mb_substr($comment, 0, 2000) : null,
        ]);

        $this->clearIdleAlertsForSession($employee, $clockIn);

        return $entry;
    }

    private function clearIdleAlertsForSession(Employee $employee, TimeClockEntry $clockIn): void
    {
        TimeClockIdleAlert::query()
            ->where('employee_id', $employee->id)
            ->where('clock_in_entry_id', $clockIn->id)
            ->whereIn('status', [
                TimeClockIdleAlert::STATUS_OPEN,
                TimeClockIdleAlert::STATUS_ACKNOWLEDGED,
            ])
            ->update([
                'status' => TimeClockIdleAlert::STATUS_CLEARED,
                'cleared_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
    }

    /**
     * The clock-out that closed this clock-in, when nothing else has started.
     */
    private function closingClockOutFor(Employee $employee, TimeClockEntry $clockIn): ?TimeClockEntry
    {
        if ($clockIn->clocked_at === null) {
            return null;
        }

        $following = TimeClockEntry::query()
            ->where('employee_id', $employee->id)
            ->where(function ($query) use ($clockIn): void {
                $query->where('clocked_at', '>', $clockIn->clocked_at)
                    ->orWhere(function ($query) use ($clockIn): void {
                        $query->where('clocked_at', $clockIn->clocked_at)
                            ->where('id', '>', $clockIn->id);
                    });
            })
            ->orderBy('clocked_at')
            ->orderBy('id')
            ->get();

        foreach ($following as $entry) {
            if ($entry->event_type === TimeClockEntry::EVENT_CLOCK_IN) {
                return null;
            }

            if ($entry->event_type === TimeClockEntry::EVENT_CLOCK_OUT) {
                return $entry;
            }
        }

        return null;
    }

    private function resetTimesheetApprovalForReopen(Employee $employee, TimeClockEntry $clockIn): void
    {
        $connection = $employee->getConnectionName();
        if (! Schema::connection($connection)->hasTable('timesheet_approvals')) {
            return;
        }

        TimesheetApproval::on($connection)
            ->where('employee_id', $employee->id)
            ->where('clock_in_entry_id', $clockIn->id)
            ->update([
                'status' => TimesheetApproval::STATUS_PENDING,
                'completed_sessions' => 0,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
                'updated_at' => now('UTC'),
            ]);
    }

    private function entriesHaveReopenedAt(Employee $employee): bool
    {
        $connection = $employee->getConnectionName();
        static $cache = [];
        if (array_key_exists($connection, $cache)) {
            return $cache[$connection];
        }

        return $cache[$connection] = Schema::connection($connection)->hasTable('time_clock_entries')
            && Schema::connection($connection)->hasColumn('time_clock_entries', 'reopened_at');
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
     * Public open-session resolver for location tracking (does not change punch rules).
     *
     * @return array{
     *     clock_in: TimeClockEntry,
     *     last: TimeClockEntry,
     *     is_on_break: bool,
     *     open_break_start: TimeClockEntry|null,
     *     breaks: list<array{start: TimeClockEntry, end: TimeClockEntry|null}>,
     * }|null
     */
    public function openSessionFor(Employee $employee): ?array
    {
        return $this->resolveOpenSession($employee);
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
        if ($this->resolveOpenSession($employee) !== null) {
            throw new TimeClockException(
                'already_clocked_in',
                'You are already clocked in. Clock out before starting another shift.',
            );
        }

        if (InductionEligibility::blocksWork($employee)) {
            throw new TimeClockException(
                InductionEligibility::CLOCK_IN_CODE,
                InductionEligibility::BLOCK_MESSAGE,
            );
        }
    }

    private function assertScheduledShiftForClockIn(Employee $employee, ?int $scheduleShiftId = null): EmployeeScheduleShift
    {
        $issue = TimeClockScheduledShift::shiftIssue($employee);
        if ($issue === TimeClockScheduledShift::ISSUE_NO_SHIFT_TODAY) {
            throw new TimeClockException(
                TimeClockScheduledShift::ISSUE_NO_SHIFT_TODAY,
                "You don't have any shifts today.",
            );
        }

        if ($scheduleShiftId !== null && $scheduleShiftId > 0) {
            $chosen = TimeClockScheduledShift::unfinishedShiftById($employee, $scheduleShiftId);
            if ($chosen instanceof EmployeeScheduleShift) {
                return $chosen;
            }

            throw new TimeClockException(
                'shift_not_available',
                'That shift is not available to clock into.',
            );
        }

        $shift = TimeClockScheduledShift::findShiftForClockIn($employee);
        if (! $shift instanceof EmployeeScheduleShift) {
            $today = DisplayTimezone::now()->toDateString();
            if (TimeClockScheduledShift::shiftsForDate($employee, $today)->isNotEmpty()) {
                throw new TimeClockException(
                    'shifts_finished_today',
                    "You've finished all of today's shifts.",
                );
            }

            throw new TimeClockException(
                TimeClockScheduledShift::ISSUE_NO_SHIFT_TODAY,
                "You don't have any shifts today.",
            );
        }

        return $shift;
    }

    private function resolveClockInWorkLocation(EmployeeScheduleShift $scheduledShift): ?WorkLocation
    {
        if ($scheduledShift->work_location_id === null) {
            return null;
        }

        $fromShift = WorkLocation::query()->find($scheduledShift->work_location_id);

        return $fromShift instanceof WorkLocation ? $fromShift : null;
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

        $breakWindow = BreakWindow::forEmployee($employee, false, $session['breaks'] !== []);
        if (! BreakWindow::allowsBreakStart($breakWindow)) {
            throw new TimeClockException(
                BreakWindow::ISSUE_OUTSIDE,
                (string) ($breakWindow['block_message'] ?? $breakWindow['message'] ?? 'You can only take your break between the set hours.'),
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
    /**
     * @param  array{
     *     clock_in: TimeClockEntry,
     *     last: TimeClockEntry,
     *     is_on_break: bool,
     *     open_break_start: TimeClockEntry|null,
     *     breaks: list<array{start: TimeClockEntry, end: TimeClockEntry|null}>,
     * }  $session
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null}  $device
     * @return array{location: WorkLocation, geofence: array<string, mixed>}|null
     */
    private function matchingAutoClockOutTarget(Employee $employee, array $session, array $device): ?array
    {
        $clockIn = $session['clock_in'];
        $liveLocation = $employee->effectiveWorkLocationForMobile();
        $sessionLocation = $this->resolveSessionWorkLocation($employee, $clockIn);
        $accuracy = $device['accuracy_meters'] ?? null;

        if ($liveLocation instanceof WorkLocation && $this->workLocationHasCoordinates($liveLocation)) {
            $location = $liveLocation;
            $geofence = $this->evaluateGeofence(
                $location,
                $device['latitude'],
                $device['longitude'],
                $accuracy,
            );
        } else {
            $location = $sessionLocation;
            if (! $location instanceof WorkLocation) {
                return null;
            }
            $geofence = $this->evaluateSessionGeofence(
                $clockIn,
                $location,
                $device['latitude'],
                $device['longitude'],
                $accuracy,
            );
        }

        if (! $this->readingIsOutsideGeofence($geofence, $accuracy)) {
            return null;
        }

        return [
            'location' => $location,
            'geofence' => $geofence,
        ];
    }

    /**
     * @param  array{distance_meters: float, allowed_radius_meters: int}  $geofence
     */
    private function readingIsOutsideGeofence(array $geofence, ?float $accuracyMeters = null): bool
    {
        return AutoClockOut::isOutside(
            (float) $geofence['distance_meters'],
            (int) $geofence['allowed_radius_meters'],
            $accuracyMeters,
            $this->geofenceExitExtraMeters(),
        );
    }

    private function assertOutsideGeofenceForAutoClockOut(array $geofence, ?float $accuracyMeters = null): void
    {
        if ($this->readingIsOutsideGeofence($geofence, $accuracyMeters)) {
            return;
        }

        $exitAt = $geofence['allowed_radius_meters'] + $this->geofenceExitExtraMeters();

        throw new TimeClockException(
            'still_within_geofence',
            sprintf(
                'You are still within the work site geofence (about %.0f m away; auto clock-out requires leaving beyond %d m).',
                $geofence['distance_meters'],
                $exitAt,
            ),
            422,
            [
                'distance_from_site_meters' => $geofence['distance_meters'],
                'allowed_radius_meters' => $geofence['allowed_radius_meters'],
                'exit_radius_meters' => $exitAt,
                'expected_latitude' => $geofence['expected_latitude'],
                'expected_longitude' => $geofence['expected_longitude'],
            ],
        );
    }

    private function geofenceExitExtraMeters(): int
    {
        return max(0, (int) config('time_clock.geofence_exit_extra_meters', 0));
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

        return $employee->effectiveWorkLocationForMobile();
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
        // Use the site's current radius for live enter/exit checks. The radius
        // stamped on the clock-in row remains historical audit data only.
        $radius = $this->radiusForWorkLocation($location);
        $distance = GeoDistance::metersBetween(
            $deviceLatitude,
            $deviceLongitude,
            $expectedLat,
            $expectedLng,
        );

        return [
            'distance_meters' => round($distance, 2),
            'allowed_radius_meters' => $radius,
            'within_geofence' => $distance <= $radius,
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
        $radius = $this->radiusForWorkLocation($location);
        $distance = GeoDistance::metersBetween(
            $deviceLatitude,
            $deviceLongitude,
            $expectedLat,
            $expectedLng,
        );

        return [
            'distance_meters' => round($distance, 2),
            'allowed_radius_meters' => $radius,
            'within_geofence' => $distance <= $radius,
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
        ?int $scheduleShiftId = null,
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
            'schedule_shift_id' => $scheduleShiftId !== null && $scheduleShiftId > 0 ? $scheduleShiftId : null,
        ];

        if ($eventType === TimeClockEntry::EVENT_CLOCK_OUT) {
            $comment = isset($device['comment']) ? trim((string) $device['comment']) : '';
            if ($comment !== '') {
                $attributes['comment'] = mb_substr($comment, 0, 2000);
            }
        }

        if (! $this->timeClockEntriesHaveScheduleShiftId($employee)) {
            unset($attributes['schedule_shift_id']);
        }

        return TimeClockEntry::query()->create($attributes);
    }

    private function scheduleShiftIdFromClockIn(TimeClockEntry $clockIn): ?int
    {
        $id = $clockIn->schedule_shift_id;

        return $id !== null && (int) $id > 0 ? (int) $id : null;
    }

    private function timeClockEntriesHaveScheduleShiftId(Employee $employee): bool
    {
        $connection = $employee->getConnectionName();
        static $cache = [];
        if (array_key_exists($connection, $cache)) {
            return $cache[$connection];
        }

        return $cache[$connection] = Schema::connection($connection)->hasTable('time_clock_entries')
            && Schema::connection($connection)->hasColumn('time_clock_entries', 'schedule_shift_id');
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

    private function assignmentIssue(?WorkLocation $location, bool $hasCoordinates): ?string
    {
        if (! $location instanceof WorkLocation) {
            return 'no_work_location_assigned';
        }

        if (! $hasCoordinates) {
            return 'work_location_missing_coordinates';
        }

        return null;
    }
}
