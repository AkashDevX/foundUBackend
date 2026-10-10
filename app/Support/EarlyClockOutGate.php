<?php

namespace App\Support;

use App\Exceptions\TimeClockException;
use App\Models\EarlyClockOut;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Blocks a manual clock-out that is earlier than the scheduled end until an
 * admin approves it. There is no grace period. The employee must leave a note.
 */
final class EarlyClockOutGate
{
    public const ISSUE_NOTE_REQUIRED = 'clock_out_note_required';

    public const ISSUE_APPROVAL_PENDING = 'clock_out_approval_pending';

    public static function shiftEnd(EmployeeScheduleShift $shift, ?CarbonInterface $fallbackDate = null): Carbon
    {
        $start = ClockInGrace::shiftStart($shift, $fallbackDate);
        $end = ClockInGrace::shiftStartAt($start->toDateString(), ClockInGrace::storedTimeToHm($shift->end_time));
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $end;
    }

    /**
     * What the mobile clock-out screen should show for the open shift.
     *
     * @return array{needs_approval: bool, approved: bool, shift_end_label: string|null}
     */
    public static function mobileStatus(Employee $employee, ?CarbonInterface $now = null): array
    {
        $empty = [
            'needs_approval' => false,
            'approved' => false,
            'shift_end_label' => null,
        ];

        $now = ($now ?? DisplayTimezone::now())->copy();
        $shift = self::scheduledShift($employee, $now);
        if (! $shift instanceof EmployeeScheduleShift) {
            return $empty;
        }

        return self::mobileStatusForShift($employee, $shift, $now);
    }

    /**
     * @return array{needs_approval: bool, approved: bool, shift_end_label: string|null}
     */
    public static function mobileStatusForShift(
        Employee $employee,
        EmployeeScheduleShift $shift,
        ?CarbonInterface $now = null,
    ): array {
        $now = ($now ?? DisplayTimezone::now())->copy();
        $end = self::shiftEnd($shift, $now);
        $label = $end->format('g:i A');
        $moment = $now->copy()->timezone($end->timezone)->seconds(0);
        if ($moment->greaterThanOrEqualTo($end->copy()->seconds(0))) {
            return [
                'needs_approval' => false,
                'approved' => false,
                'shift_end_label' => $label,
            ];
        }

        $connection = self::connectionName($employee);
        if (! Schema::connection($connection)->hasTable('early_clock_outs')) {
            return [
                'needs_approval' => false,
                'approved' => false,
                'shift_end_label' => $label,
            ];
        }

        $scheduleShiftId = $shift->id ? (int) $shift->id : null;
        $approved = self::clearanceStatus($employee, $scheduleShiftId, $end) === ClockInGrace::CLEARANCE_CLEARED;

        return [
            'needs_approval' => ! $approved,
            'approved' => $approved,
            'shift_end_label' => $label,
        ];
    }

    public static function assertAllowsClockOut(Employee $employee, ?string $note, ?CarbonInterface $now = null): void
    {
        $now = ($now ?? DisplayTimezone::now())->copy();
        $shift = self::scheduledShift($employee, $now);
        if (! $shift instanceof EmployeeScheduleShift) {
            return;
        }

        self::assertForShift($employee, $shift, $note, $now);
    }

    public static function assertForShift(
        Employee $employee,
        EmployeeScheduleShift $shift,
        ?string $note,
        ?CarbonInterface $now = null,
    ): void {
        $now = ($now ?? DisplayTimezone::now())->copy();
        $end = self::shiftEnd($shift, $now);
        $moment = $now->copy()->timezone($end->timezone)->seconds(0);
        if ($moment->greaterThanOrEqualTo($end->copy()->seconds(0))) {
            return;
        }

        $connection = self::connectionName($employee);
        if (! Schema::connection($connection)->hasTable('early_clock_outs')) {
            return;
        }

        $scheduleShiftId = $shift->id ? (int) $shift->id : null;
        $clearance = self::clearanceStatus($employee, $scheduleShiftId, $end);
        if ($clearance === ClockInGrace::CLEARANCE_CLEARED) {
            return;
        }

        $endLabel = $end->format('g:i A');
        $trimmed = trim((string) $note);
        if ($clearance !== ClockInGrace::CLEARANCE_PENDING && $trimmed === '') {
            throw new TimeClockException(
                self::ISSUE_NOTE_REQUIRED,
                "You're leaving before {$endLabel}. Add a note for your manager. You won't be clocked out until an administrator approves this.",
            );
        }

        $minutesEarly = (int) abs($moment->diffInMinutes($end->copy()->seconds(0), false));

        throw new TimeClockException(
            self::ISSUE_APPROVAL_PENDING,
            "You're leaving before {$endLabel}. You won't be clocked out until an administrator approves this. Please contact the admin for further assistance.",
            422,
            [
                'early_clock_out' => [
                    'connection' => $connection,
                    'employee_id' => (int) $employee->id,
                    'schedule_shift_id' => $scheduleShiftId,
                    'scheduled_date' => $shift->scheduled_date?->toDateString() ?? $end->toDateString(),
                    'shift_ends_at' => $end->copy()->utc()->toIso8601String(),
                    'employee_note' => $trimmed !== '' ? mb_substr($trimmed, 0, 2000) : null,
                    'minutes_early' => max(1, $minutesEarly),
                ],
            ],
        );
    }

    public static function persistBlockedAttempt(TimeClockException $exception): void
    {
        $payload = $exception->details['early_clock_out'] ?? null;
        if (! is_array($payload) || $payload === []) {
            return;
        }

        $connection = isset($payload['connection']) && is_string($payload['connection'])
            ? $payload['connection']
            : (string) config('database.default');

        if (! Schema::connection($connection)->hasTable('early_clock_outs')) {
            return;
        }

        $shiftEndsAt = Carbon::parse((string) $payload['shift_ends_at'])->utc();
        $scheduleShiftId = isset($payload['schedule_shift_id']) ? (int) $payload['schedule_shift_id'] : null;
        if ($scheduleShiftId === 0) {
            $scheduleShiftId = null;
        }

        $existing = self::matching($connection, (int) $payload['employee_id'], $scheduleShiftId, $shiftEndsAt)
            ->where('status', EarlyClockOut::STATUS_PENDING)
            ->first();

        $attributes = [
            'employee_id' => (int) $payload['employee_id'],
            'schedule_shift_id' => $scheduleShiftId,
            'scheduled_date' => (string) $payload['scheduled_date'],
            'shift_ends_at' => $shiftEndsAt,
            'status' => EarlyClockOut::STATUS_PENDING,
            'attempted_at' => now('UTC'),
            'minutes_early' => (int) $payload['minutes_early'],
        ];
        if (is_string($payload['employee_note']) && $payload['employee_note'] !== '') {
            $attributes['employee_note'] = $payload['employee_note'];
        }

        if ($existing instanceof EarlyClockOut) {
            $existing->fill($attributes);
            $existing->save();

            return;
        }

        if (! isset($attributes['employee_note'])) {
            return;
        }

        $created = new EarlyClockOut($attributes);
        $created->setConnection($connection);
        $created->save();
    }

    public static function noteSuccessfulClockOut(Employee $employee, ?CarbonInterface $now = null): void
    {
        $connection = self::connectionName($employee);
        if (! Schema::connection($connection)->hasTable('early_clock_outs')) {
            return;
        }

        $now = ($now ?? DisplayTimezone::now())->copy();
        $shift = self::scheduledShift($employee, $now);
        if (! $shift instanceof EmployeeScheduleShift) {
            return;
        }

        $end = self::shiftEnd($shift, $now);
        $rows = self::matching($connection, (int) $employee->id, $shift->id ? (int) $shift->id : null, $end->copy()->utc())
            ->whereIn('status', [EarlyClockOut::STATUS_PENDING, EarlyClockOut::STATUS_CLEARED])
            ->get();

        foreach ($rows as $row) {
            $row->status = $row->status === EarlyClockOut::STATUS_CLEARED
                ? EarlyClockOut::STATUS_USED
                : EarlyClockOut::STATUS_VOID;
            $row->save();
        }
    }

    private static function scheduledShift(Employee $employee, CarbonInterface $now): ?EmployeeScheduleShift
    {
        $localNow = $now->copy()->timezone(DisplayTimezone::name());
        $openId = TimeClockScheduledShift::openSessionScheduleShiftId($employee);
        if ($openId !== null) {
            $open = EmployeeScheduleShift::query()->find($openId);
            if ($open instanceof EmployeeScheduleShift && (int) $open->employee_id === (int) $employee->id) {
                return $open;
            }
        }

        $picked = TimeClockScheduledShift::pickBestForMoment(
            TimeClockScheduledShift::shiftsForDate($employee, $localNow->toDateString()),
            $localNow,
        );
        if ($picked instanceof EmployeeScheduleShift) {
            return $picked;
        }

        $display = TimeClockScheduledShift::todayShiftForDisplay($employee, $localNow);
        if ($display === null) {
            return null;
        }

        $shift = new EmployeeScheduleShift([
            'scheduled_date' => $localNow->toDateString(),
            'start_time' => $display['start_time'],
            'end_time' => $display['end_time'],
        ]);
        $shift->setConnection(self::connectionName($employee));

        return $shift;
    }

    private static function clearanceStatus(Employee $employee, ?int $scheduleShiftId, CarbonInterface $shiftEnd): ?string
    {
        $rows = self::matching(
            self::connectionName($employee),
            (int) $employee->id,
            $scheduleShiftId,
            $shiftEnd->copy()->utc(),
        )->whereIn('status', [EarlyClockOut::STATUS_PENDING, EarlyClockOut::STATUS_CLEARED])->get();

        if ($rows->contains(static fn (EarlyClockOut $row): bool => $row->status === EarlyClockOut::STATUS_CLEARED)) {
            return ClockInGrace::CLEARANCE_CLEARED;
        }

        if ($rows->contains(static fn (EarlyClockOut $row): bool => $row->status === EarlyClockOut::STATUS_PENDING)) {
            return ClockInGrace::CLEARANCE_PENDING;
        }

        return null;
    }

    /**
     * @return Builder<EarlyClockOut>
     */
    private static function matching(
        string $connection,
        int $employeeId,
        ?int $scheduleShiftId,
        CarbonInterface $shiftEndsAtUtc,
    ): Builder {
        $query = EarlyClockOut::on($connection)->where('employee_id', $employeeId);

        if ($scheduleShiftId !== null && $scheduleShiftId > 0) {
            return $query->where('schedule_shift_id', $scheduleShiftId);
        }

        return $query->where('shift_ends_at', $shiftEndsAtUtc->copy()->utc());
    }

    private static function connectionName(Employee $employee): string
    {
        return $employee->getConnection()->getName();
    }
}
