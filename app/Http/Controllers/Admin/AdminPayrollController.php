<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OrganizationPortalUser;
use App\Models\PayrollAwardRate;
use App\Models\PayrollRun;
use App\Models\PublicHoliday;
use App\Models\TimesheetApproval;
use App\Support\AdminPayroll;
use App\Support\PayrollAwardRateSeeder;
use App\Support\PayrollRateTypes;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPayrollController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.payroll.runs');
    }

    public function rates(Request $request): View
    {
        $ctx = $this->pageContext($request);
        PayrollAwardRateSeeder::ensureDefaults($ctx['connection']);

        $effectiveFrom = (string) config('payroll.default_rates_effective_from', '2025-07-01');

        $rates = PayrollAwardRate::on($ctx['connection'])
            ->where('effective_from', $effectiveFrom)
            ->orderBy('employment_type')
            ->orderBy('award_level')
            ->orderBy('rate_type')
            ->get();

        return view('admin.payroll', array_merge($ctx, [
            'section' => 'rates',
            'effectiveFrom' => $effectiveFrom,
            'groupedRates' => AdminPayroll::groupRatesForDisplay($rates),
        ]));
    }

    public function updateRates(Request $request): RedirectResponse
    {
        $ctx = $this->pageContext($request);
        $effectiveFrom = (string) config('payroll.default_rates_effective_from', '2025-07-01');

        $data = $request->validate([
            'rates' => ['required', 'array'],
            'rates.*' => ['required', 'array'],
            'rates.*.*' => ['required', 'array'],
            'rates.*.*.*' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        DB::connection($ctx['connection'])->transaction(function () use ($ctx, $data, $effectiveFrom): void {
            foreach ($data['rates'] as $employmentType => $levels) {
                if (! in_array($employmentType, PayrollRateTypes::employmentTypes(), true)) {
                    continue;
                }
                foreach ($levels as $awardLevel => $rateTypes) {
                    if (! in_array($awardLevel, PayrollRateTypes::awardLevels(), true)) {
                        continue;
                    }
                    foreach ($rateTypes as $rateType => $amount) {
                        if (! in_array($rateType, PayrollRateTypes::awardRateKeys(), true)) {
                            continue;
                        }
                        PayrollAwardRate::on($ctx['connection'])->updateOrCreate(
                            [
                                'employment_type' => $employmentType,
                                'award_level' => $awardLevel,
                                'rate_type' => $rateType,
                                'effective_from' => $effectiveFrom,
                            ],
                            ['amount' => round((float) $amount, 2)]
                        );
                    }
                }
            }
        });

        return redirect()->route('admin.payroll.rates')->with('status', 'Award rates saved.');
    }

    public function runs(Request $request): View
    {
        $ctx = $this->pageContext($request);

        $existingRuns = PayrollRun::on($ctx['connection'])
            ->orderByDesc('fortnight_start')
            ->limit(24)
            ->get();

        $fortnightStart = $request->query('fortnight');
        if (is_string($fortnightStart) && $fortnightStart !== '') {
            $fortnightStart = AdminPayroll::normalizeFortnightStart($fortnightStart);
        } else {
            $recent = AdminPayroll::recentFortnights(1, $existingRuns);
            $fortnightStart = $recent[0]['start'] ?? AdminPayroll::normalizeFortnightStart(now()->toDateString());
        }

        $fortnightEnd = AdminPayroll::fortnightEndForStart($fortnightStart);

        $fortnightLabel = sprintf(
            '%s – %s',
            \App\Support\DisplayTimezone::format(\Carbon\Carbon::parse($fortnightStart), 'M j, Y'),
            \App\Support\DisplayTimezone::format(\Carbon\Carbon::parse($fortnightEnd), 'M j, Y')
        );

        $employees = $this->loadPayrollEmployees($ctx['connection'], $fortnightStart, $fortnightEnd);

        $timesheetApprovals = TimesheetApproval::on($ctx['connection'])->get();
        $publicHolidays = PublicHoliday::on($ctx['connection'])
            ->whereBetween('holiday_date', [$fortnightStart, $fortnightEnd])
            ->orderBy('holiday_date')
            ->get();

        $previewRows = AdminPayroll::previewFortnight(
            $ctx['connection'],
            $employees,
            $fortnightStart,
            $fortnightEnd,
            $publicHolidays,
            $timesheetApprovals,
        );

        $currentRun = PayrollRun::on($ctx['connection'])
            ->where('fortnight_start', $fortnightStart)
            ->with(['lines.employee'])
            ->first();

        return view('admin.payroll', array_merge($ctx, [
            'section' => 'runs',
            'fortnightStart' => $fortnightStart,
            'fortnightEnd' => $fortnightEnd,
            'fortnightLabel' => $fortnightLabel,
            'recentFortnights' => AdminPayroll::recentFortnights(8, $existingRuns),
            'previewRows' => $previewRows,
            'blockerStats' => AdminPayroll::blockerStats($previewRows),
            'requireApprovedTimesheets' => (bool) config('payroll.require_approved_timesheets', true),
            'publicHolidays' => $publicHolidays,
            'currentRun' => $currentRun,
        ]));
    }

    public function generateRun(Request $request): RedirectResponse
    {
        $ctx = $this->pageContext($request);

        $data = $request->validate([
            'fortnight_start' => ['required', 'date'],
            'finalize' => ['nullable', 'boolean'],
        ]);

        $fortnightStart = AdminPayroll::normalizeFortnightStart($data['fortnight_start']);
        $fortnightEnd = AdminPayroll::fortnightEndForStart($fortnightStart);
        $finalize = (bool) ($data['finalize'] ?? false);

        $employees = $this->loadPayrollEmployees($ctx['connection'], $fortnightStart, $fortnightEnd);

        $previewRows = AdminPayroll::previewFortnight(
            $ctx['connection'],
            $employees,
            $fortnightStart,
            $fortnightEnd,
            PublicHoliday::on($ctx['connection'])->whereBetween('holiday_date', [$fortnightStart, $fortnightEnd])->get(),
            TimesheetApproval::on($ctx['connection'])->get(),
        );

        $hasPayable = AdminPayroll::payableRows($previewRows) !== [];
        if (! $hasPayable) {
            throw ValidationException::withMessages([
                'fortnight_start' => AdminPayroll::summarizeBlockers($previewRows),
            ]);
        }

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');

        AdminPayroll::persistRun(
            $ctx['connection'],
            $fortnightStart,
            $fortnightEnd,
            $previewRows,
            (string) $portalUser->name,
            $finalize,
        );

        $message = $finalize
            ? 'Payroll run generated and finalized. Leave balances updated.'
            : 'Payroll run saved as draft.';

        return redirect()
            ->route('admin.payroll.runs', ['fortnight' => $fortnightStart])
            ->with('status', $message);
    }

    public function holidays(Request $request): View
    {
        $ctx = $this->pageContext($request);

        $holidays = PublicHoliday::on($ctx['connection'])
            ->orderByDesc('holiday_date')
            ->limit(50)
            ->get();

        return view('admin.payroll', array_merge($ctx, [
            'section' => 'holidays',
            'holidays' => $holidays,
        ]));
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $ctx = $this->pageContext($request);

        $data = $request->validate([
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:160'],
            'region' => ['nullable', 'string', 'max:32'],
        ]);

        PublicHoliday::on($ctx['connection'])->updateOrCreate(
            ['holiday_date' => $data['holiday_date']],
            ['name' => $data['name'], 'region' => $data['region']]
        );

        return redirect()->route('admin.payroll.holidays')->with('status', 'Public holiday saved.');
    }

    public function destroyHoliday(Request $request, int $holiday): RedirectResponse
    {
        $ctx = $this->pageContext($request);
        PublicHoliday::on($ctx['connection'])->whereKey($holiday)->delete();

        return redirect()->route('admin.payroll.holidays')->with('status', 'Public holiday removed.');
    }

    public function exportRun(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $ctx = $this->pageContext($request);

        $data = $request->validate([
            'fortnight_start' => ['required', 'date'],
        ]);

        $fortnightStart = AdminPayroll::normalizeFortnightStart($data['fortnight_start']);
        $fortnightEnd = AdminPayroll::fortnightEndForStart($fortnightStart);

        $employees = $this->loadPayrollEmployees($ctx['connection'], $fortnightStart, $fortnightEnd);
        $previewRows = AdminPayroll::previewFortnight(
            $ctx['connection'],
            $employees,
            $fortnightStart,
            $fortnightEnd,
            PublicHoliday::on($ctx['connection'])->whereBetween('holiday_date', [$fortnightStart, $fortnightEnd])->get(),
            TimesheetApproval::on($ctx['connection'])->get(),
        );

        $filename = 'payroll-'.$fortnightStart.'-'.$fortnightEnd.'.csv';

        return response()->streamDownload(function () use ($previewRows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee', 'Email', 'Worked hrs', 'Roster hrs', 'Variance', 'Gross pay', 'Line', 'Hours', 'Rate', 'Amount']);
            foreach (AdminPayroll::payableRows($previewRows) as $row) {
                /** @var Employee $emp */
                $emp = $row['employee'];
                foreach ($row['lines'] as $line) {
                    fputcsv($out, [
                        $emp->full_legal_name ?: $emp->email,
                        $emp->email,
                        $row['total_hours'],
                        $row['scheduled_hours'] ?? 0,
                        $row['roster_variance'] ?? '',
                        $row['total_amount'],
                        $line['label'] ?? '',
                        $line['hours'] ?? 0,
                        $line['rate'] ?? 0,
                        $line['amount'] ?? 0,
                    ]);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function loadPayrollEmployees(string $connection, string $fortnightStart, string $fortnightEnd): \Illuminate\Support\Collection
    {
        $tz = \App\Support\DisplayTimezone::name();
        $entriesFrom = \Carbon\Carbon::parse($fortnightStart, $tz)->startOfDay()->utc()->subDay();
        $entriesTo = \Carbon\Carbon::parse($fortnightEnd, $tz)->endOfDay()->utc()->addDay();

        return Employee::on($connection)
            ->where('employment_status', 'active')
            ->with([
                'timeClockEntries' => static function ($query) use ($entriesFrom, $entriesTo): void {
                    $query->with('shift')
                        ->whereBetween('clocked_at', [$entriesFrom, $entriesTo])
                        ->orderBy('clocked_at');
                },
                'scheduleShifts' => static function ($query) use ($fortnightStart, $fortnightEnd): void {
                    $query->whereBetween('scheduled_date', [$fortnightStart, $fortnightEnd]);
                },
                'leaveRecords' => static function ($query) use ($fortnightStart, $fortnightEnd): void {
                    $query->where('status', 'pending')
                        ->whereBetween('leave_date', [$fortnightStart, $fortnightEnd]);
                },
            ])
            ->orderBy('full_legal_name')
            ->orderBy('email')
            ->get();
    }

    /**
     * @return array{company: \App\Models\Company, connection: string}
     */
    private function pageContext(Request $request): array
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();

        return [
            'company' => $company,
            'connection' => $company->tenant_connection,
        ];
    }
}
