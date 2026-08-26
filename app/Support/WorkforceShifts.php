<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeAssignmentShift;
use App\Models\EmployeeScheduleShift;
use App\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class WorkforceShifts
{
    /**
     * @return array<int, string>
     */
    public static function allowedDays(): array
    {
        return ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'shift_name' => ['required', 'string', 'max:160'],
            'shift_start_time' => ['required', 'date_format:H:i'],
            'shift_end_time' => ['required', 'date_format:H:i'],
            'shift_days' => ['nullable', 'array'],
            'shift_days.*' => ['string', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'shift_breaks' => ['nullable', 'array', 'max:8'],
            'shift_breaks.*.label' => ['nullable', 'string', 'max:80'],
            'shift_breaks.*.minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'shift_breaks.*.paid' => ['nullable'],
            'shift_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @param  mixed  $input
     * @return array<int, string>|null
     */
    public static function normalizeDays(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $allowed = self::allowedDays();
        $days = collect($input)
            ->map(fn ($day) => is_string($day) ? strtolower(trim($day)) : null)
            ->filter(fn ($day) => is_string($day) && in_array($day, $allowed, true))
            ->unique()
            ->values()
            ->all();

        return $days === [] ? null : $days;
    }

    /**
     * @return array{breaks: list<array{label: string, minutes: int, paid: bool}>|null, breaks_summary: string|null}
     */
    public static function breakPayload(Request $request): array
    {
        $breaks = ShiftBreaks::normalize($request->input('shift_breaks'));

        return [
            'breaks' => $breaks === [] ? null : $breaks,
            'breaks_summary' => ShiftBreaks::summaryFrom($breaks),
        ];
    }

    public static function createOnConnection(string $connection, Request $request): Shift
    {
        $data = $request->validate(self::rules());
        $breakPayload = self::breakPayload($request);

        return Shift::on($connection)->create([
            'name' => $data['shift_name'],
            'start_time' => $data['shift_start_time'],
            'end_time' => $data['shift_end_time'],
            'shift_days' => self::normalizeDays($data['shift_days'] ?? null),
            'breaks' => $breakPayload['breaks'],
            'breaks_summary' => $breakPayload['breaks_summary'],
            'notes' => $data['shift_notes'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{shift: Shift, times_changed: bool, schedule_rows_synced: int}
     */
    public static function updateOnConnection(string $connection, Shift $target, Request $request): array
    {
        $data = $request->validate(self::rules());
        $breakPayload = self::breakPayload($request);

        $previousStart = self::formatTime($target->start_time, 'H:i', '');
        $previousEnd = self::formatTime($target->end_time, 'H:i', '');

        $target->forceFill([
            'name' => $data['shift_name'],
            'start_time' => $data['shift_start_time'],
            'end_time' => $data['shift_end_time'],
            'shift_days' => self::normalizeDays($data['shift_days'] ?? null),
            'breaks' => $breakPayload['breaks'],
            'breaks_summary' => $breakPayload['breaks_summary'],
            'notes' => $data['shift_notes'] ?? null,
        ])->save();

        $timesChanged = $previousStart !== $data['shift_start_time']
            || $previousEnd !== $data['shift_end_time'];

        $synced = 0;
        if ($timesChanged) {
            $synced = AdminWeeklySchedule::syncTemplateTimesToSchedule($connection, $target);
        }

        return [
            'shift' => $target->fresh() ?? $target,
            'times_changed' => $timesChanged,
            'schedule_rows_synced' => $synced,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     start_time: string,
     *     end_time: string,
     *     option_label: string,
     *     shift_days: list<string>,
     *     breaks: list<array{label: string, minutes: int, paid: bool}>,
     *     notes: string,
     *     employee_count: int,
     *     schedule_count: int
     * }
     */
    public static function catalogEntry(Shift $shift, int $employeeCount = 0, int $scheduleCount = 0): array
    {
        $option = self::optionPayload($shift);
        $days = is_array($shift->shift_days) ? array_values(array_filter($shift->shift_days, 'is_string')) : [];

        return [
            ...$option,
            'shift_days' => $days,
            'breaks' => ShiftBreaks::normalize($shift->breaks),
            'notes' => trim((string) ($shift->notes ?? '')),
            'employee_count' => max(0, $employeeCount),
            'schedule_count' => max(0, $scheduleCount),
        ];
    }

    /**
     * @param  Collection<int, Shift>  $shifts
     * @return list<array<string, mixed>>
     */
    public static function catalogForConnection(string $connection, Collection $shifts): array
    {
        $ids = $shifts->pluck('id')->filter()->map(static fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $assignmentEmployees = EmployeeAssignmentShift::on($connection)
            ->whereIn('shift_id', $ids)
            ->selectRaw('shift_id, COUNT(DISTINCT employee_id) as employee_count')
            ->groupBy('shift_id')
            ->pluck('employee_count', 'shift_id');

        $legacyEmployees = Employee::on($connection)
            ->whereIn('shift_id', $ids)
            ->selectRaw('shift_id, COUNT(*) as employee_count')
            ->groupBy('shift_id')
            ->pluck('employee_count', 'shift_id');

        $scheduleCounts = EmployeeScheduleShift::on($connection)
            ->whereIn('shift_id', $ids)
            ->where('entry_type', EmployeeScheduleShift::TYPE_SHIFT)
            ->selectRaw('shift_id, COUNT(*) as schedule_count')
            ->groupBy('shift_id')
            ->pluck('schedule_count', 'shift_id');

        return $shifts->map(static function (Shift $shift) use ($assignmentEmployees, $legacyEmployees, $scheduleCounts): array {
            $id = (int) $shift->id;
            $employeeCount = max(
                (int) ($assignmentEmployees[$id] ?? 0),
                (int) ($legacyEmployees[$id] ?? 0),
            );

            return self::catalogEntry(
                $shift,
                $employeeCount,
                (int) ($scheduleCounts[$id] ?? 0),
            );
        })->values()->all();
    }

    /**
     * @return array{id: int, name: string, start_time: string, end_time: string, option_label: string}
     */
    public static function optionPayload(Shift $shift): array
    {
        $start = self::formatTime($shift->start_time, 'H:i', '09:00');
        $end = self::formatTime($shift->end_time, 'H:i', '17:00');
        $startLabel = self::formatTime($shift->start_time, 'g:i A', '—');
        $endLabel = self::formatTime($shift->end_time, 'g:i A', '—');

        return [
            'id' => (int) $shift->id,
            'name' => (string) $shift->name,
            'start_time' => $start,
            'end_time' => $end,
            'option_label' => trim($shift->name.' · '.$startLabel.' – '.$endLabel),
        ];
    }

    private static function formatTime(mixed $value, string $format, string $fallback): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format($format);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->format($format);
            } catch (\Throwable) {
                return $fallback;
            }
        }

        return $fallback;
    }
}
