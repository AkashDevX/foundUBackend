<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeLeaveRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class PayrollLeaveProcessor
{
    /**
     * @param  array<string, float>  $rates
     * @return list<array{rate_type: string, label: string, hours: float, rate: float, amount: float, sort_order: int, leave_record_id: int}>
     */
    public static function buildPayLines(
        Employee $employee,
        Collection $leaveRecords,
        array $rates,
        string $fortnightStart,
        string $fortnightEnd,
        int $sortStart = 100,
    ): array {
        $tz = DisplayTimezone::name();
        $rangeStart = Carbon::parse($fortnightStart, $tz)->startOfDay();
        $rangeEnd = Carbon::parse($fortnightEnd, $tz)->endOfDay();
        $ordinary = PayrollEmployeeRates::ordinaryHourlyRate($rates);

        $lines = [];
        $sort = $sortStart;

        foreach ($leaveRecords as $record) {
            if (! $record instanceof EmployeeLeaveRecord) {
                continue;
            }
            if ($record->status !== EmployeeLeaveRecord::STATUS_PENDING) {
                continue;
            }
            if ($record->leave_date === null) {
                continue;
            }

            $leaveDay = $record->leave_date->copy()->timezone($tz)->startOfDay();
            if ($leaveDay->lt($rangeStart) || $leaveDay->gt($rangeEnd)) {
                continue;
            }

            $hours = (float) $record->hours;
            if ($hours <= 0) {
                continue;
            }

            $hourlyRate = (float) ($record->hourly_rate ?? $ordinary);
            if ($hourlyRate <= 0) {
                $hourlyRate = $ordinary;
            }

            $loading = (float) ($record->loading_percent ?? 0);
            if ($record->leave_type === EmployeeLeaveRecord::TYPE_ANNUAL && $loading <= 0) {
                $loading = (float) config('payroll.annual_leave_loading_percent', 17.5);
            }

            $multiplier = 1 + ($loading / 100);
            $amount = round($hours * $hourlyRate * $multiplier, 2);

            $typeLabel = $record->leave_type === EmployeeLeaveRecord::TYPE_ANNUAL ? 'Annual leave taken' : 'Sick leave taken';
            $loadingNote = $record->leave_type === EmployeeLeaveRecord::TYPE_ANNUAL
                ? ' (incl. '.$loading.'% loading)'
                : '';

            $lines[] = [
                'rate_type' => $record->leave_type === EmployeeLeaveRecord::TYPE_ANNUAL
                    ? PayrollRateTypes::ANNUAL_LEAVE_TAKEN
                    : PayrollRateTypes::SICK_LEAVE_TAKEN,
                'label' => $typeLabel.$loadingNote.' · '.DisplayTimezone::format($record->leave_date, 'M j, Y'),
                'hours' => $hours,
                'rate' => round($hourlyRate * $multiplier, 2),
                'amount' => $amount,
                'sort_order' => $sort++,
                'leave_record_id' => (int) $record->id,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $leaveLines
     */
    public static function applyFinalize(Employee $employee, array $leaveLines, string $connection): void
    {
        foreach ($leaveLines as $line) {
            $recordId = (int) ($line['leave_record_id'] ?? 0);
            if ($recordId <= 0) {
                continue;
            }

            $record = EmployeeLeaveRecord::on($connection)->whereKey($recordId)->first();
            if ($record === null || $record->employee_id !== $employee->id) {
                continue;
            }

            $hours = (float) $line['hours'];
            $amount = (float) $line['amount'];

            if ($record->leave_type === EmployeeLeaveRecord::TYPE_SICK) {
                $employee->sick_leave_balance_hours = max(0, round((float) $employee->sick_leave_balance_hours - $hours, 2));
                $employee->sick_leave_balance_amount = max(0, round((float) $employee->sick_leave_balance_amount - $amount, 2));
            } else {
                $employee->annual_leave_balance_hours = max(0, round((float) $employee->annual_leave_balance_hours - $hours, 2));
                $employee->annual_leave_balance_amount = max(0, round((float) $employee->annual_leave_balance_amount - $amount, 2));
            }

            $record->forceFill([
                'status' => EmployeeLeaveRecord::STATUS_PAID,
                'paid_amount' => $amount,
                'hourly_rate' => $line['rate'],
            ])->save();
        }
    }
}
