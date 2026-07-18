<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLeaveRecord;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use App\Models\TimesheetApproval;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class AdminDashboardNotifications
{
    private const SOON_EXPIRE_DAYS = 30;

    private const UPCOMING_RENEWAL_DAYS = 90;

    private const RECENTLY_JOINED_DAYS = 30;

    private const UPCOMING_SHIFT_DAYS = 7;

    private const BIRTHDAY_LOOKAHEAD_DAYS = 7;

    private const LATE_GRACE_MINUTES = 15;

    private const MAX_ITEMS_PER_SECTION = 12;

    /**
     * @return array{
     *     sections: list<array{
     *         key: string,
     *         title: string,
     *         items: list<array{message: string, url: string|null, severity: string, sort_at: int}>,
     *         total_count: int,
     *         unavailable: bool,
     *         unavailable_reason: string|null,
     *     }>,
     *     alert_count: int,
     * }
     */
    public static function collect(Company $company): array
    {
        $conn = $company->tenant_connection;
        $now = DisplayTimezone::now();
        $today = $now->toDateString();
        $tz = DisplayTimezone::name();

        $employees = Employee::on($conn)
            ->where('employment_status', 'active')
            ->orderBy('full_legal_name')
            ->orderBy('email')
            ->get();

        $allEmployees = Employee::on($conn)->orderBy('full_legal_name')->get();

        $scheduleFrom = $now->copy()->subDay()->toDateString();
        $scheduleTo = $now->copy()->addDays(self::UPCOMING_SHIFT_DAYS)->toDateString();

        $scheduleShifts = EmployeeScheduleShift::on($conn)
            ->where('entry_type', EmployeeScheduleShift::TYPE_SHIFT)
            ->whereBetween('scheduled_date', [$scheduleFrom, $scheduleTo])
            ->whereHas('employee', static fn ($query) => $query->where('employment_status', 'active'))
            ->with('employee')
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get();

        $timeOffEntries = EmployeeScheduleShift::on($conn)
            ->where('entry_type', EmployeeScheduleShift::TYPE_TIME_OFF)
            ->whereBetween('scheduled_date', [$scheduleFrom, $scheduleTo])
            ->with('employee')
            ->get();

        $clockEntries = TimeClockEntry::on($conn)
            ->where('clocked_at', '>=', $now->copy()->subDays(14)->startOfDay())
            ->with('employee')
            ->orderBy('clocked_at')
            ->get();

        $latestClockEntries = $employees->isEmpty()
            ? collect()
            : TimeClockEntry::on($conn)
                ->whereIn('employee_id', $employees->pluck('id'))
                ->orderByDesc('clocked_at')
                ->orderByDesc('id')
                ->get()
                ->unique('employee_id')
                ->values();

        $leaveRecords = EmployeeLeaveRecord::on($conn)
            ->where('status', EmployeeLeaveRecord::STATUS_PENDING)
            ->with('employee')
            ->orderBy('leave_date')
            ->get();

        $leaveOnSchedule = EmployeeLeaveRecord::on($conn)
            ->whereBetween('leave_date', [$scheduleFrom, $scheduleTo])
            ->where('status', '!=', EmployeeLeaveRecord::STATUS_CANCELLED)
            ->with('employee')
            ->get();

        $excusedAbsences = self::buildExcusedAbsenceKeys($timeOffEntries, $leaveOnSchedule);

        $sickToday = EmployeeLeaveRecord::on($conn)
            ->where('leave_type', EmployeeLeaveRecord::TYPE_SICK)
            ->whereDate('leave_date', $today)
            ->where('status', '!=', EmployeeLeaveRecord::STATUS_CANCELLED)
            ->with('employee')
            ->get();

        $timesheetApprovals = TimesheetApproval::on($conn)->get();

        $employeesWithClock = $employees->load([
            'timeClockEntries' => static fn ($q) => $q
                ->where('clocked_at', '>=', $now->copy()->subWeeks(8)->startOfDay())
                ->orderBy('clocked_at'),
        ]);

        $employeeUrl = static fn (Employee $employee): string => route('admin.registrations.show', [
            'companySlug' => $company->slug,
            'publicId' => $employee->public_id,
        ]);

        $name = static fn (Employee $employee): string => trim((string) ($employee->full_legal_name ?: $employee->email ?: 'Employee'));

        $sections = [];

        $sections[] = self::section(
            'expired_documents',
            'Expired or soon-to-expire documents',
            self::documentExpiryItems($employees, $employeeUrl, $name, $now, true),
        );

        $sections[] = self::section(
            'recently_joined',
            'Employees who recently joined',
            self::recentlyJoinedItems($employees, $employeeUrl, $name, $now),
        );

        $sections[] = self::section(
            'birthdays',
            'Employee birthdays',
            self::birthdayItems($employees, $employeeUrl, $name, $now),
        );

        $sections[] = self::section(
            'upcoming_shifts',
            'Upcoming shift reminders',
            self::upcomingShiftItems($scheduleShifts, $employeeUrl, $name, $now),
        );

        $sections[] = self::section(
            'missing_clock_in',
            'Employees who have not clocked in for their scheduled shift',
            self::missingClockInItems($scheduleShifts, $clockEntries, $excusedAbsences, $employeeUrl, $name, $now, $tz),
        );

        $sections[] = self::section(
            'no_shows_sick',
            'No-shows and sick call-outs',
            self::noShowAndSickItems(
                $scheduleShifts,
                $clockEntries,
                $timeOffEntries,
                $sickToday,
                $excusedAbsences,
                $employeeUrl,
                $name,
                $now,
                $tz,
            ),
        );

        $sections[] = self::section(
            'late_early_punches',
            'Late clock-ins or early clock-outs',
            self::lateEarlyItems($scheduleShifts, $clockEntries, $excusedAbsences, $employeeUrl, $name, $now, $tz),
        );

        $sections[] = self::section(
            'overtime',
            'Overtime alerts',
            self::overtimeItems($employeesWithClock, $employeeUrl, $name, $now, $tz),
        );

        $sections[] = self::section(
            'unapproved_timesheets',
            'Unapproved timesheets',
            self::unapprovedTimesheetItems($employeesWithClock, $timesheetApprovals, $employeeUrl, $name),
        );

        $sections[] = self::section(
            'pending_leave',
            'Pending leave requests',
            self::pendingLeaveItems($leaveRecords, $employeeUrl, $name),
        );

        $sections[] = self::section(
            'upcoming_renewals',
            'Upcoming visa, licence, or certification renewals',
            self::documentExpiryItems($employees, $employeeUrl, $name, $now, false),
        );

        $sections[] = self::section(
            'incomplete_onboarding',
            'Employees with incomplete onboarding requirements',
            self::incompleteOnboardingItems($allEmployees, $employeeUrl, $name),
        );

        $sections[] = self::section(
            'incidents',
            'New incident or hazard reports',
            [],
            true,
            'Incident reporting is not set up yet.',
        );

        $sections[] = self::section(
            'messages',
            'New messages from employees',
            [],
            true,
            'Employee messaging is not set up yet.',
        );

        $sections[] = self::section(
            'training_renewals',
            'Upcoming training or compliance renewals',
            self::trainingRenewalItems($employees, $employeeUrl, $name, $now),
        );

        $sections[] = self::section(
            'open_shifts',
            'Open shifts that still need to be assigned',
            self::openShiftItems($employees, $employeeUrl, $name),
        );

        $sections[] = self::section(
            'schedule_conflicts',
            'Schedule conflicts or overlapping shifts',
            self::scheduleConflictItems($scheduleShifts, $employeeUrl, $name),
        );

        $sections[] = self::section(
            'clocked_in',
            'Staff currently clocked in',
            self::clockedInItems($employees, $latestClockEntries, $employeeUrl, $name, $now),
        );

        $alertCount = 0;
        $informationalKeys = ['clocked_in', 'birthdays', 'recently_joined', 'upcoming_shifts'];
        foreach ($sections as $section) {
            if ($section['unavailable'] || in_array($section['key'], $informationalKeys, true)) {
                continue;
            }
            $alertCount += $section['total_count'];
        }

        return [
            'sections' => $sections,
            'alert_count' => $alertCount,
        ];
    }

    /**
     * @param  list<array{message: string, url: string|null, severity: string, sort_at: int}>  $items
     * @return array{
     *     key: string,
     *     title: string,
     *     items: list<array{message: string, url: string|null, severity: string, sort_at: int}>,
     *     total_count: int,
     *     unavailable: bool,
     *     unavailable_reason: string|null,
     * }
     */
    private static function section(
        string $key,
        string $title,
        array $items,
        bool $unavailable = false,
        ?string $unavailableReason = null,
    ): array {
        usort($items, static fn (array $a, array $b): int => $a['sort_at'] <=> $b['sort_at']);

        $totalCount = count($items);
        $visible = array_slice($items, 0, self::MAX_ITEMS_PER_SECTION);

        return [
            'key' => $key,
            'title' => $title,
            'items' => $visible,
            'total_count' => $totalCount,
            'unavailable' => $unavailable,
            'unavailable_reason' => $unavailableReason,
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  callable(Employee): string  $employeeUrl
     * @param  callable(Employee): string  $name
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function documentExpiryItems(
        Collection $employees,
        callable $employeeUrl,
        callable $name,
        CarbonInterface $now,
        bool $expiredOrSoon,
    ): array {
        $items = [];
        $today = $now->copy()->startOfDay();
        $seen = [];

        foreach ($employees as $employee) {
            foreach (self::employeeDocumentExpiries($employee) as $doc) {
                $expiryDate = $doc['date'];
                if ($expiryDate === null) {
                    continue;
                }

                $dedupeKey = (int) $employee->id.'|'.$doc['label'].'|'.$expiryDate->toDateString();
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;

                $daysUntil = (int) $today->diffInDays($expiryDate, false);
                $isExpired = $daysUntil < 0;
                $isSoon = $daysUntil >= 0 && $daysUntil <= self::SOON_EXPIRE_DAYS;
                $isUpcoming = $daysUntil > self::SOON_EXPIRE_DAYS && $daysUntil <= self::UPCOMING_RENEWAL_DAYS;

                if ($expiredOrSoon && ! $isExpired && ! $isSoon) {
                    continue;
                }
                if (! $expiredOrSoon && ! $isUpcoming) {
                    continue;
                }

                $label = $doc['label'];
                if ($isExpired) {
                    $message = sprintf('%s — %s expired %s', $name($employee), $label, $expiryDate->format('j M Y'));
                    $severity = 'urgent';
                } elseif ($isSoon) {
                    $message = sprintf('%s — %s expires %s (%d days)', $name($employee), $label, $expiryDate->format('j M Y'), $daysUntil);
                    $severity = 'warning';
                } else {
                    $message = sprintf('%s — %s renewal due %s (%d days)', $name($employee), $label, $expiryDate->format('j M Y'), $daysUntil);
                    $severity = 'info';
                }

                $items[] = [
                    'message' => $message,
                    'url' => $employeeUrl($employee),
                    'severity' => $severity,
                    'sort_at' => $expiryDate->getTimestamp(),
                ];
            }
        }

        return $items;
    }

    /**
     * @return list<array{label: string, date: CarbonInterface|null}>
     */
    private static function employeeDocumentExpiries(Employee $employee): array
    {
        $docs = [];

        $columnLabels = [
            'visa_expiry' => 'Visa',
            'police_check_expiry' => 'Police check',
            'fit_to_work_expiry' => 'Fit-to-work clearance',
            'vehicle_expiry' => 'Vehicle registration',
        ];

        foreach ($columnLabels as $column => $label) {
            $iso = RegistrationDisplay::toNullableIsoDate($employee->{$column} ?? null);
            $docs[] = ['label' => $label, 'date' => self::parseIsoDate($iso)];
        }

        foreach (['licences_json', 'id_documents_json', 'insurances_json'] as $jsonColumn) {
            $rows = $employee->{$jsonColumn} ?? null;
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $label = self::documentRowTitle($row);
                if ($label === '') {
                    $label = match ($jsonColumn) {
                        'licences_json' => 'Licence',
                        'id_documents_json' => 'ID document',
                        default => 'Insurance',
                    };
                }
                $iso = RegistrationDisplay::toNullableIsoDate(RegistrationDisplay::expiryRawFromDocumentRow($row));
                $docs[] = ['label' => $label, 'date' => self::parseIsoDate($iso)];
            }
        }

        return $docs;
    }

    private static function parseIsoDate(?string $iso): ?CarbonInterface
    {
        if ($iso === null || $iso === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $iso, DisplayTimezone::name())->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function recentlyJoinedItems(Collection $employees, callable $employeeUrl, callable $name, CarbonInterface $now): array
    {
        $items = [];
        $cutoff = $now->copy()->subDays(self::RECENTLY_JOINED_DAYS)->startOfDay();

        foreach ($employees as $employee) {
            $joinedAt = $employee->hired_at ?? $employee->created_at;
            if ($joinedAt === null || $joinedAt->lt($cutoff)) {
                continue;
            }

            $items[] = [
                'message' => sprintf('%s joined on %s', $name($employee), DisplayTimezone::formatDate($joinedAt)),
                'url' => $employeeUrl($employee),
                'severity' => 'info',
                'sort_at' => -$joinedAt->getTimestamp(),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function birthdayItems(Collection $employees, callable $employeeUrl, callable $name, CarbonInterface $now): array
    {
        $items = [];

        foreach ($employees as $employee) {
            $iso = RegistrationDisplay::toNullableIsoDate(
                RegistrationDisplay::employeeRawDateValue($employee, 'date_of_birth', ['dateOfBirth', 'date_of_birth']),
            );
            if ($iso === null) {
                continue;
            }

            try {
                $dob = Carbon::createFromFormat('!Y-m-d', $iso, DisplayTimezone::name());
            } catch (\Throwable) {
                continue;
            }

            $birthdayThisYear = $dob->copy()->year($now->year)->startOfDay();
            if ($birthdayThisYear->lt($now->copy()->startOfDay())) {
                $birthdayThisYear->addYear();
            }

            $daysUntil = (int) $now->copy()->startOfDay()->diffInDays($birthdayThisYear, false);
            if ($daysUntil < 0 || $daysUntil > self::BIRTHDAY_LOOKAHEAD_DAYS) {
                continue;
            }

            $when = $daysUntil === 0 ? 'today' : ($daysUntil === 1 ? 'tomorrow' : 'on '.$birthdayThisYear->format('j M'));

            $items[] = [
                'message' => sprintf('%s — birthday %s', $name($employee), $when),
                'url' => $employeeUrl($employee),
                'severity' => 'info',
                'sort_at' => $birthdayThisYear->getTimestamp(),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, EmployeeScheduleShift>  $scheduleShifts
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function upcomingShiftItems(Collection $scheduleShifts, callable $employeeUrl, callable $name, CarbonInterface $now): array
    {
        $items = [];
        $limit = $now->copy()->addDays(self::UPCOMING_SHIFT_DAYS)->endOfDay();

        foreach ($scheduleShifts as $shift) {
            $employee = $shift->employee;
            if (! $employee instanceof Employee) {
                continue;
            }

            $shiftStart = self::shiftDateTime($shift);
            if ($shiftStart->lt($now) || $shiftStart->gt($limit)) {
                continue;
            }

            $timeLabel = self::formatShiftTime($shift);
            $items[] = [
                'message' => sprintf(
                    '%s — shift on %s%s',
                    $name($employee),
                    DisplayTimezone::formatDate($shift->scheduled_date),
                    $timeLabel !== '' ? ' · '.$timeLabel : '',
                ),
                'url' => route('admin.employees.weekly-schedule'),
                'severity' => 'info',
                'sort_at' => $shiftStart->getTimestamp(),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, EmployeeScheduleShift>  $scheduleShifts
     * @param  Collection<int, TimeClockEntry>  $clockEntries
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function missingClockInItems(
        Collection $scheduleShifts,
        Collection $clockEntries,
        array $excusedAbsences,
        callable $employeeUrl,
        callable $name,
        CarbonInterface $now,
        string $tz,
    ): array {
        $items = [];
        $today = $now->toDateString();

        foreach ($scheduleShifts as $shift) {
            if ($shift->scheduled_date?->toDateString() !== $today) {
                continue;
            }

            $employee = $shift->employee;
            if (! $employee instanceof Employee) {
                continue;
            }

            if (self::isExcusedAbsence((int) $employee->id, $today, $excusedAbsences)) {
                continue;
            }

            $shiftStart = self::shiftDateTime($shift);
            if ($now->lt($shiftStart->copy()->addMinutes(self::LATE_GRACE_MINUTES))) {
                continue;
            }

            if (self::hasClockInOnDate($clockEntries, (int) $employee->id, $today, $tz)) {
                continue;
            }

            $items[] = [
                'message' => sprintf(
                    '%s — scheduled %s, not clocked in yet',
                    $name($employee),
                    self::formatShiftTime($shift) ?: 'today',
                ),
                'url' => route('admin.employees.time-clock'),
                'severity' => 'warning',
                'sort_at' => $shiftStart->getTimestamp(),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, EmployeeScheduleShift>  $scheduleShifts
     * @param  Collection<int, TimeClockEntry>  $clockEntries
     * @param  Collection<int, EmployeeScheduleShift>  $timeOffEntries
     * @param  Collection<int, EmployeeLeaveRecord>  $sickToday
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function noShowAndSickItems(
        Collection $scheduleShifts,
        Collection $clockEntries,
        Collection $timeOffEntries,
        Collection $sickToday,
        array $excusedAbsences,
        callable $employeeUrl,
        callable $name,
        CarbonInterface $now,
        string $tz,
    ): array {
        $items = [];

        foreach ($sickToday as $leave) {
            $employee = $leave->employee;
            if (! $employee instanceof Employee) {
                continue;
            }
            $items[] = [
                'message' => sprintf('%s — sick leave recorded for today', $name($employee)),
                'url' => $employeeUrl($employee),
                'severity' => 'info',
                'sort_at' => $now->getTimestamp(),
            ];
        }

        foreach ($timeOffEntries as $entry) {
            $employee = $entry->employee;
            if (! $employee instanceof Employee) {
                continue;
            }
            $notes = mb_strtolower((string) ($entry->notes ?? ''));
            if (! str_contains($notes, 'sick') && ! str_contains($notes, 'call')) {
                continue;
            }
            $items[] = [
                'message' => sprintf(
                    '%s — time off on %s%s',
                    $name($employee),
                    DisplayTimezone::formatDate($entry->scheduled_date),
                    $entry->notes ? ' · '.$entry->notes : '',
                ),
                'url' => route('admin.employees.weekly-schedule'),
                'severity' => 'info',
                'sort_at' => $entry->scheduled_date?->getTimestamp() ?? $now->getTimestamp(),
            ];
        }

        foreach ($scheduleShifts as $shift) {
            $employee = $shift->employee;
            if (! $employee instanceof Employee) {
                continue;
            }

            $shiftEnd = self::shiftEndDateTime($shift);
            if ($now->lt($shiftEnd)) {
                continue;
            }

            $date = $shift->scheduled_date?->toDateString();
            if ($date === null) {
                continue;
            }

            if (self::isExcusedAbsence((int) $employee->id, $date, $excusedAbsences)) {
                continue;
            }

            if (self::hasClockInOnDate($clockEntries, (int) $employee->id, $date, $tz)) {
                continue;
            }

            $items[] = [
                'message' => sprintf(
                    '%s — no-show for shift on %s (%s)',
                    $name($employee),
                    DisplayTimezone::formatDate($shift->scheduled_date),
                    self::formatShiftTime($shift) ?: 'scheduled shift',
                ),
                'url' => route('admin.employees.time-clock'),
                'severity' => 'urgent',
                'sort_at' => $shiftEnd->getTimestamp(),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, EmployeeScheduleShift>  $scheduleShifts
     * @param  Collection<int, TimeClockEntry>  $clockEntries
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function lateEarlyItems(
        Collection $scheduleShifts,
        Collection $clockEntries,
        array $excusedAbsences,
        callable $employeeUrl,
        callable $name,
        CarbonInterface $now,
        string $tz,
    ): array {
        $items = [];
        $lookback = $now->copy()->subDay()->toDateString();

        foreach ($scheduleShifts as $shift) {
            $date = $shift->scheduled_date?->toDateString();
            if ($date === null || $date < $lookback) {
                continue;
            }

            $employee = $shift->employee;
            if (! $employee instanceof Employee) {
                continue;
            }

            if (self::isExcusedAbsence((int) $employee->id, $date, $excusedAbsences)) {
                continue;
            }

            $shiftStart = self::shiftDateTime($shift);
            $shiftEnd = self::shiftEndDateTime($shift);

            $dayEntries = $clockEntries->filter(static function (TimeClockEntry $entry) use ($employee, $date, $tz): bool {
                if ((int) $entry->employee_id !== (int) $employee->id || $entry->clocked_at === null) {
                    return false;
                }

                return $entry->clocked_at->copy()->timezone($tz)->toDateString() === $date;
            })->values();

            $firstIn = $dayEntries->first(static fn (TimeClockEntry $e): bool => $e->event_type === TimeClockEntry::EVENT_CLOCK_IN);
            if ($firstIn?->clocked_at !== null) {
                $lateMinutes = (int) $shiftStart->diffInMinutes($firstIn->clocked_at, false);
                if ($lateMinutes > self::LATE_GRACE_MINUTES) {
                    $items[] = [
                        'message' => sprintf(
                            '%s — clocked in %d min late on %s (scheduled %s)',
                            $name($employee),
                            $lateMinutes,
                            DisplayTimezone::formatDate($shift->scheduled_date),
                            self::formatShiftTime($shift),
                        ),
                        'url' => route('admin.employees.time-clock'),
                        'severity' => 'warning',
                        'sort_at' => $firstIn->clocked_at->getTimestamp(),
                    ];
                }
            }

            if ($shiftEnd === null) {
                continue;
            }

            $lastOut = $dayEntries->reverse()->first(static fn (TimeClockEntry $e): bool => $e->event_type === TimeClockEntry::EVENT_CLOCK_OUT);
            if ($lastOut?->clocked_at !== null) {
                $earlyMinutes = (int) $lastOut->clocked_at->diffInMinutes($shiftEnd, false);
                if ($earlyMinutes > self::LATE_GRACE_MINUTES) {
                    $items[] = [
                        'message' => sprintf(
                            '%s — clocked out %d min early on %s (scheduled until %s)',
                            $name($employee),
                            $earlyMinutes,
                            DisplayTimezone::formatDate($shift->scheduled_date),
                            self::formatShiftEndTime($shift),
                        ),
                        'url' => route('admin.employees.time-clock'),
                        'severity' => 'warning',
                        'sort_at' => $lastOut->clocked_at->getTimestamp(),
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function overtimeItems(Collection $employees, callable $employeeUrl, callable $name, CarbonInterface $now, string $tz): array
    {
        $items = [];
        $weekStart = $now->copy()->timezone($tz)->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        foreach ($employees as $employee) {
            $entries = $employee->timeClockEntries ?? collect();
            $weekEntries = $entries->filter(static function (TimeClockEntry $entry) use ($weekStart, $weekEnd, $tz): bool {
                if ($entry->clocked_at === null) {
                    return false;
                }
                $at = $entry->clocked_at->copy()->timezone($tz);

                return $at->between($weekStart, $weekEnd);
            });

            $summary = AdminTimeClockDisplay::summarizeWorkSessions($weekEntries);
            $hoursWorked = $summary['total_seconds'] / 3600;
            $threshold = self::weeklyHoursThreshold($employee, (string) ($employee->employment_type ?? ''));

            if ($hoursWorked <= $threshold) {
                continue;
            }

            $overtimeHours = round($hoursWorked - $threshold, 1);
            $items[] = [
                'message' => sprintf(
                    '%s — %.1f h overtime this week (%.1f h worked, %.1f h threshold)',
                    $name($employee),
                    $overtimeHours,
                    round($hoursWorked, 1),
                    $threshold,
                ),
                'url' => route('admin.employees.time-clock'),
                'severity' => 'warning',
                'sort_at' => $now->getTimestamp(),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, TimesheetApproval>  $approvals
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function unapprovedTimesheetItems(Collection $employees, Collection $approvals, callable $employeeUrl, callable $name): array
    {
        $rows = AdminTimesheetApproval::buildRows($employees, $approvals, TimesheetApproval::STATUS_PENDING);
        $items = [];

        foreach ($rows as $row) {
            $employee = $row['employee'];
            $items[] = [
                'message' => sprintf(
                    '%s — timesheet pending approval for %s (%s)',
                    $name($employee),
                    $row['day_label'],
                    $row['total_hours_label'],
                ),
                'url' => route('admin.employees.time-clock', ['timesheet_status' => 'pending']),
                'severity' => 'warning',
                'sort_at' => -strtotime($row['work_date']),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, EmployeeLeaveRecord>  $leaveRecords
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function pendingLeaveItems(Collection $leaveRecords, callable $employeeUrl, callable $name): array
    {
        $items = [];

        foreach ($leaveRecords as $leave) {
            $employee = $leave->employee;
            if (! $employee instanceof Employee) {
                continue;
            }

            $typeLabel = match ($leave->leave_type) {
                EmployeeLeaveRecord::TYPE_SICK => 'sick leave',
                EmployeeLeaveRecord::TYPE_ANNUAL => 'annual leave',
                default => strtolower(str_replace('_', ' ', (string) $leave->leave_type)).' leave',
            };
            $items[] = [
                'message' => sprintf(
                    '%s — pending %s on %s (%.1f h)',
                    $name($employee),
                    $typeLabel,
                    DisplayTimezone::formatDate($leave->leave_date),
                    (float) $leave->hours,
                ),
                'url' => $employeeUrl($employee),
                'severity' => 'warning',
                'sort_at' => $leave->leave_date?->getTimestamp() ?? 0,
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function trainingRenewalItems(Collection $employees, callable $employeeUrl, callable $name, CarbonInterface $now): array
    {
        $items = [];
        $today = $now->copy()->startOfDay();
        $trainingLabels = [
            'police_check_expiry' => 'Police check',
            'fit_to_work_expiry' => 'Fit-to-work clearance',
        ];

        foreach ($employees as $employee) {
            foreach ($trainingLabels as $column => $label) {
                $iso = RegistrationDisplay::toNullableIsoDate($employee->{$column} ?? null);
                $expiryDate = self::parseIsoDate($iso);
                if ($expiryDate === null) {
                    continue;
                }

                $daysUntil = (int) $today->diffInDays($expiryDate, false);
                if ($daysUntil < 0 || $daysUntil > self::UPCOMING_RENEWAL_DAYS) {
                    continue;
                }

                $items[] = [
                    'message' => sprintf(
                        '%s — %s %s on %s',
                        $name($employee),
                        $label,
                        $daysUntil === 0 ? 'expires today' : 'renewal due',
                        $expiryDate->format('j M Y'),
                    ),
                    'url' => $employeeUrl($employee),
                    'severity' => $daysUntil <= self::SOON_EXPIRE_DAYS ? 'warning' : 'info',
                    'sort_at' => $expiryDate->getTimestamp(),
                ];
            }

            if (! is_array($employee->licences_json)) {
                continue;
            }

            foreach ($employee->licences_json as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $type = self::documentRowTitle($row);
                if ($type === '') {
                    continue;
                }
                $lower = mb_strtolower($type);
                if (! str_contains($lower, 'first aid') && ! str_contains($lower, 'white card') && ! str_contains($lower, 'rsa') && ! str_contains($lower, 'training')) {
                    continue;
                }
                $iso = RegistrationDisplay::toNullableIsoDate(RegistrationDisplay::expiryRawFromDocumentRow($row));
                $expiryDate = self::parseIsoDate($iso);
                if ($expiryDate === null) {
                    continue;
                }
                $daysUntil = (int) $today->diffInDays($expiryDate, false);
                if ($daysUntil < 0 || $daysUntil > self::UPCOMING_RENEWAL_DAYS) {
                    continue;
                }
                $items[] = [
                    'message' => sprintf('%s — %s training/compliance renewal due %s', $name($employee), $type, $expiryDate->format('j M Y')),
                    'url' => $employeeUrl($employee),
                    'severity' => $daysUntil <= self::SOON_EXPIRE_DAYS ? 'warning' : 'info',
                    'sort_at' => $expiryDate->getTimestamp(),
                ];
            }
        }

        return $items;
    }

    /**
     * @param  Collection<int, Employee>  $allEmployees
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function incompleteOnboardingItems(Collection $allEmployees, callable $employeeUrl, callable $name): array
    {
        $items = [];

        foreach ($allEmployees as $employee) {
            if ($employee->employment_status === 'pending') {
                $items[] = [
                    'message' => sprintf('%s — registration pending approval', $name($employee)),
                    'url' => $employeeUrl($employee),
                    'severity' => 'warning',
                    'sort_at' => $employee->created_at?->getTimestamp() ?? 0,
                ];

                continue;
            }

            if ($employee->employment_status !== 'active') {
                continue;
            }

            $gaps = [];
            if ($employee->department_id === null) {
                $gaps[] = 'department';
            }
            if ($employee->work_location_id === null) {
                $gaps[] = 'work location';
            }
            if ($employee->shift_id === null) {
                $gaps[] = 'shift assignment';
            }
            if (! (bool) ($employee->police_check_uploaded ?? false)) {
                $gaps[] = 'police check upload';
            }
            if (! (bool) ($employee->fit_to_work_uploaded ?? false)) {
                $gaps[] = 'fit-to-work upload';
            }

            if ($gaps === []) {
                continue;
            }

            $items[] = [
                'message' => sprintf('%s — missing %s', $name($employee), implode(', ', $gaps)),
                'url' => $employeeUrl($employee),
                'severity' => 'warning',
                'sort_at' => $employee->updated_at?->getTimestamp() ?? 0,
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function openShiftItems(Collection $employees, callable $employeeUrl, callable $name): array
    {
        $items = [];

        $employees->loadMissing('assignmentShifts');

        foreach ($employees as $employee) {
            if ($employee->work_location_id === null || $employee->department_id === null) {
                $items[] = [
                    'message' => sprintf('%s — work assignment incomplete (needs department and location)', $name($employee)),
                    'url' => route('admin.employees.assignments'),
                    'severity' => 'warning',
                    'sort_at' => $employee->updated_at?->getTimestamp() ?? 0,
                ];

                continue;
            }

            if ($employee->shift_id === null && $employee->assignmentShifts->isEmpty()) {
                $items[] = [
                    'message' => sprintf('%s — no shift pattern assigned yet', $name($employee)),
                    'url' => route('admin.employees.assignments'),
                    'severity' => 'warning',
                    'sort_at' => $employee->updated_at?->getTimestamp() ?? 0,
                ];
            }
        }

        return $items;
    }

    /**
     * @param  Collection<int, EmployeeScheduleShift>  $scheduleShifts
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function scheduleConflictItems(Collection $scheduleShifts, callable $employeeUrl, callable $name): array
    {
        $items = [];
        $grouped = $scheduleShifts->groupBy(static fn (EmployeeScheduleShift $s): string => (int) $s->employee_id.'|'.$s->scheduled_date?->toDateString());

        foreach ($grouped as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $sorted = $group->sortBy(static fn (EmployeeScheduleShift $s): int => self::shiftDateTime($s)->getTimestamp())->values();

            for ($i = 0; $i < $sorted->count() - 1; $i++) {
                $a = $sorted[$i];
                $b = $sorted[$i + 1];
                $aStart = self::shiftDateTime($a);
                $aEnd = self::shiftEndDateTime($a);
                $bStart = self::shiftDateTime($b);
                if ($bStart->lt($aEnd)) {
                    $employee = $a->employee;
                    if (! $employee instanceof Employee) {
                        continue;
                    }
                    $items[] = [
                        'message' => sprintf(
                            '%s — overlapping shifts on %s (%s and %s)',
                            $name($employee),
                            DisplayTimezone::formatDate($a->scheduled_date),
                            self::formatShiftTime($a),
                            self::formatShiftTime($b),
                        ),
                        'url' => route('admin.employees.weekly-schedule'),
                        'severity' => 'urgent',
                        'sort_at' => $aStart->getTimestamp(),
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, TimeClockEntry>  $clockEntries
     * @return list<array{message: string, url: string|null, severity: string, sort_at: int}>
     */
    private static function clockedInItems(Collection $employees, Collection $latestClockEntries, callable $employeeUrl, callable $name, CarbonInterface $now): array
    {
        $items = [];
        $latestByEmployee = $latestClockEntries->keyBy(static fn (TimeClockEntry $entry): int => (int) $entry->employee_id);

        foreach ($employees as $employee) {
            $last = $latestByEmployee->get((int) $employee->id);
            if (! $last instanceof TimeClockEntry || $last->event_type !== TimeClockEntry::EVENT_CLOCK_IN || $last->clocked_at === null) {
                continue;
            }

            $items[] = [
                'message' => sprintf(
                    '%s — clocked in since %s',
                    $name($employee),
                    DisplayTimezone::format($last->clocked_at, 'g:i A'),
                ),
                'url' => route('admin.employees.time-clock'),
                'severity' => 'success',
                'sort_at' => -$last->clocked_at->getTimestamp(),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, TimeClockEntry>  $clockEntries
     */
    private static function hasClockInOnDate(Collection $clockEntries, int $employeeId, string $date, string $tz): bool
    {
        return $clockEntries->contains(static function (TimeClockEntry $entry) use ($employeeId, $date, $tz): bool {
            if ((int) $entry->employee_id !== $employeeId || $entry->event_type !== TimeClockEntry::EVENT_CLOCK_IN || $entry->clocked_at === null) {
                return false;
            }

            return $entry->clocked_at->copy()->timezone($tz)->toDateString() === $date;
        });
    }

    /**
     * @param  Collection<int, EmployeeScheduleShift>  $timeOffEntries
     * @param  Collection<int, EmployeeLeaveRecord>  $leaveRecords
     * @return array<string, true>
     */
    private static function buildExcusedAbsenceKeys(Collection $timeOffEntries, Collection $leaveRecords): array
    {
        $keys = [];

        foreach ($timeOffEntries as $entry) {
            $date = $entry->scheduled_date?->toDateString();
            if ($date === null) {
                continue;
            }
            $keys[(int) $entry->employee_id.'|'.$date] = true;
        }

        foreach ($leaveRecords as $leave) {
            $date = $leave->leave_date?->toDateString();
            if ($date === null) {
                continue;
            }
            $keys[(int) $leave->employee_id.'|'.$date] = true;
        }

        return $keys;
    }

    /**
     * @param  array<string, true>  $excusedAbsences
     */
    private static function isExcusedAbsence(int $employeeId, string $date, array $excusedAbsences): bool
    {
        return isset($excusedAbsences[$employeeId.'|'.$date]);
    }

    private static function shiftDateTime(EmployeeScheduleShift $shift): CarbonInterface
    {
        $date = $shift->scheduled_date?->toDateString() ?? DisplayTimezone::now()->toDateString();
        $time = self::storedTimeToHm($shift->start_time);

        return Carbon::parse($date.' '.$time, DisplayTimezone::name());
    }

    private static function shiftEndDateTime(EmployeeScheduleShift $shift): CarbonInterface
    {
        $date = $shift->scheduled_date?->toDateString() ?? DisplayTimezone::now()->toDateString();
        $endTime = self::storedTimeToHm($shift->end_time, '17:00');
        $start = self::shiftDateTime($shift);
        $end = Carbon::parse($date.' '.$endTime, DisplayTimezone::name());

        if ($end->lte($start)) {
            $end->addDay();
        }

        return $end;
    }

    private static function storedTimeToHm(mixed $value, string $default = '09:00'): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return $default;
    }

    private static function formatShiftTime(EmployeeScheduleShift $shift): string
    {
        $start = self::storedTimeToHm($shift->start_time);
        $end = self::storedTimeToHm($shift->end_time, '17:00');

        return $start.'–'.$end;
    }

    private static function formatShiftEndTime(EmployeeScheduleShift $shift): string
    {
        return self::storedTimeToHm($shift->end_time, '17:00');
    }

    private static function weeklyHoursThreshold(Employee $employee, string $employmentType): float
    {
        if ($employmentType === 'part_time') {
            $contracted = is_numeric($employee->hours_per_week ?? null)
                ? max(0, (float) $employee->hours_per_week)
                : 0.0;

            return $contracted > 0 ? $contracted : (float) config('payroll.full_time_weekly_hours', 38);
        }

        return (float) config('payroll.full_time_weekly_hours', 38);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function documentRowTitle(array $row): string
    {
        foreach (['documentType', 'document_type', 'idType', 'id_type', 'type', 'name', 'title', 'label'] as $key) {
            if (! empty($row[$key]) && is_scalar($row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }
}
