@extends('layouts.admin')

@php
    use App\Support\DisplayTimezone;
    use App\Support\PayrollRateTypes;

    /** @var \App\Models\Company $currentCompany */
    /** @var string|null $tenantError */
    /** @var string $section */

    $reportMeta = [
        'payroll' => [
            'title' => 'Payroll Summary Report',
            'subtitle' => 'Consolidated gross pay and hours by pay period.',
            'ref' => 'PAY',
        ],
        'paysheet' => [
            'title' => 'Paysheet Report',
            'subtitle' => 'Per-employee earnings calculation and deductions for a selected pay run.',
            'ref' => 'PSH',
        ],
        'timesheet' => [
            'title' => 'Timesheet & Hours Report',
            'subtitle' => 'Worked hours per employee from recorded timesheets.',
            'ref' => 'TSH',
        ],
        'leave' => [
            'title' => 'Leave Report',
            'subtitle' => 'Leave utilisation by type and recent activity.',
            'ref' => 'LVE',
        ],
        'headcount' => [
            'title' => 'Workforce Headcount Report',
            'subtitle' => 'Employee distribution by status, type and department.',
            'ref' => 'HCT',
        ],
    ];
    $meta = $reportMeta[$section] ?? ['title' => 'Report', 'subtitle' => '', 'ref' => 'RPT'];
    $periodLabel = $periodLabel ?? '';
    $filters = $filters ?? [];
    $generatedAt = DisplayTimezone::now();
    $reportRef = $meta['ref'].'-'.strtoupper(substr((string) $currentCompany->slug, 0, 4)).'-'.$generatedAt->format('Ymd');

    $th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-brand-text-secondary';
    $td = 'px-4 py-3 text-sm text-brand-text';
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $hrs = fn ($v) => number_format((float) $v, 2);
    $statBlock = 'rounded-xl border border-brand-border bg-brand-surface/40 px-4 py-3.5';
    $fInput = 'w-full rounded-lg border border-brand-border bg-white px-3 py-2 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
    $fLabel = 'mb-1 block text-[10px] font-bold uppercase tracking-widest text-brand-text-secondary';
@endphp

@section('title', $meta['title'])

@section('heading', 'Reports')

@section('subheading')
    {{ $currentCompany->name }}
@endsection

@push('scripts')
    <style>
        @media print {
            /* margin:0 collapses the page margin boxes so the browser can't print the URL/date/title header & footer */
            @page { margin: 0; }
            body { background: #fff !important; }
            #admin-sidebar, #admin-sidebar-overlay, header.sticky, .report-toolbar, .no-print { display: none !important; }
            main { padding: 0 !important; }
            .report-document { box-shadow: none !important; border: 0 !important; border-radius: 0 !important; max-width: none !important; }
            .report-document table { page-break-inside: auto; }
            .report-document tr { page-break-inside: avoid; }
        }
    </style>
@endpush

@section('content')
    @if ($tenantError !== null)
        <div data-flash-warning="{{ e('Could not reach this organization\'s database. '.$tenantError) }}" hidden></div>
    @endif

    <div class="mx-auto max-w-4xl">
        {{-- Filters (screen only) --}}
        <form method="GET" action="{{ route('admin.reports.'.$section) }}" class="report-toolbar mb-5 rounded-2xl border border-brand-border bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-4 flex items-center gap-2">
                <svg class="size-4 text-brand-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" /></svg>
                <h2 class="text-sm font-bold text-brand-text">Report filters</h2>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @if ($section === 'payroll')
                    <div>
                        <label for="f-from" class="{{ $fLabel }}">Period from</label>
                        <input type="date" id="f-from" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $fInput }}">
                    </div>
                    <div>
                        <label for="f-to" class="{{ $fLabel }}">Period to</label>
                        <input type="date" id="f-to" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $fInput }}">
                    </div>
                    <div>
                        <label for="f-status" class="{{ $fLabel }}">Status</label>
                        <select id="f-status" name="status" class="{{ $fInput }}">
                            <option value="">All statuses</option>
                            <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                            <option value="finalized" @selected(($filters['status'] ?? '') === 'finalized')>Finalized</option>
                        </select>
                    </div>
                @elseif ($section === 'paysheet')
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label for="f-run" class="{{ $fLabel }}">Pay run</label>
                        <select id="f-run" name="run_id" class="{{ $fInput }}">
                            @forelse ($runOptions as $opt)
                                <option value="{{ $opt['id'] }}" @selected((string) ($filters['run_id'] ?? '') === (string) $opt['id'])>{{ $opt['label'] }}</option>
                            @empty
                                <option value="">No pay runs available</option>
                            @endforelse
                        </select>
                    </div>
                @elseif ($section === 'timesheet')
                    <div>
                        <label for="f-from" class="{{ $fLabel }}">From</label>
                        <input type="date" id="f-from" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $fInput }}">
                    </div>
                    <div>
                        <label for="f-to" class="{{ $fLabel }}">To</label>
                        <input type="date" id="f-to" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $fInput }}">
                    </div>
                    <div>
                        <label for="f-employee" class="{{ $fLabel }}">Employee</label>
                        <select id="f-employee" name="employee_id" class="{{ $fInput }}">
                            <option value="">All employees</option>
                            @foreach ($employeeOptions as $opt)
                                <option value="{{ $opt['id'] }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $opt['id'])>{{ $opt['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="f-ts-status" class="{{ $fLabel }}">Approval status</label>
                        <select id="f-ts-status" name="status" class="{{ $fInput }}">
                            <option value="">All statuses</option>
                            <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Approved</option>
                            <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                            <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejected</option>
                        </select>
                    </div>
                @elseif ($section === 'leave')
                    <div>
                        <label for="f-from" class="{{ $fLabel }}">From</label>
                        <input type="date" id="f-from" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $fInput }}">
                    </div>
                    <div>
                        <label for="f-to" class="{{ $fLabel }}">To</label>
                        <input type="date" id="f-to" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $fInput }}">
                    </div>
                    <div>
                        <label for="f-leave-type" class="{{ $fLabel }}">Leave type</label>
                        <select id="f-leave-type" name="leave_type" class="{{ $fInput }} capitalize">
                            <option value="">All types</option>
                            @foreach ($leaveTypeOptions as $opt)
                                <option value="{{ $opt }}" @selected(($filters['leave_type'] ?? '') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="f-payment" class="{{ $fLabel }}">Payment</label>
                        <select id="f-payment" name="payment" class="{{ $fInput }}">
                            <option value="">Paid &amp; unpaid</option>
                            <option value="paid" @selected(($filters['payment'] ?? '') === 'paid')>Paid only</option>
                            <option value="unpaid" @selected(($filters['payment'] ?? '') === 'unpaid')>Unpaid only</option>
                        </select>
                    </div>
                @elseif ($section === 'headcount')
                    <div>
                        <label for="f-department" class="{{ $fLabel }}">Department</label>
                        <select id="f-department" name="department" class="{{ $fInput }} capitalize">
                            <option value="">All departments</option>
                            @foreach ($departmentOptions as $opt)
                                <option value="{{ $opt }}" @selected(($filters['department'] ?? '') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="f-emp-type" class="{{ $fLabel }}">Employment type</label>
                        <select id="f-emp-type" name="employment_type" class="{{ $fInput }} capitalize">
                            <option value="">All types</option>
                            @foreach ($typeOptions as $opt)
                                <option value="{{ $opt }}" @selected(($filters['employment_type'] ?? '') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="f-emp-status" class="{{ $fLabel }}">Employment status</label>
                        <select id="f-emp-status" name="employment_status" class="{{ $fInput }} capitalize">
                            <option value="">All statuses</option>
                            @foreach ($statusOptions as $opt)
                                <option value="{{ $opt }}" @selected(($filters['employment_status'] ?? '') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-brand-border pt-4">
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" /></svg>
                        Generate report
                    </button>
                    <a href="{{ route('admin.reports.'.$section) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text-secondary shadow-sm transition hover:bg-brand-surface hover:text-brand-text">
                        Reset
                    </a>
                </div>
                <button type="button" onclick="window.print()" class="inline-flex items-center justify-center gap-2 rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-bold text-brand-primary shadow-sm transition hover:bg-brand-surface">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" /></svg>
                    Print / Save PDF
                </button>
            </div>
        </form>

        {{-- Report document --}}
        <article class="report-document overflow-hidden rounded-2xl border border-brand-border bg-white shadow-lg shadow-black/[0.04]">
            {{-- Letterhead --}}
            <header class="border-b-2 border-brand-primary px-8 pt-8 pb-6">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <img src="{{ asset('images/crulynk-logo.png') }}?v=9" alt="" width="56" height="56" class="h-14 w-auto object-contain">
                        <div>
                            <h1 class="text-lg font-extrabold uppercase tracking-tight text-brand-text">{{ $currentCompany->name }}</h1>
                            <p class="mt-0.5 font-mono text-xs uppercase tracking-widest text-brand-text-secondary">{{ $currentCompany->slug }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center rounded-full bg-brand-primary/10 px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-brand-primary">Official Report</span>
                        <p class="mt-2 font-mono text-xs text-brand-text-secondary">Ref: {{ $reportRef }}</p>
                    </div>
                </div>

                <div class="mt-6">
                    <h2 class="text-2xl font-bold tracking-tight text-brand-text">{{ $meta['title'] }}</h2>
                    <p class="mt-1 text-sm text-brand-text-secondary">{{ $meta['subtitle'] }}</p>
                </div>

                <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-brand-border pt-5 sm:grid-cols-3">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-brand-text-secondary">Prepared for</dt>
                        <dd class="mt-1 text-sm font-semibold text-brand-text">{{ $currentCompany->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-brand-text-secondary">Reporting period</dt>
                        <dd class="mt-1 text-sm font-semibold text-brand-text">{{ $periodLabel !== '' ? $periodLabel : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-brand-text-secondary">Generated</dt>
                        <dd class="mt-1 text-sm font-semibold text-brand-text">{{ $generatedAt->format('d M Y, g:i A') }}</dd>
                    </div>
                </dl>
            </header>

            <div class="px-8 py-7">
                {{-- ===================== PAYROLL ===================== --}}
                @if ($section === 'payroll')
                    @php
                        $totalPay = $runs->sum('total_amount');
                        $totalHours = $runs->sum('total_hours');
                        $avgRunPay = $runs->count() > 0 ? $totalPay / $runs->count() : 0;
                    @endphp

                    <section>
                        <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-brand-primary">1. Key figures</h3>
                        <div class="grid gap-3 sm:grid-cols-4">
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Pay runs</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-text">{{ $runs->count() }}</dd>
                            </div>
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Total gross pay</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-primary">{{ $money($totalPay) }}</dd>
                            </div>
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Total hours</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-text">{{ $hrs($totalHours) }}</dd>
                            </div>
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Avg / pay run</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-text">{{ $money($avgRunPay) }}</dd>
                            </div>
                        </div>
                    </section>

                    <section class="mt-8">
                        <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-brand-primary">2. Pay run detail</h3>
                        <div class="overflow-hidden rounded-xl border border-brand-border">
                            <table class="min-w-full divide-y divide-brand-border">
                                <thead class="bg-brand-surface/60">
                                    <tr>
                                        <th class="{{ $th }}">Pay period</th>
                                        <th class="{{ $th }}">Status</th>
                                        <th class="{{ $th }} text-right">Employees</th>
                                        <th class="{{ $th }} text-right">Hours</th>
                                        <th class="{{ $th }} text-right">Gross pay</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-brand-border">
                                    @forelse ($runs as $run)
                                        <tr>
                                            <td class="{{ $td }} font-medium">{{ optional($run['period_start'])->format('d M') }} – {{ optional($run['period_end'])->format('d M Y') }}</td>
                                            <td class="{{ $td }} capitalize">{{ $run['status'] ?? '—' }}</td>
                                            <td class="{{ $td }} text-right tabular-nums">{{ $run['employee_count'] }}</td>
                                            <td class="{{ $td }} text-right tabular-nums">{{ $hrs($run['total_hours']) }}</td>
                                            <td class="{{ $td }} text-right font-semibold tabular-nums">{{ $money($run['total_amount']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-brand-text-secondary">No pay runs have been generated for this organization.</td></tr>
                                    @endforelse
                                </tbody>
                                @if ($runs->isNotEmpty())
                                    <tfoot class="border-t-2 border-brand-border bg-brand-surface/40">
                                        <tr>
                                            <td class="{{ $td }} font-bold" colspan="3">Total</td>
                                            <td class="{{ $td }} text-right font-bold tabular-nums">{{ $hrs($totalHours) }}</td>
                                            <td class="{{ $td }} text-right font-bold tabular-nums text-brand-primary">{{ $money($totalPay) }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </section>
                @endif

                {{-- ===================== PAYSHEET ===================== --}}
                @if ($section === 'paysheet')
                    @php
                        $summary = $summary ?? [
                            'employee_count' => 0,
                            'gross_pay' => 0,
                            'worked_hours' => 0,
                            'paid_leave_amount' => 0,
                            'allowance_amount' => 0,
                            'deductions_total' => 0,
                            'deductions_recorded' => false,
                            'net_pay' => null,
                            'accruals_value' => 0,
                        ];
                        $sheets = $sheets ?? collect();
                    @endphp

                    <section>
                        <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-brand-primary">1. Key figures</h3>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Employees on paysheet</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-text">{{ $summary['employee_count'] }}</dd>
                            </div>
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Total gross earnings</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-primary">{{ $money($summary['gross_pay']) }}</dd>
                            </div>
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Worked hours</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-text">{{ $hrs($summary['worked_hours']) }}</dd>
                            </div>
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">
                                    {{ $summary['deductions_recorded'] ? 'Total net pay' : 'Deductions recorded' }}
                                </dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-text">
                                    @if ($summary['deductions_recorded'])
                                        {{ $money($summary['net_pay'] ?? 0) }}
                                    @else
                                        None
                                    @endif
                                </dd>
                            </div>
                        </div>
                        @unless ($summary['deductions_recorded'])
                            <p class="mt-3 text-xs text-brand-text-secondary">
                                Statutory deductions (PAYG tax, superannuation, salary sacrifice, etc.) are not stored on pay runs yet.
                                This paysheet shows gross earnings and how each line was calculated. Net pay will appear once deduction lines are recorded.
                            </p>
                        @endunless
                    </section>

                    <section class="mt-8">
                        <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-brand-primary">2. Employee paysheets</h3>

                        @forelse ($sheets as $sheet)
                            @php $t = $sheet['totals']; @endphp
                            <article class="mb-6 overflow-hidden rounded-xl border border-brand-border last:mb-0">
                                <header class="border-b border-brand-border bg-brand-surface/50 px-4 py-4 sm:px-5">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h4 class="text-base font-bold text-brand-text">{{ $sheet['employee'] }}</h4>
                                            <p class="mt-0.5 text-xs text-brand-text-secondary">
                                                {{ $sheet['employee_code'] ?: 'No employee code' }}
                                                · {{ PayrollRateTypes::employmentTypeLabel($sheet['employment_type'] ?? null) }}
                                                · {{ PayrollRateTypes::awardLevelLabel($sheet['award_level'] ?? null) }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[10px] font-bold uppercase tracking-widest text-brand-text-secondary">Gross pay</p>
                                            <p class="text-lg font-extrabold tabular-nums text-brand-primary">{{ $money($t['gross_pay']) }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                                        <div class="rounded-lg border border-brand-border bg-white px-3 py-2">
                                            <p class="text-[10px] font-bold uppercase tracking-widest text-brand-text-secondary">Ordinary / base</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums">{{ $money($t['ordinary_amount']) }}</p>
                                        </div>
                                        <div class="rounded-lg border border-brand-border bg-white px-3 py-2">
                                            <p class="text-[10px] font-bold uppercase tracking-widest text-brand-text-secondary">Penalty / weekend / PH</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums">{{ $money($t['penalty_amount']) }}</p>
                                        </div>
                                        <div class="rounded-lg border border-brand-border bg-white px-3 py-2">
                                            <p class="text-[10px] font-bold uppercase tracking-widest text-brand-text-secondary">Overtime</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums">{{ $money($t['overtime_amount']) }}</p>
                                        </div>
                                        <div class="rounded-lg border border-brand-border bg-white px-3 py-2">
                                            <p class="text-[10px] font-bold uppercase tracking-widest text-brand-text-secondary">Allowances</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums">{{ $money($t['allowance_amount']) }}</p>
                                        </div>
                                        <div class="rounded-lg border border-brand-border bg-white px-3 py-2">
                                            <p class="text-[10px] font-bold uppercase tracking-widest text-brand-text-secondary">Paid leave</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums">{{ $money($t['paid_leave_amount']) }}</p>
                                        </div>
                                    </div>
                                </header>

                                <div class="px-4 py-4 sm:px-5">
                                    <h5 class="mb-2 text-[11px] font-bold uppercase tracking-widest text-brand-primary">Earnings — how pay was calculated</h5>
                                    <div class="overflow-hidden rounded-lg border border-brand-border">
                                        <table class="min-w-full divide-y divide-brand-border">
                                            <thead class="bg-brand-surface/60">
                                                <tr>
                                                    <th class="{{ $th }}">Earning line</th>
                                                    <th class="{{ $th }} text-right">Hours</th>
                                                    <th class="{{ $th }} text-right">Rate</th>
                                                    <th class="{{ $th }} text-right">Amount</th>
                                                    <th class="{{ $th }}">Calculation</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-brand-border">
                                                @forelse ($t['earnings'] as $line)
                                                    <tr>
                                                        <td class="{{ $td }} font-medium">{{ $line['label'] }}</td>
                                                        <td class="{{ $td }} text-right tabular-nums">{{ $line['hours'] > 0 ? $hrs($line['hours']) : '—' }}</td>
                                                        <td class="{{ $td }} text-right tabular-nums">{{ $line['rate'] > 0 ? $money($line['rate']) : '—' }}</td>
                                                        <td class="{{ $td }} text-right font-semibold tabular-nums">{{ $money($line['amount']) }}</td>
                                                        <td class="{{ $td }} text-xs text-brand-text-secondary">
                                                            @if ($line['hours'] > 0 && $line['rate'] > 0)
                                                                {{ $hrs($line['hours']) }}h × {{ $money($line['rate']) }} = {{ $money($line['amount']) }}
                                                            @elseif ($line['amount'] > 0)
                                                                Fixed amount {{ $money($line['amount']) }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-brand-text-secondary">No earnings lines for this employee.</td></tr>
                                                @endforelse
                                            </tbody>
                                            @if (! empty($t['earnings']))
                                                <tfoot class="border-t-2 border-brand-border bg-brand-surface/40">
                                                    <tr>
                                                        <td class="{{ $td }} font-bold" colspan="3">Gross earnings</td>
                                                        <td class="{{ $td }} text-right font-bold tabular-nums text-brand-primary">{{ $money($t['gross_pay']) }}</td>
                                                        <td class="{{ $td }}"></td>
                                                    </tr>
                                                </tfoot>
                                            @endif
                                        </table>
                                    </div>

                                    <h5 class="mb-2 mt-5 text-[11px] font-bold uppercase tracking-widest text-brand-primary">Deductions</h5>
                                    <div class="overflow-hidden rounded-lg border border-brand-border">
                                        @if ($t['deductions_recorded'] && ! empty($t['deductions']))
                                            <table class="min-w-full divide-y divide-brand-border">
                                                <thead class="bg-brand-surface/60">
                                                    <tr>
                                                        <th class="{{ $th }}">Deduction</th>
                                                        <th class="{{ $th }} text-right">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-brand-border">
                                                    @foreach ($t['deductions'] as $line)
                                                        <tr>
                                                            <td class="{{ $td }} font-medium">{{ $line['label'] }}</td>
                                                            <td class="{{ $td }} text-right font-semibold tabular-nums">{{ $money($line['amount']) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="border-t-2 border-brand-border bg-brand-surface/40">
                                                    <tr>
                                                        <td class="{{ $td }} font-bold">Total deductions</td>
                                                        <td class="{{ $td }} text-right font-bold tabular-nums">{{ $money($t['deductions_total']) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="{{ $td }} font-bold">Net pay</td>
                                                        <td class="{{ $td }} text-right font-bold tabular-nums text-brand-primary">{{ $money($t['net_pay'] ?? 0) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        @else
                                            <div class="px-4 py-5 text-sm text-brand-text-secondary">
                                                No deductions recorded for this employee on this pay run.
                                                Gross pay remains {{ $money($t['gross_pay']) }} until tax, super or other deductions are captured.
                                            </div>
                                        @endif
                                    </div>

                                    @if (! empty($t['accruals']) || ! empty($t['unpaid_leave']))
                                        <h5 class="mb-2 mt-5 text-[11px] font-bold uppercase tracking-widest text-brand-primary">Leave tracking (not included in gross pay)</h5>
                                        <div class="overflow-hidden rounded-lg border border-brand-border">
                                            <table class="min-w-full divide-y divide-brand-border">
                                                <thead class="bg-brand-surface/60">
                                                    <tr>
                                                        <th class="{{ $th }}">Item</th>
                                                        <th class="{{ $th }} text-right">Hours</th>
                                                        <th class="{{ $th }} text-right">Value</th>
                                                        <th class="{{ $th }}">Note</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-brand-border">
                                                    @foreach ($t['accruals'] as $line)
                                                        <tr>
                                                            <td class="{{ $td }} font-medium">{{ $line['label'] }}</td>
                                                            <td class="{{ $td }} text-right tabular-nums">{{ $hrs($line['hours']) }}</td>
                                                            <td class="{{ $td }} text-right tabular-nums">{{ $money($line['amount']) }}</td>
                                                            <td class="{{ $td }} text-xs text-brand-text-secondary">Balance accrual only — excluded from gross</td>
                                                        </tr>
                                                    @endforeach
                                                    @foreach ($t['unpaid_leave'] as $line)
                                                        <tr>
                                                            <td class="{{ $td }} font-medium">{{ $line['label'] }}</td>
                                                            <td class="{{ $td }} text-right tabular-nums">{{ $hrs($line['hours']) }}</td>
                                                            <td class="{{ $td }} text-right tabular-nums">{{ $money(0) }}</td>
                                                            <td class="{{ $td }} text-xs text-brand-text-secondary">Unpaid attendance tracking</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-brand-border px-4 py-12 text-center text-sm text-brand-text-secondary">
                                @if ($selectedRun ?? null)
                                    This pay run has no employee lines yet. Generate a draft or finalized run under Payroll → Payrun first.
                                @else
                                    No pay runs have been generated for this organization.
                                @endif
                            </div>
                        @endforelse
                    </section>
                @endif

                {{-- ===================== TIMESHEET ===================== --}}
                @if ($section === 'timesheet')
                    @php $totalHours = $rows->sum('hours'); @endphp

                    <section>
                        <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-brand-primary">1. Key figures</h3>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Total hours</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-primary">{{ $hrs($totalHours) }}</dd>
                            </div>
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Employees with hours</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-text">{{ $rows->count() }}</dd>
                            </div>
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Days worked</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-text">{{ $rows->sum('days') }}</dd>
                            </div>
                        </div>
                    </section>

                    <section class="mt-8">
                        <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-brand-primary">2. Hours by employee</h3>
                        <div class="overflow-hidden rounded-xl border border-brand-border">
                            <table class="min-w-full divide-y divide-brand-border">
                                <thead class="bg-brand-surface/60">
                                    <tr>
                                        <th class="{{ $th }} w-10">#</th>
                                        <th class="{{ $th }}">Employee</th>
                                        <th class="{{ $th }} text-right">Days</th>
                                        <th class="{{ $th }} text-right">Sessions</th>
                                        <th class="{{ $th }} text-right">Hours</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-brand-border">
                                    @forelse ($rows as $i => $row)
                                        <tr>
                                            <td class="{{ $td }} tabular-nums text-brand-text-secondary">{{ $i + 1 }}</td>
                                            <td class="{{ $td }} font-medium">{{ $row['employee'] }}</td>
                                            <td class="{{ $td }} text-right tabular-nums">{{ $row['days'] }}</td>
                                            <td class="{{ $td }} text-right tabular-nums">{{ $row['sessions'] }}</td>
                                            <td class="{{ $td }} text-right font-semibold tabular-nums">{{ $hrs($row['hours']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-brand-text-secondary">No timesheets were recorded in this period.</td></tr>
                                    @endforelse
                                </tbody>
                                @if ($rows->isNotEmpty())
                                    <tfoot class="border-t-2 border-brand-border bg-brand-surface/40">
                                        <tr>
                                            <td class="{{ $td }} font-bold" colspan="4">Total hours</td>
                                            <td class="{{ $td }} text-right font-bold tabular-nums text-brand-primary">{{ $hrs($totalHours) }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </section>
                @endif

                {{-- ===================== LEAVE ===================== --}}
                @if ($section === 'leave')
                    @php
                        $totalLeaveHours = $byType->sum('total_hours');
                        $totalLeaveAmount = $byType->sum('total_amount');
                        $totalRecords = $byType->sum('records');
                    @endphp

                    <section>
                        <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-brand-primary">1. Key figures</h3>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Leave records</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-text">{{ $totalRecords }}</dd>
                            </div>
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Total leave hours</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-primary">{{ $hrs($totalLeaveHours) }}</dd>
                            </div>
                            <div class="{{ $statBlock }}">
                                <dt class="text-xs text-brand-text-secondary">Total paid leave</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums text-brand-text">{{ $money($totalLeaveAmount) }}</dd>
                            </div>
                        </div>
                    </section>

                    <section class="mt-8">
                        <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-brand-primary">2. Leave by type</h3>
                        <div class="overflow-hidden rounded-xl border border-brand-border">
                            <table class="min-w-full divide-y divide-brand-border">
                                <thead class="bg-brand-surface/60">
                                    <tr>
                                        <th class="{{ $th }}">Leave type</th>
                                        <th class="{{ $th }} text-right">Records</th>
                                        <th class="{{ $th }} text-right">Hours</th>
                                        <th class="{{ $th }} text-right">Paid amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-brand-border">
                                    @forelse ($byType as $t)
                                        <tr>
                                            <td class="{{ $td }} font-medium capitalize">{{ $t['leave_type'] }}</td>
                                            <td class="{{ $td }} text-right tabular-nums">{{ $t['records'] }}</td>
                                            <td class="{{ $td }} text-right tabular-nums">{{ $hrs($t['total_hours']) }}</td>
                                            <td class="{{ $td }} text-right font-semibold tabular-nums">{{ $money($t['total_amount']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-12 text-center text-sm text-brand-text-secondary">No leave has been recorded for this organization.</td></tr>
                                    @endforelse
                                </tbody>
                                @if ($byType->isNotEmpty())
                                    <tfoot class="border-t-2 border-brand-border bg-brand-surface/40">
                                        <tr>
                                            <td class="{{ $td }} font-bold">Total</td>
                                            <td class="{{ $td }} text-right font-bold tabular-nums">{{ $totalRecords }}</td>
                                            <td class="{{ $td }} text-right font-bold tabular-nums">{{ $hrs($totalLeaveHours) }}</td>
                                            <td class="{{ $td }} text-right font-bold tabular-nums text-brand-primary">{{ $money($totalLeaveAmount) }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </section>

                    <section class="mt-8">
                        <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-brand-primary">3. Recent leave activity</h3>
                        <div class="overflow-hidden rounded-xl border border-brand-border">
                            <table class="min-w-full divide-y divide-brand-border">
                                <thead class="bg-brand-surface/60">
                                    <tr>
                                        <th class="{{ $th }}">Date</th>
                                        <th class="{{ $th }}">Employee</th>
                                        <th class="{{ $th }}">Type</th>
                                        <th class="{{ $th }}">Paid</th>
                                        <th class="{{ $th }} text-right">Hours</th>
                                        <th class="{{ $th }}">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-brand-border">
                                    @forelse ($recent as $r)
                                        <tr>
                                            <td class="{{ $td }} tabular-nums">{{ optional($r['leave_date'])->format('d M Y') ?? '—' }}</td>
                                            <td class="{{ $td }} font-medium">{{ $r['employee'] }}</td>
                                            <td class="{{ $td }} capitalize">{{ $r['leave_type'] }}</td>
                                            <td class="{{ $td }}">{{ $r['is_paid'] ? 'Paid' : 'Unpaid' }}</td>
                                            <td class="{{ $td }} text-right tabular-nums">{{ $hrs($r['hours']) }}</td>
                                            <td class="{{ $td }} capitalize">{{ $r['status'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-brand-text-secondary">No recent leave activity.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                {{-- ===================== HEADCOUNT ===================== --}}
                @if ($section === 'headcount')
                    @php
                        $breakdowns = [
                            ['n' => '2', 'title' => 'Employment status', 'rows' => $byStatus],
                            ['n' => '3', 'title' => 'Employment type', 'rows' => $byType],
                            ['n' => '4', 'title' => 'Department', 'rows' => $byDepartment],
                        ];
                    @endphp

                    <section>
                        <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-brand-primary">1. Total headcount</h3>
                        <div class="rounded-xl border border-brand-border bg-brand-surface/40 px-6 py-5">
                            <span class="block text-4xl font-extrabold tabular-nums text-brand-primary">{{ $total }}</span>
                            <span class="mt-1 block text-sm text-brand-text-secondary">Employees on record as at {{ $generatedAt->format('d M Y') }}</span>
                        </div>
                    </section>

                    @foreach ($breakdowns as $b)
                        <section class="mt-8">
                            <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-brand-primary">{{ $b['n'] }}. Breakdown — {{ $b['title'] }}</h3>
                            <div class="overflow-hidden rounded-xl border border-brand-border">
                                <table class="min-w-full divide-y divide-brand-border">
                                    <thead class="bg-brand-surface/60">
                                        <tr>
                                            <th class="{{ $th }}">{{ $b['title'] }}</th>
                                            <th class="{{ $th }} text-right">Employees</th>
                                            <th class="{{ $th }} w-1/3">Share</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-brand-border">
                                        @forelse ($b['rows'] as $row)
                                            @php $pct = $total > 0 ? round(($row['count'] / $total) * 100) : 0; @endphp
                                            <tr>
                                                <td class="{{ $td }} font-medium capitalize">{{ $row['label'] }}</td>
                                                <td class="{{ $td }} text-right tabular-nums">{{ $row['count'] }}</td>
                                                <td class="{{ $td }}">
                                                    <div class="flex items-center gap-3">
                                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-brand-surface">
                                                            <div class="h-full rounded-full bg-brand-primary" style="width: {{ $pct }}%"></div>
                                                        </div>
                                                        <span class="w-9 shrink-0 text-right text-xs font-semibold tabular-nums text-brand-text-secondary">{{ $pct }}%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="px-4 py-12 text-center text-sm text-brand-text-secondary">No data available.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endforeach
                @endif
            </div>

            {{-- Footer --}}
            <footer class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-brand-border bg-brand-surface/30 px-8 py-4 text-[11px] text-brand-text-secondary">
                <span>Confidential — prepared for {{ $currentCompany->name }}.</span>
                <span>Generated by {{ config('app.name', 'CruLynk') }} · {{ $generatedAt->format('d M Y, g:i A') }} · Ref {{ $reportRef }}</span>
            </footer>
        </article>
    </div>
@endsection
