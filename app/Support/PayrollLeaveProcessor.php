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

            $dateLabel = DisplayTimezone::format($record->leave_date, 'M j, Y');

            // Unpaid leave is recorded as a zero-dollar tracking line so it still
            // appears on the pay run for attendance, without paying the employee.
            if (! (bool) ($record->is_paid ?? true)) {
                $lines[] = [
                    'rate_type' => PayrollRateTypes::UNPAID_LEAVE_TAKEN,
                    'label' => 'Unpaid leave taken · '.$dateLabel,
                    'hours' => $hours,
                    'rate' => 0.0,
                    'amount' => 0.0,
                    'sort_order' => $sort++,
                    'leave_record_id' => (int) $record->id,
                ];

                continue;
            }

            $hourlyRate = (float) ($record->hourly_rate ?? $ordinary);
            if ($hourlyRate <= 0) {
                $hourlyRate = $ordinary;
            }

            $amount = round($hours * $hourlyRate, 2);

            [$rateType, $typeLabel] = match ($record->leave_type) {
                EmployeeLeaveRecord::TYPE_ANNUAL => [PayrollRateTypes::ANNUAL_LEAVE_TAKEN, 'Annual leave taken'],
                EmployeeLeaveRecord::TYPE_SICK => [PayrollRateTypes::SICK_LEAVE_TAKEN, 'Sick leave taken'],
                default => [PayrollRateTypes::LEAVE_TAKEN, ucfirst(str_replace('_', ' ', (string) $record->leave_type)).' leave taken'],
            };

            $lines[] = [
                'rate_type' => $rateType,
                'label' => $typeLabel.' · '.$dateLabel,
                'hours' => $hours,
                'rate' => round($hourlyRate, 2),
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

            // Unpaid leave draws no pay and no paid-leave balance; it is only recorded.
            if (! (bool) ($record->is_paid ?? true)) {
                $record->forceFill([
                    'status' => EmployeeLeaveRecord::STATUS_RECORDED,
                    'paid_amount' => 0,
                    'hourly_rate' => null,
                ])->save();

                continue;
            }

            if ($record->leave_type === EmployeeLeaveRecord::TYPE_SICK) {
                $employee->sick_leave_balance_hours = max(0, round((float) $employee->sick_leave_balance_hours - $hours, 2));
                $employee->sick_leave_balance_amount = max(0, round((float) $employee->sick_leave_balance_amount - $amount, 2));
            } elseif ($record->leave_type === EmployeeLeaveRecord::TYPE_ANNUAL) {
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
