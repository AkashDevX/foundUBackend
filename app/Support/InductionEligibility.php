<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\InductionAttempt;
use App\Models\TrainingAssignment;
use App\Models\TrainingAttempt;
use App\Models\TrainingModule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Newly approved employees stay ineligible for shifts and clock-in until they
 * pass the published induction module, or an admin records an override.
 * Employees hired before this rule keep induction_status = not_required.
 */
final class InductionEligibility
{
    public const STATUS_NOT_REQUIRED = 'not_required';

    public const STATUS_REQUIRED = 'required';

    public const STATUS_PASSED = 'passed';

    public const STATUS_OVERRIDDEN = 'overridden';

    public const CLOCK_IN_CODE = 'induction_required';

    public const BLOCK_MESSAGE = 'Pass mandatory induction before you can be rostered or clock in and out. Open the Train tab. An administrator can grant an override.';

    public const RECOMMENDED_QUESTIONS = 25;

    public const DEFAULT_PASS_PERCENT = 92;

    public const DEFAULT_MAX_ATTEMPTS = 3;

    public static function blocksStatus(?string $status): bool
    {
        return $status === self::STATUS_REQUIRED;
    }

    public static function allowsAnotherAttempt(bool $passed, int $attemptsUsed, int $maxAttempts): bool
    {
        return ! $passed && $attemptsUsed < max(1, $maxAttempts);
    }

    public static function percentPassed(int $score, int $maxScore, int $passPercent): bool
    {
        if ($maxScore <= 0) {
            return false;
        }

        $percent = round(($score / $maxScore) * 100, 2);

        return $percent >= $passPercent;
    }

    public static function blocksWork(Employee $employee): bool
    {
        return self::blocksStatus(self::status($employee));
    }

    public static function status(Employee $employee): string
    {
        $connection = $employee->getConnectionName();
        if (! is_string($connection) || $connection === '' || ! self::employeeColumnReady($connection)) {
            return self::STATUS_NOT_REQUIRED;
        }

        $status = (string) ($employee->induction_status ?? self::STATUS_NOT_REQUIRED);

        return in_array($status, [
            self::STATUS_NOT_REQUIRED,
            self::STATUS_REQUIRED,
            self::STATUS_PASSED,
            self::STATUS_OVERRIDDEN,
        ], true) ? $status : self::STATUS_NOT_REQUIRED;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_REQUIRED => 'Induction required',
            self::STATUS_PASSED => 'Induction passed',
            self::STATUS_OVERRIDDEN => 'Override — eligible for shifts',
            default => 'Not required',
        };
    }

    public static function assertCanBeScheduled(Employee $employee): void
    {
        if (! self::blocksWork($employee)) {
            return;
        }

        $name = trim((string) ($employee->full_legal_name ?: $employee->email ?: 'This employee'));

        throw ValidationException::withMessages([
            'induction' => sprintf(
                '%s must pass mandatory induction before shifts can be assigned. Grant an override on their profile if they should work first.',
                $name,
            ),
        ]);
    }

    /**
     * Mark a newly approved employee as induction-required and assign the published module.
     */
    public static function requireForNewHire(Employee $employee, ?string $assignedBy): string
    {
        $connection = $employee->getConnectionName();
        if (! is_string($connection) || $connection === '' || ! self::employeeColumnReady($connection)) {
            return 'Registration approved. The employee must open the mobile app and sign in with their registration email and password.';
        }

        $employee->forceFill([
            'induction_status' => self::STATUS_REQUIRED,
            'induction_passed_at' => null,
            'induction_overridden_at' => null,
            'induction_overridden_by' => null,
            'induction_override_reason' => null,
        ])->save();

        $module = self::inductionModule($connection);
        if ($module === null) {
            return 'Registration approved. This employee must pass mandatory induction before shifts or clock-in. Publish the induction under Training and it is assigned automatically — there is no employee list to tick. They sign in on the mobile app with their registration email and password.';
        }

        self::assignModule($module, $employee, $assignedBy ?: 'Induction');

        return 'Registration approved. Mandatory induction is now in their Train tab. They stay ineligible for shifts and clock-in until they pass or you grant an override.';
    }

    public static function ensureAssigned(Employee $employee): void
    {
        if (self::status($employee) !== self::STATUS_REQUIRED) {
            return;
        }

        $connection = $employee->getConnectionName();
        if (! is_string($connection) || $connection === '') {
            return;
        }

        $module = self::inductionModule($connection);
        if ($module === null) {
            return;
        }

        self::assignModule($module, $employee, 'Induction');
    }

    public static function assignRequiredEmployees(TrainingModule $module, ?string $assignedBy): int
    {
        if (! $module->is_induction) {
            return 0;
        }

        $connection = $module->getConnectionName();
        if (! is_string($connection) || $connection === '' || ! self::employeeColumnReady($connection)) {
            return 0;
        }

        $employees = Employee::on($connection)
            ->where('employment_status', 'active')
            ->where('induction_status', self::STATUS_REQUIRED)
            ->get();

        $created = 0;
        foreach ($employees as $employee) {
            if (self::assignModule($module, $employee, $assignedBy ?: 'Induction')) {
                $created++;
            }
        }

        return $created;
    }

    public static function grantOverride(Employee $employee, string $grantedBy, string $reason): void
    {
        $connection = $employee->getConnectionName();
        if (! is_string($connection) || ! self::employeeColumnReady($connection)) {
            throw ValidationException::withMessages([
                'induction' => 'Induction is not available until the latest database update has been applied.',
            ]);
        }

        if (($employee->employment_status ?? '') !== 'active') {
            throw ValidationException::withMessages([
                'induction' => 'Only active employees can receive an induction override.',
            ]);
        }

        $employee->forceFill([
            'induction_status' => self::STATUS_OVERRIDDEN,
            'induction_overridden_at' => now(),
            'induction_overridden_by' => $grantedBy,
            'induction_override_reason' => $reason,
        ])->save();
    }

    public static function clearOverride(Employee $employee): void
    {
        if (self::status($employee) !== self::STATUS_OVERRIDDEN) {
            throw ValidationException::withMessages([
                'induction' => 'This employee does not have an induction override.',
            ]);
        }

        $passed = self::hasPassingAttempt($employee);
        $employee->forceFill([
            'induction_status' => $passed ? self::STATUS_PASSED : self::STATUS_REQUIRED,
            'induction_passed_at' => $passed ? ($employee->induction_passed_at ?? now()) : null,
            'induction_overridden_at' => null,
            'induction_overridden_by' => null,
            'induction_override_reason' => null,
        ])->save();
    }

    /**
     * Archive this quiz submission. A failed induction with attempts left reopens
     * the live training attempt so the employee can sit it again.
     *
     * @return array{is_induction: bool, can_retry: bool, attempts_used: int, max_attempts: int, attempts_remaining: int, passed: bool, locked: bool}
     */
    public static function finalizeSubmission(TrainingAssignment $assignment, TrainingAttempt $attempt): array
    {
        $assignment->loadMissing('module');
        $module = $assignment->module;
        $empty = self::emptyContext();

        if ($module === null || ! $module->is_induction) {
            return $empty;
        }

        $connection = $assignment->getConnectionName();
        if (! is_string($connection) || $connection === '' || ! self::attemptsTableReady($connection)) {
            return $empty;
        }

        $maxAttempts = max(1, (int) ($module->max_attempts ?: self::DEFAULT_MAX_ATTEMPTS));
        $used = (int) InductionAttempt::on($connection)
            ->where('training_assignment_id', $assignment->id)
            ->count() + 1;

        InductionAttempt::on($connection)->create([
            'employee_id' => $assignment->employee_id,
            'training_assignment_id' => $assignment->id,
            'training_module_id' => $module->id,
            'attempt_number' => $used,
            'score' => $attempt->score,
            'max_score' => $attempt->max_score,
            'percent' => $attempt->percent,
            'passed' => $attempt->passed === true,
            'submitted_at' => $attempt->submitted_at ?? now(),
        ]);

        $passed = $attempt->passed === true;
        $employee = Employee::on($connection)->find($assignment->employee_id);
        if ($passed && $employee instanceof Employee && self::employeeColumnReady($connection)) {
            if (self::status($employee) !== self::STATUS_OVERRIDDEN) {
                $employee->forceFill([
                    'induction_status' => self::STATUS_PASSED,
                    'induction_passed_at' => now(),
                ])->save();
            }
        }

        $canRetry = self::allowsAnotherAttempt($passed, $used, $maxAttempts);
        if ($canRetry) {
            AdminTraining::resetAttempt($assignment);
        }

        return [
            'is_induction' => true,
            'can_retry' => $canRetry,
            'attempts_used' => $used,
            'max_attempts' => $maxAttempts,
            'attempts_remaining' => max(0, $maxAttempts - $used),
            'passed' => $passed,
            'locked' => ! $passed && ! $canRetry,
        ];
    }

    public static function onAdminReset(TrainingAssignment $assignment): void
    {
        $assignment->loadMissing('module');
        $module = $assignment->module;
        if ($module === null || ! $module->is_induction) {
            return;
        }

        $connection = $assignment->getConnectionName();
        if (! is_string($connection) || $connection === '') {
            return;
        }

        if (self::attemptsTableReady($connection)) {
            InductionAttempt::on($connection)
                ->where('training_assignment_id', $assignment->id)
                ->delete();
        }

        if (! self::employeeColumnReady($connection)) {
            return;
        }

        $employee = Employee::on($connection)->find($assignment->employee_id);
        if (! $employee instanceof Employee) {
            return;
        }

        if (self::status($employee) === self::STATUS_PASSED) {
            $employee->forceFill([
                'induction_status' => self::STATUS_REQUIRED,
                'induction_passed_at' => null,
            ])->save();
        }
    }

    /**
     * @return array{is_induction: bool, max_attempts: int, attempts_used: int, attempts_remaining: int|null}
     */
    public static function summaryFields(TrainingAssignment $assignment): array
    {
        $assignment->loadMissing('module');
        $module = $assignment->module;
        $isInduction = (bool) ($module?->is_induction ?? false);
        $maxAttempts = $isInduction
            ? max(1, (int) ($module?->max_attempts ?: self::DEFAULT_MAX_ATTEMPTS))
            : 1;
        $used = $isInduction ? self::attemptsUsed($assignment) : 0;

        return [
            'is_induction' => $isInduction,
            'max_attempts' => $maxAttempts,
            'attempts_used' => $used,
            'attempts_remaining' => $isInduction ? max(0, $maxAttempts - $used) : null,
        ];
    }

    /**
     * @return array{is_induction: bool, can_retry: bool, attempts_used: int, max_attempts: int, attempts_remaining: int, passed: bool, locked: bool}
     */
    public static function detailContext(TrainingAssignment $assignment): array
    {
        $summary = self::summaryFields($assignment);
        if ($summary['is_induction'] !== true) {
            return self::emptyContext();
        }

        $assignment->loadMissing('attempt');
        $connection = $assignment->getConnectionName();
        $employee = is_string($connection) && $connection !== ''
            ? Employee::on($connection)->find($assignment->employee_id)
            : null;
        $status = $employee instanceof Employee ? self::status($employee) : self::STATUS_REQUIRED;
        $passed = $status === self::STATUS_PASSED || $assignment->attempt?->passed === true;
        $submitted = $assignment->attempt?->isSubmitted() === true;
        $used = (int) $summary['attempts_used'];
        $maxAttempts = (int) $summary['max_attempts'];
        $canRetry = $status === self::STATUS_REQUIRED
            && self::allowsAnotherAttempt($passed, $used, $maxAttempts)
            && ! $submitted;

        return [
            'is_induction' => true,
            'can_retry' => $canRetry,
            'attempts_used' => $used,
            'max_attempts' => $maxAttempts,
            'attempts_remaining' => max(0, $maxAttempts - $used),
            'passed' => $passed,
            'locked' => $status === self::STATUS_REQUIRED && $submitted && ! $passed && $used >= $maxAttempts,
        ];
    }

    /**
     * @return array{required: bool, status: string, status_label: string, message: string|null}
     */
    public static function mobileSummary(Employee $employee): array
    {
        $status = self::status($employee);
        $required = self::blocksStatus($status);

        return [
            'required' => $required,
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'message' => $required ? self::BLOCK_MESSAGE : null,
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     passed_at: \Illuminate\Support\Carbon|null,
     *     overridden_at: \Illuminate\Support\Carbon|null,
     *     overridden_by: string|null,
     *     override_reason: string|null,
     *     attempts: list<array{attempt_number: int, percent: float|null, passed: bool, score: int|null, max_score: int|null, submitted_at: \Illuminate\Support\Carbon|null}>,
     *     can_override: bool,
     *     can_clear_override: bool
     * }|null
     */
    public static function adminCard(Employee $employee): ?array
    {
        $connection = $employee->getConnectionName();
        if (! is_string($connection) || $connection === '' || ! self::employeeColumnReady($connection)) {
            return null;
        }

        $status = self::status($employee);
        $attempts = [];
        if (self::attemptsTableReady($connection)) {
            $attempts = InductionAttempt::on($connection)
                ->where('employee_id', $employee->id)
                ->orderBy('attempt_number')
                ->orderBy('id')
                ->get()
                ->map(static fn (InductionAttempt $row): array => [
                    'attempt_number' => (int) $row->attempt_number,
                    'percent' => $row->percent !== null ? (float) $row->percent : null,
                    'passed' => $row->passed === true,
                    'score' => $row->score,
                    'max_score' => $row->max_score,
                    'submitted_at' => $row->submitted_at,
                ])
                ->all();
        }

        return [
            'status' => $status,
            'label' => self::statusLabel($status),
            'passed_at' => $employee->induction_passed_at,
            'overridden_at' => $employee->induction_overridden_at,
            'overridden_by' => $employee->induction_overridden_by,
            'override_reason' => $employee->induction_override_reason,
            'attempts' => $attempts,
            'can_override' => ($employee->employment_status ?? '') === 'active' && $status === self::STATUS_REQUIRED,
            'can_clear_override' => $status === self::STATUS_OVERRIDDEN,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function decorateResultRows(string $connection, array $rows): array
    {
        if (! self::attemptsTableReady($connection)) {
            return $rows;
        }

        $assignmentIds = collect($rows)->pluck('assignment_id')->filter()->map(fn ($id) => (int) $id)->all();
        $history = InductionAttempt::on($connection)
            ->whereIn('training_assignment_id', $assignmentIds)
            ->orderBy('attempt_number')
            ->get()
            ->groupBy('training_assignment_id');

        $employeeIds = collect($rows)->pluck('employee_id')->filter()->map(fn ($id) => (int) $id)->all();
        $statuses = self::employeeColumnReady($connection)
            ? Employee::on($connection)->whereIn('id', $employeeIds)->pluck('induction_status', 'id')
            : collect();

        return array_map(static function (array $row) use ($history, $statuses): array {
            $assignmentId = (int) ($row['assignment_id'] ?? 0);
            $employeeId = (int) ($row['employee_id'] ?? 0);
            $attempts = $history->get($assignmentId, collect());
            $row['induction_attempts'] = $attempts->map(static fn (InductionAttempt $attempt): array => [
                'attempt_number' => (int) $attempt->attempt_number,
                'percent' => $attempt->percent,
                'passed' => $attempt->passed === true,
                'score' => $attempt->score,
                'max_score' => $attempt->max_score,
            ])->all();
            $row['induction_status'] = (string) ($statuses[$employeeId] ?? self::STATUS_NOT_REQUIRED);

            return $row;
        }, $rows);
    }

    /**
     * The organization has one induction. Create it the first time an admin opens Induction.
     */
    public static function ensureSingleton(string $connection, ?string $createdBy): TrainingModule
    {
        if (! Schema::connection($connection)->hasColumn('training_modules', 'is_induction')) {
            throw ValidationException::withMessages([
                'induction' => 'Induction is not available until the latest database update has been applied.',
            ]);
        }

        $existing = TrainingModule::on($connection)
            ->where('is_induction', true)
            ->orderBy('id')
            ->get();

        if ($existing->isEmpty()) {
            return TrainingModule::on($connection)->create([
                'title' => 'Employee induction',
                'description' => 'Company policies, environmental policies, health and safety, heat stress, working at heights, chemical safety, and other required training. New employees must pass this before they can be rostered or clock in.',
                'status' => 'draft',
                'is_induction' => true,
                'pass_percent' => self::DEFAULT_PASS_PERCENT,
                'max_attempts' => self::DEFAULT_MAX_ATTEMPTS,
                'question_time_seconds' => 45,
                'created_by' => $createdBy,
            ]);
        }

        /** @var TrainingModule $primary */
        $primary = $existing->first();
        if ($existing->count() > 1) {
            TrainingModule::on($connection)
                ->where('is_induction', true)
                ->where('id', '!=', $primary->id)
                ->update(['is_induction' => false]);
        }

        return $primary;
    }

    public static function markModuleAsInduction(TrainingModule $module, bool $isInduction): void
    {
        $connection = $module->getConnectionName();
        if (! is_string($connection) || $connection === '' || ! Schema::connection($connection)->hasColumn('training_modules', 'is_induction')) {
            return;
        }

        if ($isInduction) {
            TrainingModule::on($connection)
                ->where('id', '!=', $module->id)
                ->where('is_induction', true)
                ->update(['is_induction' => false]);
        }

        $module->is_induction = $isInduction;
        if ($isInduction && (int) ($module->max_attempts ?? 1) < 1) {
            $module->max_attempts = self::DEFAULT_MAX_ATTEMPTS;
        }
        $module->save();
    }

    private static function assignModule(TrainingModule $module, Employee $employee, string $assignedBy): bool
    {
        $connection = $module->getConnectionName() ?: $employee->getConnectionName();
        if (! is_string($connection) || $connection === '') {
            return false;
        }

        $exists = TrainingAssignment::on($connection)
            ->where('training_module_id', $module->id)
            ->where('employee_id', $employee->id)
            ->exists();

        if ($exists) {
            return false;
        }

        TrainingAssignment::on($connection)->create([
            'training_module_id' => $module->id,
            'employee_id' => $employee->id,
            'assigned_by' => $assignedBy,
            'assigned_at' => now(),
        ]);

        return true;
    }

    private static function inductionModule(string $connection): ?TrainingModule
    {
        if (! Schema::connection($connection)->hasTable('training_modules')) {
            return null;
        }
        if (! Schema::connection($connection)->hasColumn('training_modules', 'is_induction')) {
            return null;
        }

        $modules = TrainingModule::on($connection)
            ->where('is_induction', true)
            ->orderByDesc('id')
            ->get();

        return $modules->firstWhere('status', 'published') ?? $modules->first();
    }

    private static function hasPassingAttempt(Employee $employee): bool
    {
        $connection = $employee->getConnectionName();
        if (! is_string($connection) || ! self::attemptsTableReady($connection)) {
            return false;
        }

        return InductionAttempt::on($connection)
            ->where('employee_id', $employee->id)
            ->where('passed', true)
            ->exists();
    }

    private static function attemptsUsed(TrainingAssignment $assignment): int
    {
        $connection = $assignment->getConnectionName();
        if (! is_string($connection) || $connection === '' || ! self::attemptsTableReady($connection)) {
            return 0;
        }

        return (int) InductionAttempt::on($connection)
            ->where('training_assignment_id', $assignment->id)
            ->count();
    }

    /**
     * @return array{is_induction: bool, can_retry: bool, attempts_used: int, max_attempts: int, attempts_remaining: int, passed: bool, locked: bool}
     */
    private static function emptyContext(): array
    {
        return [
            'is_induction' => false,
            'can_retry' => false,
            'attempts_used' => 0,
            'max_attempts' => 1,
            'attempts_remaining' => 0,
            'passed' => false,
            'locked' => false,
        ];
    }

    private static function employeeColumnReady(string $connection): bool
    {
        return Schema::connection($connection)->hasTable('employees')
            && Schema::connection($connection)->hasColumn('employees', 'induction_status');
    }

    private static function attemptsTableReady(string $connection): bool
    {
        return Schema::connection($connection)->hasTable('induction_attempts');
    }
};
