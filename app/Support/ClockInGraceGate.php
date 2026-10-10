<?php

namespace App\Support;

use App\Exceptions\TimeClockException;
use App\Models\ClockInException;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Applies the clock-in grace window to a scheduled shift and records an
 * exception when the organization requires admin approval.
 */
final class ClockInGraceGate
{
    /**
     * @return array{
     *     blocks_clock_in: bool,
     *     issue: string|null,
     *     window: array<string, mixed>|null
     * }
     */
    public static function assess(Employee $employee, ?CarbonInterface $now = null): array
    {
        $now = ($now ?? DisplayTimezone::now())->copy();
        $settings = ClockInGraceSettings::current(self::connectionName($employee));
        $shift = self::shiftForStatus($employee, $now);
        if ($shift === null) {
            return [
                'blocks_clock_in' => false,
                'issue' => null,
                'window' => null,
            ];
        }

        return self::evaluation($employee, $shift, $now, $settings, forAttempt: false);
    }

    public static function assertAllowsClockIn(Employee $employee, EmployeeScheduleShift $shift, ?CarbonInterface $now = null): void
    {
        $now = ($now ?? DisplayTimezone::now())->copy();
        $settings = ClockInGraceSettings::current(self::connectionName($employee));
        $target = [
            'schedule_shift_id' => $shift->id ? (int) $shift->id : null,
            'date' => $shift->scheduled_date?->toDateString() ?? $now->copy()->timezone(DisplayTimezone::name())->toDateString(),
            'start_hm' => ClockInGrace::storedTimeToHm($shift->start_time),
        ];

        $result = self::evaluation($employee, $target, $now, $settings, forAttempt: true);
        if (! $result['blocks_clock_in']) {
            return;
        }

        throw new TimeClockException(
            (string) $result['issue'],
            (string) ($result['window']['block_message'] ?? 'Clock-in is outside the allowed window.'),
            422,
            $result['record'] ?? [],
        );
    }

    public static function persistBlockedAttempt(TimeClockException $exception): void
    {
        $payload = $exception->details['exception'] ?? null;
        if (! is_array($payload) || $payload === []) {
            return;
        }

        $connection = isset($payload['connection']) && is_string($payload['connection'])
            ? $payload['connection']
            : (string) config('database.default');

        if (! Schema::connection($connection)->hasTable('clock_in_exceptions')) {
            return;
        }

        $shiftStartsAt = Carbon::parse((string) $payload['shift_starts_at'])->utc();
        $scheduledDate = (string) $payload['scheduled_date'];
        $scheduleShiftId = isset($payload['schedule_shift_id']) ? (int) $payload['schedule_shift_id'] : null;
        if ($scheduleShiftId === 0) {
            $scheduleShiftId = null;
        }

        $existing = self::matching($connection, (int) $payload['employee_id'], $scheduleShiftId, $scheduledDate, $shiftStartsAt)
            ->where('status', ClockInException::STATUS_PENDING)
            ->first();

        $attributes = [
            'employee_id' => (int) $payload['employee_id'],
            'schedule_shift_id' => $scheduleShiftId,
            'scheduled_date' => $scheduledDate,
            'shift_starts_at' => $shiftStartsAt,
            'kind' => (string) $payload['kind'],
            'status' => ClockInException::STATUS_PENDING,
            'attempted_at' => now('UTC'),
            'grace_minutes' => (int) $payload['grace_minutes'],
            'minutes_outside' => (int) $payload['minutes_outside'],
        ];

        if ($existing instanceof ClockInException) {
            $existing->fill($attributes);
            $existing->save();

            return;
        }

        $created = new ClockInException($attributes);
        $created->setConnection($connection);
        $created->save();
    }

    public static function noteSuccessfulClockIn(Employee $employee, EmployeeScheduleShift $shift): void
    {
        $connection = self::connectionName($employee);
        if (! Schema::connection($connection)->hasTable('clock_in_exceptions')) {
            return;
        }

        $start = ClockInGrace::shiftStart($shift);
        $query = self::matching(
            $connection,
            (int) $employee->id,
            $shift->id ? (int) $shift->id : null,
            $start->toDateString(),
            $start->copy()->utc(),
        )->whereIn('status', [ClockInException::STATUS_PENDING, ClockInException::STATUS_CLEARED]);

        foreach ($query->get() as $row) {
            $row->status = $row->status === ClockInException::STATUS_CLEARED
                ? ClockInException::STATUS_USED
                : ClockInException::STATUS_VOID;
            $row->save();
        }
    }

    /**
     * @param  array{schedule_shift_id: int|null, date: string, start_hm: string}  $shift
     * @param  array{grace_minutes: int, outside_policy: string, persisted: bool, ready: bool}  $settings
     * @return array{
     *     blocks_clock_in: bool,
     *     issue: string|null,
     *     window: array<string, mixed>,
     *     record: array<string, mixed>
     * }
     */
    private static function evaluation(
        Employee $employee,
        array $shift,
        CarbonInterface $now,
        array $settings,
        bool $forAttempt,
    ): array {
        $start = ClockInGrace::shiftStartAt($shift['date'], $shift['start_hm']);
        $bounds = ClockInGrace::bounds($start, $settings['grace_minutes']);
        $deviation = ClockInGrace::deviation($now, $start, $settings['grace_minutes']);
        $policy = $settings['ready']
            ? $settings['outside_policy']
            : ClockInGrace::POLICY_PREVENT;
        $clearance = self::clearanceStatus(
            $employee,
            $shift['schedule_shift_id'],
            $shift['date'],
            $start,
        );
        $decision = ClockInGrace::decision($deviation, $policy, $clearance);

        $blocks = $decision['blocks'];
        if (! $forAttempt && $decision['record_exception'] && $clearance === null) {
            $blocks = false;
        }

        $issue = $blocks ? $decision['issue'] : null;
        $boundary = $deviation === ClockInGrace::KIND_EARLY ? $bounds['earliest'] : $bounds['latest'];
        $message = $issue !== null
            ? ClockInGrace::blockedMessage(
                $issue,
                $deviation,
                $settings['grace_minutes'],
                $bounds['start'],
                $bounds['earliest'],
                $bounds['latest'],
            )
            : null;

        $record = [];
        if ($forAttempt && $decision['record_exception'] && $deviation !== null) {
            $record = [
                'record_exception' => true,
                'exception' => [
                    'connection' => self::connectionName($employee),
                    'employee_id' => (int) $employee->id,
                    'schedule_shift_id' => $shift['schedule_shift_id'],
                    'scheduled_date' => $shift['date'],
                    'shift_starts_at' => $start->copy()->utc()->toIso8601String(),
                    'kind' => $deviation,
                    'grace_minutes' => $settings['grace_minutes'],
                    'minutes_outside' => ClockInGrace::minutesOutside($now, $boundary),
                ],
            ];
        }

        return [
            'blocks_clock_in' => $blocks,
            'issue' => $issue,
            'window' => [
                'grace_minutes' => $settings['grace_minutes'],
                'policy' => $policy,
                'shift_starts_at' => $start->copy()->utc()->toIso8601String(),
                'earliest_at' => $bounds['earliest']->copy()->utc()->toIso8601String(),
                'latest_at' => $bounds['latest']->copy()->utc()->toIso8601String(),
                'earliest_label' => $bounds['earliest']->format('g:i A'),
                'latest_label' => $bounds['latest']->format('g:i A'),
                'start_label' => $bounds['start']->format('g:i A'),
                'within_window' => $deviation === null,
                'deviation' => $deviation,
                'exception_status' => $clearance,
                'block_message' => $message,
            ],
            'record' => $record,
        ];
    }

    /**
     * @return array{schedule_shift_id: int|null, date: string, start_hm: string}|null
     */
    private static function shiftForStatus(Employee $employee, CarbonInterface $now): ?array
    {
        $localNow = $now->copy()->timezone(DisplayTimezone::name());
        $date = $localNow->toDateString();
        $shifts = TimeClockScheduledShift::shiftsForDate($employee, $date);
        if ($shifts->isNotEmpty()) {
            $picked = TimeClockScheduledShift::clockInTargetForEmployee($employee, $localNow);
            if (! $picked instanceof EmployeeScheduleShift) {
                return null;
            }

            return [
                'schedule_shift_id' => $picked->id ? (int) $picked->id : null,
                'date' => $picked->scheduled_date?->toDateString() ?? $date,
                'start_hm' => ClockInGrace::storedTimeToHm($picked->start_time),
            ];
        }

        if (TimeClockScheduledShift::shiftIssue($employee, $localNow) !== null) {
            return null;
        }

        $display = TimeClockScheduledShift::todayShiftForDisplay($employee, $localNow);
        if ($display === null) {
            return null;
        }

        return [
            'schedule_shift_id' => null,
            'date' => $date,
            'start_hm' => ClockInGrace::storedTimeToHm($display['start_time']),
        ];
    }

    private static function clearanceStatus(
        Employee $employee,
        ?int $scheduleShiftId,
        string $date,
        CarbonInterface $shiftStart,
    ): ?string {
        $connection = self::connectionName($employee);
        if (! Schema::connection($connection)->hasTable('clock_in_exceptions')) {
            return null;
        }

        $rows = self::matching($connection, (int) $employee->id, $scheduleShiftId, $date, $shiftStart->copy()->utc())
            ->whereIn('status', [ClockInException::STATUS_PENDING, ClockInException::STATUS_CLEARED])
            ->get();

        if ($rows->contains(static fn (ClockInException $row): bool => $row->status === ClockInException::STATUS_CLEARED)) {
            return ClockInGrace::CLEARANCE_CLEARED;
        }

        if ($rows->contains(static fn (ClockInException $row): bool => $row->status === ClockInException::STATUS_PENDING)) {
            return ClockInGrace::CLEARANCE_PENDING;
        }

        return null;
    }

    private static function connectionName(Employee $employee): string
    {
        return $employee->getConnection()->getName();
    }

    /**
     * @return Builder<ClockInException>
     */
    private static function matching(
        string $connection,
        int $employeeId,
        ?int $scheduleShiftId,
        string $date,
        CarbonInterface $shiftStartsAtUtc,
    ): Builder {
        $query = ClockInException::on($connection)->where('employee_id', $employeeId);

        if ($scheduleShiftId !== null && $scheduleShiftId > 0) {
            return $query->where('schedule_shift_id', $scheduleShiftId);
        }

        return $query
            ->whereDate('scheduled_date', $date)
            ->where('shift_starts_at', $shiftStartsAtUtc->copy()->utc());
    }
}
