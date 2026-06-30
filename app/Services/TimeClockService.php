<?php

namespace App\Services;

use App\Exceptions\TimeClockException;
use App\Models\Employee;
use App\Models\TimeClockEntry;
use App\Models\WorkLocation;
use App\Support\GeoDistance;
use Illuminate\Support\Facades\DB;

class TimeClockService
{
    public function geofenceRadiusMeters(): int
    {
        $radius = (int) config('time_clock.geofence_radius_meters', 100);

        return max(10, min($radius, 5_000));
    }

    /**
     * @return array<string, mixed>
     */
    public function statusFor(Employee $employee): array
    {
        $employee->loadMissing(['assignedDepartment', 'workLocation', 'assignedShift']);

        $lastEntry = $this->latestEntryFor($employee);
        $isClockedIn = $lastEntry !== null && $lastEntry->event_type === TimeClockEntry::EVENT_CLOCK_IN;
        $location = $employee->workLocation;
        $hasCoordinates = $this->workLocationHasCoordinates($location);

        return [
            'is_clocked_in' => $isClockedIn,
            'can_clock_in' => ! $isClockedIn && $employee->work_location_id !== null && $hasCoordinates,
            'can_clock_out' => $isClockedIn,
            'geofence_radius_meters' => $this->geofenceRadiusMeters(),
            'open_session' => $isClockedIn && $lastEntry !== null
                ? [
                    'entry_id' => $lastEntry->id,
                    'clocked_in_at' => $lastEntry->clocked_at?->toIso8601String(),
                    'work_location_id' => $lastEntry->work_location_id,
                    'within_geofence' => (bool) $lastEntry->within_geofence,
                ]
                : null,
            'last_event' => $lastEntry instanceof TimeClockEntry ? $lastEntry->toMobilePayload() : null,
            'assignment_ready' => $employee->work_location_id !== null && $hasCoordinates,
            'assignment_issue' => $this->assignmentIssue($employee, $hasCoordinates),
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
            $employee->loadMissing(['workLocation', 'assignedDepartment', 'assignedShift']);

            $this->assertCanClockIn($employee);

            $location = $employee->workLocation;
            if (! $location instanceof WorkLocation) {
                throw new TimeClockException('work_location_not_found', 'Assigned work location not found.');
            }

            $geofence = $this->evaluateGeofence($location, $device['latitude'], $device['longitude']);
            $this->assertWithinGeofence($geofence);

            $entry = $this->createEntry(
                $employee,
                TimeClockEntry::EVENT_CLOCK_IN,
                $device,
                $location,
                $geofence,
                TimeClockEntry::PUNCH_SOURCE_MANUAL,
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
    public function clockOut(Employee $employee, array $device): array
    {
        return DB::transaction(function () use ($employee, $device) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $employee->loadMissing(['workLocation', 'assignedDepartment', 'assignedShift']);

            $this->assertCanClockOut($employee);

            $location = $employee->workLocation;
            if (! $location instanceof WorkLocation) {
                throw new TimeClockException('work_location_not_found', 'Assigned work location not found.');
            }

            $geofence = $this->evaluateGeofence($location, $device['latitude'], $device['longitude']);
            $this->assertWithinGeofence($geofence);

            $entry = $this->createEntry(
                $employee,
                TimeClockEntry::EVENT_CLOCK_OUT,
                $device,
                $location,
                $geofence,
                TimeClockEntry::PUNCH_SOURCE_MANUAL,
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

            $this->assertCanClockOut($employee);

            $location = $employee->workLocation;
            if (! $location instanceof WorkLocation) {
                throw new TimeClockException('work_location_not_found', 'Assigned work location not found.');
            }

            $geofence = $this->evaluateGeofence($location, $device['latitude'], $device['longitude']);
            $this->assertOutsideGeofence($geofence);

            $entry = $this->createEntry(
                $employee,
                TimeClockEntry::EVENT_CLOCK_OUT,
                $device,
                $location,
                $geofence,
                TimeClockEntry::PUNCH_SOURCE_AUTO_GEOFENCE_EXIT,
            );

            return [
                'entry' => $entry,
                'time_clock' => $this->statusFor($employee),
            ];
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

        $last = $this->latestEntryFor($employee);
        if ($last !== null && $last->event_type === TimeClockEntry::EVENT_CLOCK_IN) {
            throw new TimeClockException(
                'already_clocked_in',
                'You are already clocked in. Clock out before starting another shift.',
            );
        }
    }

    private function assertCanClockOut(Employee $employee): void
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

        $last = $this->latestEntryFor($employee);
        if ($last === null || $last->event_type !== TimeClockEntry::EVENT_CLOCK_IN) {
            throw new TimeClockException(
                'not_clocked_in',
                'You are not clocked in.',
            );
        }
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
    private function assertOutsideGeofence(array $geofence): void
    {
        if (! $geofence['within_geofence']) {
            return;
        }

        throw new TimeClockException(
            'still_within_geofence',
            sprintf(
                'You are still within the work site geofence (about %.0f m away; allowed radius is %d m).',
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
     * @return array{
     *     distance_meters: float,
     *     allowed_radius_meters: int,
     *     within_geofence: bool,
     *     expected_latitude: float,
     *     expected_longitude: float,
     * }
     */
    private function evaluateGeofence(WorkLocation $location, float $deviceLatitude, float $deviceLongitude): array
    {
        $expectedLat = (float) $location->latitude;
        $expectedLng = (float) $location->longitude;
        $radius = $this->geofenceRadiusMeters();
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
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null}  $device
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
    ): TimeClockEntry {
        return TimeClockEntry::query()->create([
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
            'shift_id' => $employee->shift_id,
        ]);
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
