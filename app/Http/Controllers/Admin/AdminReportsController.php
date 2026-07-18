<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLeaveRecord;
use App\Models\OrganizationPortalUser;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\TimesheetApproval;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminReportsController extends Controller
{
    /**
     * Reports landing — send straight to the first report.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.reports.payroll');
    }

    /**
     * Payroll summary — pay runs with total pay and hours per run.
     */
    public function payroll(Request $request): View
    {
        $ctx = $this->pageContext($request);
        $from = $this->parseDate($request, 'from');
        $to = $this->parseDate($request, 'to');
        $status = (string) $request->query('status', '');
        $status = in_array($status, ['draft', 'finalized'], true) ? $status : '';
        $runs = collect();

        try {
            $conn = $ctx['connection'];
            $query = PayrollRun::on($conn);
            if ($from) {
                $query->whereDate('fortnight_end', '>=', $from->toDateString());
            }
            if ($to) {
                $query->whereDate('fortnight_start', '<=', $to->toDateString());
            }
            if ($status !== '') {
                $query->where('status', $status);
            }

            $runModels = $query->orderByDesc('fortnight_start')->get();

            $lineTotals = PayrollRunLine::on($conn)
                ->whereIn('payroll_run_id', $runModels->pluck('id')->all())
                ->selectRaw('payroll_run_id, SUM(amount) as total_amount, SUM(hours) as total_hours, COUNT(DISTINCT employee_id) as employee_count')
                ->groupBy('payroll_run_id')
                ->get()
                ->keyBy('payroll_run_id');

            $runs = $runModels->map(function (PayrollRun $run) use ($lineTotals): array {
                $totals = $lineTotals->get($run->id);

                return [
                    'id' => $run->id,
                    'period_start' => $run->fortnight_start,
                    'period_end' => $run->fortnight_end,
                    'status' => $run->status,
                    'total_amount' => (float) ($totals->total_amount ?? 0),
                    'total_hours' => (float) ($totals->total_hours ?? 0),
                    'employee_count' => (int) ($totals->employee_count ?? 0),
                ];
            });
        } catch (\Throwable $e) {
            $ctx['tenantError'] = $e->getMessage();
        }

        return view('admin.reports', array_merge($ctx, [
            'section' => 'payroll',
            'runs' => $runs,
            'periodLabel' => $this->periodLabel($from, $to, 'All recorded pay runs'),
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'status' => $status,
            ],
        ]));
    }

    /**
     * Timesheet & hours — approved worked hours per employee for a date range.
     */
    public function timesheet(Request $request): View
    {
        $ctx = $this->pageContext($request);
        $to = $this->parseDate($request, 'to') ?? Carbon::today();
        $from = $this->parseDate($request, 'from') ?? $to->copy()->subDays(29);
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }
        $employeeId = (int) $request->integer('employee_id');
        $status = (string) $request->query('status', '');
        $status = in_array($status, ['approved', 'pending', 'rejected'], true) ? $status : '';
        $rows = collect();
        $employeeOptions = collect();

        try {
            $conn = $ctx['connection'];

            $employeeOptions = Employee::on($conn)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'full_legal_name'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $this->employeeName($e)]);

            $query = TimesheetApproval::on($conn)
                ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()]);
            if ($status !== '') {
                $query->where('status', $status);
            }
            if ($employeeId > 0) {
                $query->where('employee_id', $employeeId);
            }

            $aggregates = $query
                ->selectRaw('employee_id, SUM(total_seconds) as total_seconds, COUNT(*) as day_count, SUM(completed_sessions) as sessions')
                ->groupBy('employee_id')
                ->get();

            $employees = Employee::on($conn)
                ->whereIn('id', $aggregates->pluck('employee_id')->all())
                ->get()
                ->keyBy('id');

            $rows = $aggregates
                ->map(fn ($row) => [
                    'employee' => $this->employeeName($employees->get($row->employee_id)),
                    'hours' => round(((int) $row->total_seconds) / 3600, 2),
                    'days' => (int) $row->day_count,
                    'sessions' => (int) $row->sessions,
                ])
                ->sortByDesc('hours')
                ->values();
        } catch (\Throwable $e) {
            $ctx['tenantError'] = $e->getMessage();
        }

        return view('admin.reports', array_merge($ctx, [
            'section' => 'timesheet',
            'rows' => $rows,
            'employeeOptions' => $employeeOptions,
            'periodLabel' => $this->periodLabel($from, $to, ''),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'employee_id' => $employeeId > 0 ? $employeeId : '',
                'status' => $status,
            ],
        ]));
    }

    /**
     * Leave report — leave taken grouped by type, plus recent records.
     */
    public function leave(Request $request): View
    {
        $ctx = $this->pageContext($request);
        $from = $this->parseDate($request, 'from');
        $to = $this->parseDate($request, 'to');
        $leaveType = trim((string) $request->query('leave_type', ''));
        $payment = (string) $request->query('payment', '');
        $payment = in_array($payment, ['paid', 'unpaid'], true) ? $payment : '';
        $byType = collect();
        $recent = collect();
        $leaveTypeOptions = collect();

        try {
            $conn = $ctx['connection'];

            $leaveTypeOptions = EmployeeLeaveRecord::on($conn)
                ->whereNotNull('leave_type')
                ->where('leave_type', '<>', '')
                ->distinct()
                ->orderBy('leave_type')
                ->pluck('leave_type');

            $applyFilters = function ($query) use ($from, $to, $leaveType, $payment) {
                if ($from) {
                    $query->whereDate('leave_date', '>=', $from->toDateString());
                }
                if ($to) {
                    $query->whereDate('leave_date', '<=', $to->toDateString());
                }
                if ($leaveType !== '') {
                    $query->where('leave_type', $leaveType);
                }
                if ($payment === 'paid') {
                    $query->where('is_paid', true);
                } elseif ($payment === 'unpaid') {
                    $query->where('is_paid', false);
                }

                return $query;
            };

            $byType = $applyFilters(EmployeeLeaveRecord::on($conn))
                ->selectRaw('leave_type, COUNT(*) as records, SUM(hours) as total_hours, SUM(paid_amount) as total_amount')
                ->groupBy('leave_type')
                ->orderByDesc('total_hours')
                ->get()
                ->map(fn ($row) => [
                    'leave_type' => (string) ($row->leave_type ?: 'Unspecified'),
                    'records' => (int) $row->records,
                    'total_hours' => (float) $row->total_hours,
                    'total_amount' => (float) $row->total_amount,
                ]);

            $records = $applyFilters(EmployeeLeaveRecord::on($conn))
                ->orderByDesc('leave_date')
                ->limit(50)
                ->get();

            $employees = Employee::on($conn)
                ->whereIn('id', $records->pluck('employee_id')->all())
                ->get()
                ->keyBy('id');

            $recent = $records->map(fn (EmployeeLeaveRecord $record) => [
                'employee' => $this->employeeName($employees->get($record->employee_id)),
                'leave_type' => (string) ($record->leave_type ?: '—'),
                'leave_date' => $record->leave_date,
                'hours' => (float) $record->hours,
                'is_paid' => (bool) $record->is_paid,
                'status' => (string) ($record->status ?: '—'),
            ]);
        } catch (\Throwable $e) {
            $ctx['tenantError'] = $e->getMessage();
        }

        return view('admin.reports', array_merge($ctx, [
            'section' => 'leave',
            'byType' => $byType,
            'recent' => $recent,
            'leaveTypeOptions' => $leaveTypeOptions,
            'periodLabel' => $this->periodLabel($from, $to, 'All recorded leave'),
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'leave_type' => $leaveType,
                'payment' => $payment,
            ],
        ]));
    }

    /**
     * Workforce headcount — employees broken down by status, type and department.
     */
    public function headcount(Request $request): View
    {
        $ctx = $this->pageContext($request);
        $department = trim((string) $request->query('department', ''));
        $employmentType = trim((string) $request->query('employment_type', ''));
        $employmentStatus = trim((string) $request->query('employment_status', ''));
        $total = 0;
        $byStatus = collect();
        $byType = collect();
        $byDepartment = collect();
        $departmentOptions = collect();
        $typeOptions = collect();
        $statusOptions = collect();

        try {
            $conn = $ctx['connection'];

            $departmentOptions = Employee::on($conn)->whereNotNull('department')->where('department', '<>', '')->distinct()->orderBy('department')->pluck('department');
            $typeOptions = Employee::on($conn)->whereNotNull('employment_type')->where('employment_type', '<>', '')->distinct()->orderBy('employment_type')->pluck('employment_type');
            $statusOptions = Employee::on($conn)->whereNotNull('employment_status')->where('employment_status', '<>', '')->distinct()->orderBy('employment_status')->pluck('employment_status');

            $applyFilters = function ($query) use ($department, $employmentType, $employmentStatus) {
                if ($department !== '') {
                    $query->where('department', $department);
                }
                if ($employmentType !== '') {
                    $query->where('employment_type', $employmentType);
                }
                if ($employmentStatus !== '') {
                    $query->where('employment_status', $employmentStatus);
                }

                return $query;
            };

            $total = $applyFilters(Employee::on($conn))->count();

            $byStatus = $applyFilters(Employee::on($conn))
                ->selectRaw("COALESCE(NULLIF(employment_status, ''), 'unspecified') as label, COUNT(*) as count")
                ->groupBy('label')->orderByDesc('count')->get()
                ->map(fn ($row) => ['label' => (string) $row->label, 'count' => (int) $row->count]);

            $byType = $applyFilters(Employee::on($conn))
                ->selectRaw("COALESCE(NULLIF(employment_type, ''), 'unspecified') as label, COUNT(*) as count")
                ->groupBy('label')->orderByDesc('count')->get()
                ->map(fn ($row) => ['label' => (string) $row->label, 'count' => (int) $row->count]);

            $byDepartment = $applyFilters(Employee::on($conn))
                ->selectRaw("COALESCE(NULLIF(department, ''), 'Unassigned') as label, COUNT(*) as count")
                ->groupBy('label')->orderByDesc('count')->get()
                ->map(fn ($row) => ['label' => (string) $row->label, 'count' => (int) $row->count]);
        } catch (\Throwable $e) {
            $ctx['tenantError'] = $e->getMessage();
        }

        return view('admin.reports', array_merge($ctx, [
            'section' => 'headcount',
            'total' => $total,
            'byStatus' => $byStatus,
            'byType' => $byType,
            'byDepartment' => $byDepartment,
            'departmentOptions' => $departmentOptions,
            'typeOptions' => $typeOptions,
            'statusOptions' => $statusOptions,
            'periodLabel' => 'As at '.\App\Support\DisplayTimezone::now()->format('d M Y'),
            'filters' => [
                'department' => $department,
                'employment_type' => $employmentType,
                'employment_status' => $employmentStatus,
            ],
        ]));
    }

    private function parseDate(Request $request, string $key): ?Carbon
    {
        $value = $request->query($key);
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function periodLabel(?Carbon $from, ?Carbon $to, string $fallback): string
    {
        $fmt = static fn (Carbon $d): string => $d->format('d M Y');

        if ($from && $to) {
            return $fmt($from).' – '.$fmt($to);
        }
        if ($from) {
            return 'From '.$fmt($from);
        }
        if ($to) {
            return 'Up to '.$fmt($to);
        }

        return $fallback;
    }

    private function employeeName(?Employee $employee): string
    {
        if (! $employee instanceof Employee) {
            return 'Unknown employee';
        }

        $name = trim((string) ($employee->full_legal_name
            ?: trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))));

        return $name !== '' ? $name : ('Employee #'.$employee->id);
    }

    /**
     * @return array{company: \App\Models\Company, connection: string, currentCompany: \App\Models\Company, tenantError: string|null}
     */
    private function pageContext(Request $request): array
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();

        return [
            'company' => $company,
            'currentCompany' => $company,
            'connection' => $company->tenant_connection,
            'tenantError' => null,
        ];
    }
}
