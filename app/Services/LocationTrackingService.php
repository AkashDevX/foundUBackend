<?php

namespace App\Services;

use App\Exceptions\TimeClockException;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use App\Models\TimeClockIdleAlert;
use App\Models\TimeClockLocationSample;
use App\Models\WorkLocation;
use App\Support\DisplayTimezone;
use App\Support\GeoDistance;
use App\Support\LocationIdleDetector;
use App\Support\MovementTrailAnalyzer;
use App\Support\TimeClockScheduledShift;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LocationTrackingService
{
    public function __construct(
        private readonly TimeClockService $timeClock,
    ) {}

    public function pingMinIntervalSeconds(): int
    {
        return max(30, (int) config('time_clock.location_ping_min_interval_seconds', 120));
    }

    public function idleWindowMinutes(): int
    {
        return max(5, (int) config('time_clock.idle_window_minutes', 30));
    }

    public function idleMaxDisplacementMeters(): float
    {
        return max(5.0, (float) config('time_clock.idle_max_displacement_meters', 40));
    }

    public function idleMinUsableAccuracyMeters(): float
    {
        return max(10.0, (float) config('time_clock.idle_min_usable_accuracy_meters', 80));
    }

    public function idleAlertCooldownMinutes(): int
    {
        return max(5, (int) config('time_clock.idle_alert_cooldown_minutes', 45));
    }

    public function idleMinSamples(): int
    {
        return max(2, (int) config('time_clock.idle_min_samples', 3));
    }

    /**
     * Record a mid-shift GPS sample. Does not clock the employee out.
     *
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null}  $device
     * @return array{
     *     sample: TimeClockLocationSample|null,
     *     throttled: bool,
     *     idle_alert: TimeClockIdleAlert|null,
     * }
     */
    public function recordPing(Employee $employee, array $device): array
    {
        return DB::transaction(function () use ($employee, $device) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $employee->loadMissing(['workLocation']);

            $session = $this->timeClock->openSessionFor($employee);
            if ($session === null) {
                throw new TimeClockException(
                    'not_clocked_in',
                    'You are not clocked in. Location tracking only runs during an open shift.',
                );
            }

            /** @var TimeClockEntry $clockIn */
            $clockIn = $session['clock_in'];
            $now = now('UTC');

            $lastSample = TimeClockLocationSample::query()
                ->where('employee_id', $employee->id)
                ->where('clock_in_entry_id', $clockIn->id)
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->first();

            if ($lastSample?->recorded_at !== null) {
                $elapsed = $lastSample->recorded_at->diffInSeconds($now);
                if ($elapsed < $this->pingMinIntervalSeconds()) {
                    $openAlert = $this->openIdleAlertForSession($employee->id, (int) $clockIn->id);

                    return [
                        'sample' => null,
                        'throttled' => true,
                        'idle_alert' => $openAlert,
                    ];
                }
            }

            $location = $this->resolveWorkLocation($employee, $clockIn);
            $geofence = $this->evaluateAgainstSite(
                $location,
                $clockIn,
                (float) $device['latitude'],
                (float) $device['longitude'],
                isset($device['accuracy_meters']) ? (float) $device['accuracy_meters'] : null,
            );

            $sample = TimeClockLocationSample::query()->create([
                'employee_id' => $employee->id,
                'clock_in_entry_id' => $clockIn->id,
                'recorded_at' => $now,
                'latitude' => $device['latitude'],
                'longitude' => $device['longitude'],
                'accuracy_meters' => $device['accuracy_meters'] ?? null,
                'work_location_id' => $location?->id ?? $clockIn->work_location_id,
                'distance_from_site_meters' => $geofence['distance_meters'],
                'allowed_radius_meters' => $geofence['allowed_radius_meters'],
                'within_geofence' => $geofence['within_geofence'],
            ]);

            $idleAlert = $this->evaluateIdleAfterSample($employee, $clockIn);

            return [
                'sample' => $sample,
                'throttled' => false,
                'idle_alert' => $idleAlert,
            ];
        });
    }

    public function acknowledgeIdleAlert(Employee $employee, int $alertId): TimeClockIdleAlert
    {
        $alert = TimeClockIdleAlert::query()
            ->where('employee_id', $employee->id)
            ->whereKey($alertId)
            ->first();

        if ($alert === null) {
            throw new TimeClockException(
                'idle_alert_not_found',
                'That idle alert was not found.',
                404,
            );
        }

        if ($alert->status === TimeClockIdleAlert::STATUS_CLEARED) {
            return $alert;
        }

        $alert->status = TimeClockIdleAlert::STATUS_ACKNOWLEDGED;
        $alert->employee_acknowledged_at = now('UTC');
        $alert->save();

        return $alert->fresh();
    }

    public function clearOpenAlertsForEmployee(Employee $employee, ?int $clockInEntryId = null): void
    {
        $query = TimeClockIdleAlert::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [
                TimeClockIdleAlert::STATUS_OPEN,
                TimeClockIdleAlert::STATUS_ACKNOWLEDGED,
            ]);

        if ($clockInEntryId !== null) {
            $query->where('clock_in_entry_id', $clockInEntryId);
        }

        $query->update([
            'status' => TimeClockIdleAlert::STATUS_CLEARED,
            'cleared_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    /**
     * Latest sample per currently clocked-in employee (for admin live map).
     *
     * @param  Collection<int, Employee>  $employees
     * @return list<array<string, mixed>>
     */
    public function livePositionsForEmployees(Collection $employees): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $positions = [];
        foreach ($employees as $employee) {
            $session = $this->timeClock->openSessionFor($employee);
            if ($session === null) {
                continue;
            }

            /** @var TimeClockEntry $clockIn */
            $clockIn = $session['clock_in'];
            $sample = TimeClockLocationSample::query()
                ->where('employee_id', $employee->id)
                ->where('clock_in_entry_id', $clockIn->id)
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->first();

            $idleAlert = $this->openIdleAlertForSession($employee->id, (int) $clockIn->id);

            $employee->loadMissing(['workLocation', 'assignedShift', 'assignmentShifts.shiftTemplate']);
            $clockIn->loadMissing(['workLocation', 'shift']);

            // Prefer weekly-schedule site (may differ from work assignment), then punch session, then assignment.
            $scheduleRow = $this->todaysScheduledShiftRow($employee);
            $workLocation = $this->resolveDisplayWorkLocation($employee, $clockIn, $scheduleRow);

            $scheduledTimes = TimeClockScheduledShift::todayShiftForDisplay($employee);
            $assignment = $employee->workAssignmentForApi();
            $assignedShift = is_array($assignment) && is_array($assignment['shift'] ?? null)
                ? $assignment['shift']
                : null;
            $assignmentLocation = is_array($assignment) && is_array($assignment['work_location'] ?? null)
                ? $assignment['work_location']
                : null;

            $shiftStart = is_array($scheduledTimes)
                ? ($scheduledTimes['start_label'] ?? $scheduledTimes['start_time'] ?? null)
                : null;
            $shiftStart = $shiftStart ?? ($assignedShift['start_time'] ?? null);

            $shiftEnd = is_array($scheduledTimes)
                ? ($scheduledTimes['end_label'] ?? $scheduledTimes['end_time'] ?? null)
                : null;
            $shiftEnd = $shiftEnd ?? ($assignedShift['end_time'] ?? null);
            $shiftName = $scheduleRow?->shiftTemplate?->name
                ?? $clockIn->shift?->name
                ?? ($assignedShift['name'] ?? null);

            $workLocationName = $workLocation?->name
                ?? (is_array($assignmentLocation) ? ($assignmentLocation['name'] ?? null) : null);
            $workLocationAddress = $workLocation?->address
                ?? (is_array($assignmentLocation) ? ($assignmentLocation['address'] ?? null) : null);

            $siteLat = $clockIn->expected_latitude !== null
                ? (float) $clockIn->expected_latitude
                : ($workLocation?->latitude !== null ? (float) $workLocation->latitude : null);
            $siteLng = $clockIn->expected_longitude !== null
                ? (float) $clockIn->expected_longitude
                : ($workLocation?->longitude !== null ? (float) $workLocation->longitude : null);

            // Prefer mid-shift ping; fall back to clock-in punch coords so the map is never blank.
            $lat = $sample?->latitude !== null ? (float) $sample->latitude : null;
            $lng = $sample?->longitude !== null ? (float) $sample->longitude : null;
            $recordedAt = $sample?->recorded_at?->toIso8601String();
            $fromSample = $sample !== null;
            if ($lat === null && $clockIn->device_latitude !== null) {
                $lat = (float) $clockIn->device_latitude;
                $lng = $clockIn->device_longitude !== null ? (float) $clockIn->device_longitude : null;
                $recordedAt = $clockIn->clocked_at?->toIso8601String();
            }

            $positions[] = [
                'employee_id' => $employee->id,
                'employee_public_id' => $employee->public_id,
                'employee_name' => $employee->full_legal_name ?: ($employee->email ?? 'Employee'),
                'clock_in_entry_id' => $clockIn->id,
                'clocked_in_at' => $clockIn->clocked_at?->toIso8601String(),
                'status' => 'in_progress',
                'status_label' => 'In progress',
                'work_location_id' => $workLocation?->id ?? $clockIn->work_location_id,
                'work_location_name' => $workLocationName,
                'work_location_address' => $workLocationAddress,
                'shift_name' => $shiftName,
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'shift_label' => ($shiftStart && $shiftEnd)
                    ? trim((string) $shiftStart).' – '.trim((string) $shiftEnd)
                    : ($shiftName ?: null),
                'is_on_break' => (bool) ($session['is_on_break'] ?? false),
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy_meters' => $sample?->accuracy_meters !== null ? (float) $sample->accuracy_meters : null,
                'recorded_at' => $recordedAt,
                'position_source' => $fromSample ? 'ping' : ($lat !== null ? 'clock_in' : null),
                'within_geofence' => $sample !== null
                    ? (bool) $sample->within_geofence
                    : ($clockIn->within_geofence !== null ? (bool) $clockIn->within_geofence : null),
                'distance_from_site_meters' => $sample?->distance_from_site_meters !== null
                    ? (float) $sample->distance_from_site_meters
                    : ($clockIn->distance_from_site_meters !== null ? (float) $clockIn->distance_from_site_meters : null),
                'site_latitude' => $siteLat,
                'site_longitude' => $siteLng,
                'allowed_radius_meters' => $clockIn->allowed_radius_meters !== null
                    ? (int) $clockIn->allowed_radius_meters
                    : $this->timeClock->geofenceRadiusMeters(),
                'has_idle_alert' => $idleAlert !== null,
                'idle_alert' => $idleAlert?->toMobilePayload(),
                'detail_url' => route('admin.employees.location-tracking', [
                    'employee' => $employee->public_id,
                    'clock_in_entry_id' => $clockIn->id,
                ]),
                'site_url' => ($workLocation?->id ?? $clockIn->work_location_id)
                    ? route('admin.employees.location-tracking', [
                        'work_location_id' => $workLocation?->id ?? $clockIn->work_location_id,
                    ])
                    : null,
            ];
        }

        return $positions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function trailForSession(int $employeeId, int $clockInEntryId): array
    {
        return TimeClockLocationSample::query()
            ->where('employee_id', $employeeId)
            ->where('clock_in_entry_id', $clockInEntryId)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (TimeClockLocationSample $sample): array => [
                'id' => $sample->id,
                'recorded_at' => $sample->recorded_at?->toIso8601String(),
                'latitude' => (float) $sample->latitude,
                'longitude' => (float) $sample->longitude,
                'accuracy_meters' => $sample->accuracy_meters !== null ? (float) $sample->accuracy_meters : null,
                'within_geofence' => (bool) $sample->within_geofence,
                'distance_from_site_meters' => $sample->distance_from_site_meters !== null
                    ? (float) $sample->distance_from_site_meters
                    : null,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $trail
     * @param  list<array<string, mixed>>  $idleAlerts
     * @return array<string, mixed>
     */
    public function movementStatsForTrail(array $trail, array $idleAlerts = []): array
    {
        return MovementTrailAnalyzer::analyze(
            $trail,
            $idleAlerts,
            max(10.0, (float) config('time_clock.idle_max_displacement_meters', 40) * 0.6),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function idleAlertsForSession(int $employeeId, int $clockInEntryId): array
    {
        return TimeClockIdleAlert::query()
            ->where('employee_id', $employeeId)
            ->where('clock_in_entry_id', $clockInEntryId)
            ->orderBy('detected_at')
            ->get()
            ->map(static fn (TimeClockIdleAlert $alert): array => $alert->toMobilePayload())
            ->all();
    }

    private function evaluateIdleAfterSample(Employee $employee, TimeClockEntry $clockIn): ?TimeClockIdleAlert
    {
        $windowMinutes = $this->idleWindowMinutes();
        $windowStart = now('UTC')->subMinutes($windowMinutes);
        $maxAccuracy = $this->idleMinUsableAccuracyMeters();

        $samples = TimeClockLocationSample::query()
            ->where('employee_id', $employee->id)
            ->where('clock_in_entry_id', $clockIn->id)
            ->where('recorded_at', '>=', $windowStart)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->filter(function (TimeClockLocationSample $sample) use ($maxAccuracy): bool {
                if ($sample->accuracy_meters === null) {
                    return true;
                }

                return (float) $sample->accuracy_meters <= $maxAccuracy;
            })
            ->values();

        if ($samples->count() < $this->idleMinSamples()) {
            return $this->openIdleAlertForSession($employee->id, (int) $clockIn->id);
        }

        $payload = $samples->map(static fn (TimeClockLocationSample $sample): array => [
            'latitude' => (float) $sample->latitude,
            'longitude' => (float) $sample->longitude,
        ])->all();

        $analysis = LocationIdleDetector::analyze($payload, $this->idleMaxDisplacementMeters());

        if (!$analysis['is_idle']) {
            $this->clearOpenAlertsForEmployee($employee, (int) $clockIn->id);

            return null;
        }

        $first = $samples->first();
        $last = $samples->last();
        $spanMinutes = 0;
        if ($first?->recorded_at !== null && $last?->recorded_at !== null) {
            $spanMinutes = (int) max(1, $first->recorded_at->diffInMinutes($last->recorded_at));
        }

        if ($spanMinutes < $windowMinutes) {
            return $this->openIdleAlertForSession($employee->id, (int) $clockIn->id);
        }

        $existingOpen = $this->openIdleAlertForSession($employee->id, (int) $clockIn->id);
        if ($existingOpen !== null) {
            $existingOpen->idle_minutes = max((int) $existingOpen->idle_minutes, $spanMinutes);
            $existingOpen->max_displacement_meters = $analysis['max_displacement_meters'];
            $existingOpen->center_latitude = $analysis['center_latitude'];
            $existingOpen->center_longitude = $analysis['center_longitude'];
            $existingOpen->save();

            return $existingOpen->fresh();
        }

        $cooldownSince = now('UTC')->subMinutes($this->idleAlertCooldownMinutes());
        $recent = TimeClockIdleAlert::query()
            ->where('employee_id', $employee->id)
            ->where('clock_in_entry_id', $clockIn->id)
            ->where('detected_at', '>=', $cooldownSince)
            ->orderByDesc('detected_at')
            ->first();

        if ($recent !== null) {
            return null;
        }

        return TimeClockIdleAlert::query()->create([
            'employee_id' => $employee->id,
            'clock_in_entry_id' => $clockIn->id,
            'started_at' => $first?->recorded_at ?? now('UTC'),
            'detected_at' => now('UTC'),
            'idle_minutes' => $spanMinutes,
            'center_latitude' => $analysis['center_latitude'],
            'center_longitude' => $analysis['center_longitude'],
            'max_displacement_meters' => $analysis['max_displacement_meters'],
            'status' => TimeClockIdleAlert::STATUS_OPEN,
        ]);
    }

    private function openIdleAlertForSession(int $employeeId, int $clockInEntryId): ?TimeClockIdleAlert
    {
        return TimeClockIdleAlert::query()
            ->where('employee_id', $employeeId)
            ->where('clock_in_entry_id', $clockInEntryId)
            ->whereIn('status', [
                TimeClockIdleAlert::STATUS_OPEN,
                TimeClockIdleAlert::STATUS_ACKNOWLEDGED,
            ])
            ->orderByDesc('detected_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Site used for live list / site cards: weekly schedule first (may differ from assignment).
     */
    private function resolveDisplayWorkLocation(
        Employee $employee,
        TimeClockEntry $clockIn,
        ?EmployeeScheduleShift $scheduleRow = null,
    ): ?WorkLocation {
        $scheduleRow ??= $this->todaysScheduledShiftRow($employee);
        $scheduleRow?->loadMissing('workLocation');
        if ($scheduleRow?->workLocation instanceof WorkLocation) {
            return $scheduleRow->workLocation;
        }

        if ($clockIn->relationLoaded('workLocation') && $clockIn->workLocation instanceof WorkLocation) {
            return $clockIn->workLocation;
        }

        if ($clockIn->work_location_id !== null) {
            $fromSession = WorkLocation::query()->find($clockIn->work_location_id);
            if ($fromSession instanceof WorkLocation) {
                return $fromSession;
            }
        }

        $employee->loadMissing('workLocation');

        return $employee->workLocation instanceof WorkLocation ? $employee->workLocation : null;
    }

    /**
     * Today's concrete weekly-schedule row (no assignment materialization).
     */
    private function todaysScheduledShiftRow(Employee $employee): ?EmployeeScheduleShift
    {
        $now = DisplayTimezone::now();
        $shifts = TimeClockScheduledShift::shiftsForDate($employee, $now->toDateString());
        $row = TimeClockScheduledShift::pickBestForMoment($shifts, $now);
        $row?->loadMissing(['workLocation', 'shiftTemplate']);

        return $row;
    }

    /**
     * Geofence / ping site: stay on the punch session location (set from schedule at clock-in).
     */
    private function resolveWorkLocation(Employee $employee, TimeClockEntry $clockIn): ?WorkLocation
    {
        if ($clockIn->work_location_id !== null) {
            $fromSession = WorkLocation::query()->find($clockIn->work_location_id);
            if ($fromSession instanceof WorkLocation) {
                return $fromSession;
            }
        }

        $fromSchedule = $this->todaysScheduledShiftRow($employee)?->workLocation;
        if ($fromSchedule instanceof WorkLocation) {
            return $fromSchedule;
        }

        $employee->loadMissing('workLocation');

        return $employee->workLocation instanceof WorkLocation ? $employee->workLocation : null;
    }

    /**
     * @return array{
     *     distance_meters: float|null,
     *     allowed_radius_meters: int,
     *     within_geofence: bool,
     * }
     */
    private function evaluateAgainstSite(
        ?WorkLocation $location,
        TimeClockEntry $clockIn,
        float $deviceLatitude,
        float $deviceLongitude,
        ?float $accuracyMeters,
    ): array {
        $radius = $this->timeClock->geofenceRadiusMeters();
        $expectedLat = $location?->latitude !== null
            ? (float) $location->latitude
            : ($clockIn->expected_latitude !== null ? (float) $clockIn->expected_latitude : null);
        $expectedLng = $location?->longitude !== null
            ? (float) $location->longitude
            : ($clockIn->expected_longitude !== null ? (float) $clockIn->expected_longitude : null);

        if ($expectedLat === null || $expectedLng === null) {
            return [
                'distance_meters' => null,
                'allowed_radius_meters' => $radius,
                'within_geofence' => false,
            ];
        }

        $distance = GeoDistance::metersBetween(
            $deviceLatitude,
            $deviceLongitude,
            $expectedLat,
            $expectedLng,
        );

        $bufferCap = (float) config('time_clock.geofence_accuracy_buffer_cap_meters', 100);
        $buffer = min(max($accuracyMeters ?? 0.0, 0.0), $bufferCap);

        return [
            'distance_meters' => round($distance, 2),
            'allowed_radius_meters' => $radius,
            'within_geofence' => $distance <= ($radius + $buffer),
        ];
    }
}
