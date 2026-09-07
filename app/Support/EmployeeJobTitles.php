<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\JobTitle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sync employee ↔ job title assignments (multi-title + primary).
 */
final class EmployeeJobTitles
{
    /**
     * @param  list<int>  $jobTitleIds
     */
    public static function sync(string $connection, Employee $employee, array $jobTitleIds, ?int $primaryJobTitleId): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $jobTitleIds),
            static fn (int $id) => $id > 0
        )));

        if ($primaryJobTitleId !== null && $primaryJobTitleId > 0 && ! in_array($primaryJobTitleId, $ids, true)) {
            $ids[] = $primaryJobTitleId;
        }

        if ($ids === []) {
            DB::connection($connection)->table('employee_job_title')
                ->where('employee_id', $employee->id)
                ->delete();
            $employee->forceFill([
                'job_title_id' => null,
                'job_title' => null,
            ])->save();

            return;
        }

        $validIds = JobTitle::on($connection)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validIds) !== count($ids)) {
            throw ValidationException::withMessages([
                'job_title_ids' => 'One or more selected job titles are invalid.',
            ]);
        }

        $primary = $primaryJobTitleId !== null && in_array($primaryJobTitleId, $validIds, true)
            ? $primaryJobTitleId
            : ($employee->job_title_id && in_array((int) $employee->job_title_id, $validIds, true)
                ? (int) $employee->job_title_id
                : $validIds[0]);

        $now = now();
        $rows = [];
        foreach ($validIds as $titleId) {
            $rows[] = [
                'employee_id' => $employee->id,
                'job_title_id' => $titleId,
                'is_primary' => $titleId === $primary,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::connection($connection)->transaction(function () use ($connection, $employee, $rows, $primary): void {
            DB::connection($connection)->table('employee_job_title')
                ->where('employee_id', $employee->id)
                ->delete();
            DB::connection($connection)->table('employee_job_title')->insert($rows);

            $primaryTitle = JobTitle::on($connection)->whereKey($primary)->first();
            $fill = [
                'job_title_id' => $primary,
                'job_title' => $primaryTitle?->name,
            ];
            if ($primaryTitle !== null && is_string($primaryTitle->employment_type) && $primaryTitle->employment_type !== '') {
                $fill['employment_type'] = $primaryTitle->employment_type;
            }
            if ($primaryTitle !== null && is_string($primaryTitle->award_level) && $primaryTitle->award_level !== '') {
                $fill['award_level'] = $primaryTitle->award_level;
            }
            $employee->forceFill($fill)->save();
        });
    }

    /**
     * Add this job title to employees without replacing their other titles.
     *
     * @param  list<int>  $employeeIds
     */
    public static function attachToTitle(string $connection, JobTitle $title, array $employeeIds): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $employeeIds),
            static fn (int $id) => $id > 0
        )));

        if ($ids === []) {
            return;
        }

        $employees = Employee::on($connection)->whereIn('id', $ids)->get();
        $now = now();

        foreach ($employees as $employee) {
            $already = DB::connection($connection)->table('employee_job_title')
                ->where('employee_id', $employee->id)
                ->where('job_title_id', $title->id)
                ->exists();

            if ($already) {
                continue;
            }

            $hasPrimary = DB::connection($connection)->table('employee_job_title')
                ->where('employee_id', $employee->id)
                ->where('is_primary', true)
                ->exists();

            $makePrimary = ! $hasPrimary && $employee->job_title_id === null;

            DB::connection($connection)->table('employee_job_title')->insert([
                'employee_id' => $employee->id,
                'job_title_id' => $title->id,
                'is_primary' => $makePrimary,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($makePrimary) {
                $employee->forceFill([
                    'job_title_id' => $title->id,
                    'job_title' => $title->name,
                ])->save();
            }
        }
    }

    /**
     * @return list<int>
     */
    public static function idsFor(Employee $employee): array
    {
        if ($employee->relationLoaded('jobTitles')) {
            return $employee->jobTitles->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $employee->jobTitles()->pluck('job_titles.id')->map(fn ($id) => (int) $id)->all();
    }
}
