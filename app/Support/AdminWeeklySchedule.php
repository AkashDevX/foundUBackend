<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeAssignmentShift;
use App\Models\EmployeeLeaveEntitlement;
use App\Models\EmployeeLeaveRecord;
use App\Models\EmployeeScheduleShift;
use App\Models\LeaveType;
use App\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class AdminWeeklySchedule
{
    /** @var list<array{bg: string, border: string, text: string, accent: string}> */
    private const CARD_PALETTES = [
        ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-950', 'accent' => 'bg-emerald-500'],
        ['bg' => 'bg-sky-50', 'border' => 'border-sky-200', 'text' => 'text-sky-950', 'accent' => 'bg-sky-500'],
        ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-950', 'accent' => 'bg-amber-500'],
        ['bg' => 'bg-violet-50', 'border' => 'border-violet-200', 'text' => 'text-violet-950', 'accent' => 'bg-violet-500'],
        ['bg' => 'bg-orange-50', 'border' => 'border-orange-200', 'text' => 'text-orange-950', 'accent' => 'bg-orange-500'],
        ['bg' => 'bg-teal-50', 'border' => 'border-teal-200', 'text' => 'text-teal-950', 'accent' => 'bg-teal-500'],
    ];

    private const TIME_OFF_PALETTE = [
        'bg' => 'bg-slate-100',
        'border' => 'border-slate-300',
        'text' => 'text-slate-700',
        'accent' => 'bg-slate-400',
    ];

    public static function resolveWeekStart(?string $weekParam): Carbon
    {
        $tz = DisplayTimezone::name();

        if (is_string($weekParam) && $weekParam !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekParam)) {
            return Carbon::parse($weekParam, $tz)->startOfDay()->startOfWeek(Carbon::MONDAY);
        }

        return DisplayTimezone::now()->startOfWeek(Carbon::MONDAY);
    }

    /**
     * @return list<array{
     *     key: string,
     *     date: CarbonInterface,
     *     date_string: string,
     *     weekday_label: string,
     *     day_number: string,
     *     is_today: bool,
     * }>
     */
    public static function weekDays(CarbonInterface $weekStart): array
    {
        $today = DisplayTimezone::now()->toDateString();
        $days = [];

        foreach (AdminWeeklyAvailability::DAY_KEYS as $index => $dayKey) {
            $date = $weekStart->copy()->addDays($index);
            $days[] = [
                'key' => $dayKey,
                'date' => $date,
                'date_string' => $date->toDateString(),
                'weekday_label' => strtoupper(substr($date->format('D'), 0, 3)),
                'day_number' => $date->format('j'),
                'is_today' => $date->toDateString() === $today,
            ];
        }

        return $days;
    }

    public static function formatWeekLabel(CarbonInterface $weekStart): string
    {
        $weekEnd = $weekStart->copy()->addDays(6);

        if ($weekStart->year === $weekEnd->year) {
            return $weekStart->format('j').' – '.$weekEnd->format('j M Y');
        }

        return $weekStart->format('j M Y').' – '.$weekEnd->format('j M Y');
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, EmployeeScheduleShift>  $scheduleEntries
     * @return array{
     *     days: list<array<string, mixed>>,
     *     rows: list<array<string, mixed>>,
     *     stats: array<string, int|string>,
     * }
     */
    public static function buildSchedule(Collection $employees, CarbonInterface $weekStart, Collection $scheduleEntries): array
    {
        $days = self::weekDays($weekStart);
        $entriesByEmployeeDate = self::groupEntriesByEmployeeDate($scheduleEntries);
        $rows = [];
        $totalScheduledSeconds = 0;
        $shiftCount = 0;
        $absenceCount = 0;

        foreach ($employees as $employee) {
            $cells = [];
            $rowScheduledSeconds = 0;

            foreach ($days as $day) {
                $lookupKey = ((int) $employee->id).'|'.$day['date_string'];
                $dayEntries = $entriesByEmployeeDate->get($lookupKey, collect());
                $blocks = [];
                $hasTimeOff = $dayEntries->contains(
                    static fn (EmployeeScheduleShift $entry): bool => $entry->entry_type === EmployeeScheduleShift::TYPE_TIME_OFF
                );

                if ($hasTimeOff) {
                    /** @var EmployeeScheduleShift|null $timeOffEntry */
                    $timeOffEntry = $dayEntries->first(
                        static fn (EmployeeScheduleShift $entry): bool => $entry->entry_type === EmployeeScheduleShift::TYPE_TIME_OFF
                    );

                    if ($timeOffEntry !== null) {
                        $blocks[] = self::entryToBlock($timeOffEntry, $employee);
                        $absenceCount++;
                    }
                } else {
                    foreach ($dayEntries as $entry) {
                        if ($entry->entry_type !== EmployeeScheduleShift::TYPE_SHIFT) {
                            continue;
                        }

                        $block = self::entryToBlock($entry, $employee);
                        $blocks[] = $block;

                        $seconds = (int) ($block['duration_seconds'] ?? 0);
                        $rowScheduledSeconds += $seconds;
                        $totalScheduledSeconds += $seconds;
                        $shiftCount++;
                    }

                    if ($blocks === []) {
                        foreach (self::suggestedBlocksFromAssignment($employee, $day['key']) as $suggestion) {
                            $blocks[] = $suggestion;
                        }
                    }
                }

                $cells[$day['key']] = [
                    'is_day_off' => $hasTimeOff,
                    'blocks' => $blocks,
                ];
            }

            $rows[] = [
                'employee' => $employee,
                'employee_public_id' => $employee->public_id,
                'name' => self::employeeDisplayName($employee),
                'initials' => self::employeeInitials($employee),
                'job_title' => self::employeeJobTitle($employee),
                'week_scheduled_seconds' => $rowScheduledSeconds,
                'week_scheduled_label' => AdminTimeClockDisplay::formatDuration($rowScheduledSeconds),
                'cells' => $cells,
            ];
        }

        return [
            'days' => $days,
            'rows' => $rows,
            'stats' => [
                'employees' => count($rows),
                'shifts' => $shiftCount,
                'absences' => $absenceCount,
                'scheduled_hours_label' => AdminTimeClockDisplay::formatDuration($totalScheduledSeconds),
                'scheduled_seconds' => $totalScheduledSeconds,
            ],
        ];
    }

    /**
     * Per-employee leave balances keyed by employee public_id.
     * Each item: { id, code, name, is_paid, allocated, used, remaining }.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<string, list<array<string, mixed>>>
     */
    public static function leaveBalancesForEmployees(string $connection, Collection $employees): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $employeeIds = $employees->pluck('id')->all();

        $leaveTypes = LeaveType::on($connection)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $typesById = $leaveTypes->keyBy('id');

        $entitlements = EmployeeLeaveEntitlement::on($connection)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->groupBy('employee_id');

        $usedByEmployeeCode = [];
        $usedRows = EmployeeLeaveRecord::on($connection)
            ->whereIn('employee_id', $employeeIds)
            ->where('status', '!=', EmployeeLeaveRecord::STATUS_CANCELLED)
            ->get(['employee_id', 'leave_type', 'hours']);

        foreach ($usedRows as $row) {
            $key = (int) $row->employee_id;
            $code = (string) $row->leave_type;
            $usedByEmployeeCode[$key][$code] = ($usedByEmployeeCode[$key][$code] ?? 0.0) + (float) $row->hours;
        }

        $balances = [];

        foreach ($employees as $employee) {
            $employeeId = (int) $employee->id;
            $publicId = (string) $employee->public_id;
            $employeeEntitlements = $entitlements->get($employeeId, collect());

            // Only surface leave types this employee is actually entitled to.
            $rows = $employeeEntitlements
                ->map(static fn ($ent): array => [$typesById->get($ent->leave_type_id), $ent->entitlement_hours])
                ->filter(static fn (array $pair): bool => $pair[0] instanceof LeaveType);

            $list = [];
            foreach ($rows as [$type, $entitlementHours]) {
                /** @var LeaveType $type */
                $allocated = $entitlementHours ?? $type->default_annual_hours;
                $allocatedVal = $allocated !== null ? (float) $allocated : null;
                $used = (float) ($usedByEmployeeCode[$employeeId][$type->code] ?? 0.0);

                $list[] = [
                    'id' => (int) $type->id,
                    'code' => (string) $type->code,
                    'name' => (string) $type->name,
                    'is_paid' => (bool) $type->is_paid,
                    'allocated' => $allocatedVal,
                    'used' => round($used, 2),
                    'remaining' => $allocatedVal !== null ? round($allocatedVal - $used, 2) : null,
                ];
            }

            $balances[$publicId] = array_values($list);
        }

        return $balances;
    }

    /**
     * @param  Collection<int, EmployeeScheduleShift>  $scheduleEntries
     * @return Collection<string, Collection<int, EmployeeScheduleShift>>
     */
    private static function groupEntriesByEmployeeDate(Collection $scheduleEntries): Collection
    {
        return $scheduleEntries->groupBy(static function (EmployeeScheduleShift $entry): string {
            $date = $entry->scheduled_date?->toDateString() ?? '';

            return ((int) $entry->employee_id).'|'.$date;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function entryToBlock(EmployeeScheduleShift $entry, Employee $employee): array
    {
        if ($entry->entry_type === EmployeeScheduleShift::TYPE_TIME_OFF) {
            return [
                'id' => $entry->id,
                'editable' => true,
                'is_suggestion' => false,
                'type' => EmployeeScheduleShift::TYPE_TIME_OFF,
                'time_range' => 'All day',
                'duration_label' => 'Day off',
                'duration_seconds' => 0,
                'title' => 'Day off',
                'subtitle' => $entry->notes !== null && trim($entry->notes) !== '' ? trim($entry->notes) : 'Unavailable',
                'meta' => '',
                'palette' => self::TIME_OFF_PALETTE,
                'scheduled_date' => $entry->scheduled_date?->toDateString(),
                'employee_public_id' => $employee->public_id,
                'start_time' => null,
                'end_time' => null,
                'shift_id' => null,
                'job_title_id' => $entry->job_title_id,
                'department_id' => $entry->department_id,
                'work_location_id' => $entry->work_location_id,
                'notes' => $entry->notes,
                'leave_type_id' => $entry->leave_type_id,
                'leave_type_name' => $entry->leaveType?->name,
                'leave_hours' => $entry->leaveRecord?->hours !== null ? (float) $entry->leaveRecord->hours : null,
            ];
        }

        $start = self::formatStoredTime($entry->start_time);
        $end = self::formatStoredTime($entry->end_time);
        $durationSeconds = self::timeRangeDurationSeconds(
            self::storedTimeToHm($entry->start_time),
            self::storedTimeToHm($entry->end_time)
        );

        $jobTitle = trim((string) ($entry->jobTitle?->name ?? ''));
        if ($jobTitle === '') {
            $jobTitle = self::employeeJobTitle($employee);
        }

        $locationName = trim((string) ($entry->workLocation?->name ?? ''));
        $departmentName = trim((string) ($entry->department?->name ?? ''));
        $shiftName = trim((string) ($entry->shiftTemplate?->name ?? ''));

        return [
            'id' => $entry->id,
            'editable' => true,
            'is_suggestion' => false,
            'type' => EmployeeScheduleShift::TYPE_SHIFT,
            'time_range' => $start.' – '.$end,
            'duration_label' => AdminTimeClockDisplay::formatDuration($durationSeconds),
            'duration_seconds' => $durationSeconds,
            'title' => $jobTitle,
            'subtitle' => $shiftName !== '' ? $shiftName : 'Scheduled shift',
            'meta' => trim(collect([$departmentName, $locationName])->filter()->join(' · ')),
            'palette' => self::paletteForSeed((int) ($entry->work_location_id ?? $entry->department_id ?? $employee->id ?? 0)),
            'scheduled_date' => $entry->scheduled_date?->toDateString(),
            'employee_public_id' => $employee->public_id,
            'start_time' => self::storedTimeToHm($entry->start_time),
            'end_time' => self::storedTimeToHm($entry->end_time),
            'shift_id' => $entry->shift_id,
            'job_title_id' => $entry->job_title_id,
            'department_id' => $entry->department_id,
            'work_location_id' => $entry->work_location_id,
            'notes' => $entry->notes,
            'status' => $entry->status,
            'status_label' => EmployeeScheduleShift::statusLabel($entry->status),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function suggestedBlocksFromAssignment(Employee $employee, string $dayKey): array
    {
        $blocks = [];

        foreach (self::assignmentShiftsForEmployee($employee) as $assignmentShift) {
            $shift = $assignmentShift->shiftTemplate;
            if (! $shift instanceof Shift || ! self::shiftRunsOnDay($shift, $dayKey)) {
                continue;
            }

            $durationSeconds = self::shiftDurationSeconds($shift);
            $unpaidBreakMinutes = (int) ($assignmentShift->unpaid_break_minutes ?? 0);
            $breakLabel = $unpaidBreakMinutes > 0
                ? AdminTimeClockDisplay::formatDuration(max(0, $durationSeconds - ($unpaidBreakMinutes * 60))).' paid'
                : AdminTimeClockDisplay::formatDuration($durationSeconds);

            $blocks[] = [
                'id' => null,
                'editable' => false,
                'is_suggestion' => true,
                'type' => 'suggestion',
                'time_range' => self::shiftTimeRangeLabel($shift),
                'duration_label' => $breakLabel,
                'duration_seconds' => $durationSeconds,
                'title' => self::employeeJobTitle($employee),
                'subtitle' => $shift->name ?: 'From assignment',
                'meta' => trim(collect([
                    $employee->assignedDepartment?->name,
                    $employee->workLocation?->name,
                    $unpaidBreakMinutes > 0 ? $unpaidBreakMinutes.'m unpaid break' : null,
                ])->filter()->join(' · ')),
                'palette' => self::paletteForSeed((int) ($employee->work_location_id ?? $employee->department_id ?? $employee->id ?? 0)),
                'scheduled_date' => null,
                'employee_public_id' => $employee->public_id,
                'start_time' => $shift->start_time instanceof CarbonInterface ? $shift->start_time->format('H:i') : '09:00',
                'end_time' => $shift->end_time instanceof CarbonInterface ? $shift->end_time->format('H:i') : '17:00',
                'shift_id' => $shift->id,
                'job_title_id' => $employee->job_title_id,
                'department_id' => $employee->department_id,
                'work_location_id' => $employee->work_location_id,
                'notes' => null,
            ];
        }

        return $blocks;
    }

    /**
     * @return Collection<int, EmployeeAssignmentShift>
     */
    /**
     * Assignment shifts for schedule suggestions / clock-in eligibility.
     *
     * Prefer the multi-shift `assignmentShifts` relation; if that collection is empty,
     * fall back to the legacy `employees.shift_id` (`assignedShift`). Never short-circuit
     * on a loaded-but-empty relation — TimeClockService often loads `assignedShift` first,
     * which previously made empty `assignmentShifts` hide a valid legacy shift.
     *
     * @return Collection<int, EmployeeAssignmentShift>
     */
    private static function assignmentShiftsForEmployee(Employee $employee): Collection
    {
        $employee->loadMissing(['assignmentShifts.shiftTemplate', 'assignedShift']);

        if ($employee->assignmentShifts->isNotEmpty()) {
            return $employee->assignmentShifts;
        }

        if ($employee->assignedShift instanceof Shift) {
            return collect([self::legacyAssignmentShift($employee->assignedShift)]);
        }

        return collect();
    }

    private static function legacyAssignmentShift(Shift $shift): EmployeeAssignmentShift
    {
        $legacy = new EmployeeAssignmentShift([
            'shift_id' => $shift->id,
            'unpaid_break_minutes' => 0,
            'sort_order' => 0,
        ]);
        $legacy->setRelation('shiftTemplate', $shift);

        return $legacy;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return int Number of rows created.
     */
    public static function fillWeekFromAssignments(string $connection, Collection $employees, CarbonInterface $weekStart, Collection $existingEntries): int
    {
        $existingKeys = $existingEntries
            ->map(static fn (EmployeeScheduleShift $entry): string => ((int) $entry->employee_id).'|'.($entry->scheduled_date?->toDateString() ?? ''))
            ->flip();

        $created = 0;
        $days = self::weekDays($weekStart);

        foreach ($employees as $employee) {
            $assignmentShifts = self::assignmentShiftsForEmployee($employee);
            if ($assignmentShifts->isEmpty()) {
                continue;
            }

            foreach ($days as $day) {
                $lookupKey = ((int) $employee->id).'|'.$day['date_string'];
                if ($existingKeys->has($lookupKey)) {
                    continue;
                }

                $createdForDay = 0;

                foreach ($assignmentShifts as $assignmentShift) {
                    $shift = $assignmentShift->shiftTemplate;
                    if (! $shift instanceof Shift || ! self::shiftRunsOnDay($shift, $day['key'])) {
                        continue;
                    }

                    EmployeeScheduleShift::on($connection)->create([
                        'employee_id' => $employee->id,
                        'scheduled_date' => $day['date_string'],
                        'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
                        'start_time' => $shift->start_time instanceof CarbonInterface ? $shift->start_time->format('H:i') : '09:00',
                        'end_time' => $shift->end_time instanceof CarbonInterface ? $shift->end_time->format('H:i') : '17:00',
                        'shift_id' => $shift->id,
                        'job_title_id' => $employee->job_title_id,
                        'department_id' => $employee->department_id,
                        'work_location_id' => $employee->work_location_id,
                        'notes' => null,
                    ]);

                    $createdForDay++;
                    $created++;
                }

                if ($createdForDay > 0) {
                    $existingKeys->put($lookupKey, true);
                }
            }
        }

        return $created;
    }

    /**
     * True when the employee's assignment shift(s) run on the given date's weekday — i.e. the
     * weekly schedule would show a shift (concrete or suggestion) for that day. Read-only; does
     * not create rows. Callers must separately treat a day-off (time_off) as "no shift".
     */
    public static function hasAssignmentShiftForDate(Employee $employee, CarbonInterface $date): bool
    {
        // Ensure the newer multi-shift assignment relation is loaded; otherwise
        // assignmentShiftsForEmployee() short-circuits on a pre-loaded (often null)
        // legacy `assignedShift` and misses the employee's real assignment shifts.
        $employee->loadMissing(['assignmentShifts.shiftTemplate', 'assignedShift']);

        $dayKey = self::dayKeyForDate($date);

        foreach (self::assignmentShiftsForEmployee($employee) as $assignmentShift) {
            $shift = $assignmentShift->shiftTemplate;
            if ($shift instanceof Shift && self::shiftRunsOnDay($shift, $dayKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create concrete employee_schedule_shifts row(s) for the given date from the employee's
     * assignment shift(s), mirroring the schedule "suggestion" logic. No-op when a concrete
     * shift already exists for the day or the day is marked as time off. Returns rows created.
     */
    public static function materializeAssignmentShiftsForDate(Employee $employee, CarbonInterface $date): int
    {
        // See hasAssignmentShiftForDate(): make sure assignmentShifts is loaded so we
        // don't short-circuit on a pre-loaded null legacy `assignedShift`.
        $employee->loadMissing(['assignmentShifts.shiftTemplate', 'assignedShift']);

        $dateString = $date->toDateString();

        $alreadyScheduled = EmployeeScheduleShift::query()
            ->where('employee_id', $employee->id)
            ->whereIn('entry_type', [EmployeeScheduleShift::TYPE_SHIFT, EmployeeScheduleShift::TYPE_TIME_OFF])
            ->whereDate('scheduled_date', $dateString)
            ->exists();

        if ($alreadyScheduled) {
            return 0;
        }

        $dayKey = self::dayKeyForDate($date);
        $created = 0;

        foreach (self::assignmentShiftsForEmployee($employee) as $assignmentShift) {
            $shift = $assignmentShift->shiftTemplate;
            if (! $shift instanceof Shift || ! self::shiftRunsOnDay($shift, $dayKey)) {
                continue;
            }

            EmployeeScheduleShift::query()->create([
                'employee_id' => $employee->id,
                'scheduled_date' => $dateString,
                'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
                'start_time' => $shift->start_time instanceof CarbonInterface ? $shift->start_time->format('H:i') : '09:00',
                'end_time' => $shift->end_time instanceof CarbonInterface ? $shift->end_time->format('H:i') : '17:00',
                'shift_id' => $shift->id,
                'job_title_id' => $employee->job_title_id,
                'department_id' => $employee->department_id,
                'work_location_id' => $employee->work_location_id,
                'notes' => null,
            ]);

            $created++;
        }

        return $created;
    }

    /** Day key (mon..sun) for a date, matching AdminWeeklyAvailability::DAY_KEYS ordering. */
    public static function dayKeyForDate(CarbonInterface $date): string
    {
        return AdminWeeklyAvailability::DAY_KEYS[$date->dayOfWeekIso - 1] ?? 'mon';
    }

    /** The employee's assignment shift template that runs on the given date's weekday, if any. */
    public static function assignmentShiftForDate(Employee $employee, CarbonInterface $date): ?Shift
    {
        $employee->loadMissing(['assignmentShifts.shiftTemplate', 'assignedShift']);

        $dayKey = self::dayKeyForDate($date);

        foreach (self::assignmentShiftsForEmployee($employee) as $assignmentShift) {
            $shift = $assignmentShift->shiftTemplate;
            if ($shift instanceof Shift && self::shiftRunsOnDay($shift, $dayKey)) {
                return $shift;
            }
        }

        return null;
    }

    /**
     * Read-only display times for the employee's shift on a date (mobile status pill).
     * A concrete schedule row wins; otherwise the assignment shift that runs that weekday.
     * Returns null on a day off or when there's no shift.
     *
     * @return array{start_time: string, end_time: string, start_label: string, end_label: string}|null
     */
    public static function shiftTimesForDate(Employee $employee, CarbonInterface $date): ?array
    {
        $dateString = $date->toDateString();

        $entries = EmployeeScheduleShift::query()
            ->where('employee_id', $employee->id)
            ->whereDate('scheduled_date', $dateString)
            ->orderBy('start_time')
            ->get();

        $hasTimeOff = $entries->contains(
            static fn (EmployeeScheduleShift $entry): bool => $entry->entry_type === EmployeeScheduleShift::TYPE_TIME_OFF
        );
        if ($hasTimeOff) {
            return null;
        }

        /** @var EmployeeScheduleShift|null $concrete */
        $concrete = $entries->first(
            static fn (EmployeeScheduleShift $entry): bool => $entry->entry_type === EmployeeScheduleShift::TYPE_SHIFT
        );
        if ($concrete instanceof EmployeeScheduleShift) {
            return self::shiftTimesPayload($concrete->start_time, $concrete->end_time);
        }

        $shift = self::assignmentShiftForDate($employee, $date);
        if ($shift instanceof Shift) {
            return self::shiftTimesPayload($shift->start_time, $shift->end_time);
        }

        return null;
    }

    /**
     * @return array{start_time: string, end_time: string, start_label: string, end_label: string}
     */
    private static function shiftTimesPayload(mixed $start, mixed $end): array
    {
        return [
            'start_time' => self::storedTimeToHm($start),
            'end_time' => self::storedTimeToHm($end),
            'start_label' => self::formatStoredTime($start),
            'end_label' => self::formatStoredTime($end),
        ];
    }

    /**
     * Mobile GET /api/v1/shifts/schedule — one employee's week view (published + assignment suggestions).
     *
     * @return array<string, mixed>
     */
    public static function mobilePayloadForEmployee(Employee $employee, ?string $weekParam): array
    {
        $weekStart = self::resolveWeekStart($weekParam);
        $weekEnd = $weekStart->copy()->addDays(6);

        $employee->loadMissing(['assignedDepartment', 'assignedJobTitle', 'workLocation', 'assignedShift', 'assignmentShifts.shiftTemplate']);

        $entries = EmployeeScheduleShift::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('scheduled_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with(['shiftTemplate', 'jobTitle', 'department', 'workLocation'])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get();

        $built = self::buildSchedule(collect([$employee]), $weekStart, $entries);
        /** @var array<string, mixed>|null $row */
        $row = $built['rows'][0] ?? null;
        $cells = is_array($row['cells'] ?? null) ? $row['cells'] : [];

        $days = [];
        foreach ($built['days'] as $day) {
            $cell = is_array($cells[$day['key']] ?? null) ? $cells[$day['key']] : ['is_day_off' => false, 'blocks' => []];
            $blocks = is_array($cell['blocks'] ?? null) ? $cell['blocks'] : [];

            $days[] = [
                'day_key' => $day['key'],
                'date' => $day['date_string'],
                'weekday_label' => $day['weekday_label'],
                'day_number' => $day['day_number'],
                'is_today' => $day['is_today'],
                'is_day_off' => (bool) ($cell['is_day_off'] ?? false),
                'entries' => array_map(static fn (array $block): array => self::mobileEntryFromBlock($block), $blocks),
            ];
        }

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'week_label' => self::formatWeekLabel($weekStart),
            'scheduled_hours_label' => is_string($row['week_scheduled_label'] ?? null) ? $row['week_scheduled_label'] : '0h',
            'scheduled_seconds' => (int) ($row['week_scheduled_seconds'] ?? 0),
            'days' => $days,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private static function mobileEntryFromBlock(array $block): array
    {
        return [
            'id' => $block['id'] ?? null,
            'type' => $block['type'] ?? 'shift',
            'is_suggestion' => (bool) ($block['is_suggestion'] ?? false),
            'time_range' => $block['time_range'] ?? '',
            'duration_label' => $block['duration_label'] ?? '',
            'title' => $block['title'] ?? '',
            'subtitle' => $block['subtitle'] ?? '',
            'meta' => $block['meta'] ?? '',
            'notes' => $block['notes'] ?? null,
            'start_time' => $block['start_time'] ?? null,
            'end_time' => $block['end_time'] ?? null,
        ];
    }

    private static function shiftRunsOnDay(Shift $shift, string $dayKey): bool
    {
        $shiftDays = is_array($shift->shift_days) ? $shift->shift_days : [];

        return $shiftDays === [] || in_array($dayKey, $shiftDays, true);
    }

    private static function shiftTimeRangeLabel(Shift $shift): string
    {
        return self::formatStoredTime($shift->start_time).' – '.self::formatStoredTime($shift->end_time);
    }

    private static function shiftDurationSeconds(Shift $shift): int
    {
        return self::timeRangeDurationSeconds(
            self::storedTimeToHm($shift->start_time),
            self::storedTimeToHm($shift->end_time)
        );
    }

    private static function formatStoredTime(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('g:i A');
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return self::formatTimeString(sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]));
        }

        return '—';
    }

    private static function storedTimeToHm(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return '09:00';
    }

    private static function timeRangeDurationSeconds(string $start, string $end): int
    {
        $startMinutes = self::timeToMinutes($start);
        $endMinutes = self::timeToMinutes($end);

        if ($startMinutes === null || $endMinutes === null) {
            return 0;
        }

        if ($endMinutes <= $startMinutes) {
            $endMinutes += 24 * 60;
        }

        return ($endMinutes - $startMinutes) * 60;
    }

    private static function formatTimeString(string $time): string
    {
        $minutes = self::timeToMinutes($time);
        if ($minutes === null) {
            return $time;
        }

        $hours = intdiv($minutes, 60) % 24;
        $mins = $minutes % 60;
        $period = $hours >= 12 ? 'PM' : 'AM';
        $hour12 = $hours % 12;
        if ($hour12 === 0) {
            $hour12 = 12;
        }

        return sprintf('%d:%02d %s', $hour12, $mins, $period);
    }

    private static function timeToMinutes(string $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    /**
     * @return array{bg: string, border: string, text: string, accent: string}
     */
    private static function paletteForSeed(int $seed): array
    {
        $index = abs($seed) % count(self::CARD_PALETTES);

        return self::CARD_PALETTES[$index];
    }

    public static function employeeDisplayName(Employee $employee): string
    {
        $name = trim((string) ($employee->full_legal_name ?: ''));

        return $name !== '' ? $name : (string) $employee->email;
    }

    public static function employeeJobTitle(Employee $employee): string
    {
        $fromCatalog = trim((string) ($employee->assignedJobTitle?->name ?? ''));

        if ($fromCatalog !== '') {
            return $fromCatalog;
        }

        $legacy = trim((string) ($employee->job_title ?? ''));

        return $legacy !== '' ? $legacy : '—';
    }

    public static function employeeInitials(Employee $employee): string
    {
        $name = self::employeeDisplayName($employee);
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr($parts[count($parts) - 1], 0, 1));
        }

        if ($name !== '') {
            return strtoupper(substr($name, 0, 2));
        }

        return '??';
    }
}
