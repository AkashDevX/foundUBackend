@php
    /** @var list<array{start: string, end: string, label: string, has_run: bool, run_id: int|null, status: string|null}> $recentFortnights */
    /** @var list<array<string, mixed>> $previewRows */
    /** @var \Illuminate\Support\Collection<int, \App\Models\PublicHoliday> $publicHolidays */
    /** @var \App\Models\PayrollRun|null $currentRun */
    use App\Support\AdminPayroll;
    use App\Support\PayrollRateTypes;
@endphp

<div class="grid gap-6 xl:grid-cols-[minmax(0,17rem)_1fr]">
    <aside class="space-y-4">
        <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
            <header class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-4 py-4">
                <h2 class="text-sm font-bold text-brand-text">Fortnight</h2>
            </header>
            <nav class="divide-y divide-brand-border/80 p-2">
                @foreach ($recentFortnights as $fn)
                    <a
                        href="{{ route('admin.payroll.runs', ['fortnight' => $fn['start']]) }}"
                        class="block rounded-xl px-3 py-3 transition {{ $fortnightStart === $fn['start'] ? 'bg-brand-primary/10 ring-1 ring-brand-primary/25' : 'hover:bg-brand-surface/80' }}"
                    >
                        <p class="text-sm font-semibold text-brand-text">{{ $fn['label'] }}</p>
                        @if ($fn['has_run'])
                            <p class="mt-0.5 text-xs {{ $fn['status'] === 'finalized' ? 'text-emerald-700' : 'text-amber-800' }}">
                                {{ $fn['status'] === 'finalized' ? 'Finalized' : 'Draft saved' }}
                            </p>
                        @else
                            <p class="mt-0.5 text-xs text-brand-text-secondary">Not generated</p>
                        @endif
                    </a>
                @endforeach
            </nav>
        </section>

        @if ($publicHolidays->isNotEmpty())
            <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
                <header class="border-b border-brand-border px-4 py-3">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-brand-label">Public holidays in period</h3>
                </header>
                <ul class="divide-y divide-brand-border/80 px-4 py-2 text-sm">
                    @foreach ($publicHolidays as $holiday)
                        <li class="py-2">
                            <span class="font-medium text-brand-text">{{ $holiday->name }}</span>
                            <span class="block text-xs text-brand-text-secondary">{{ \App\Support\DisplayTimezone::format($holiday->holiday_date, 'M j, Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </aside>

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <header class="flex flex-wrap items-start justify-between gap-4 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-5 sm:px-6">
                <div>
                    <h2 class="text-base font-bold text-brand-text">Fortnightly payroll breakdown</h2>
                    <p class="mt-1 text-sm text-brand-text-secondary">
                        {{ \App\Support\DisplayTimezone::format(\Carbon\Carbon::parse($fortnightStart), 'M j') }}
                        –
                        {{ \App\Support\DisplayTimezone::format(\Carbon\Carbon::parse($fortnightEnd), 'M j, Y') }}
                        · Based on approved weekly timesheets and clock punches.
                    </p>
                </div>
                @if ($currentRun)
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $currentRun->status === 'finalized' ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-amber-50 text-amber-900 ring-amber-200' }}">
                        {{ $currentRun->status === 'finalized' ? 'Finalized' : 'Draft' }}
                    </span>
                @endif
            </header>

            <div class="flex flex-wrap gap-3 border-b border-brand-border px-5 py-4 sm:px-6">
                <form method="post" action="{{ route('admin.payroll.runs.generate') }}" class="inline">
                    @csrf
                    <input type="hidden" name="fortnight_start" value="{{ $fortnightStart }}" />
                    <input type="hidden" name="finalize" value="0" />
                    <button type="submit" class="inline-flex items-center rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-primary shadow-sm transition hover:bg-brand-surface">
                        Save draft run
                    </button>
                </form>
                <form method="post" action="{{ route('admin.payroll.runs.generate') }}" class="inline" data-confirm="Finalize this pay run? Leave balances will be updated for included employees." data-confirm-title="Finalize payroll?" data-confirm-confirm="Finalize" data-confirm-cancel="Cancel">
                    @csrf
                    <input type="hidden" name="fortnight_start" value="{{ $fortnightStart }}" />
                    <input type="hidden" name="finalize" value="1" />
                    <button type="submit" class="inline-flex items-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                        Generate &amp; finalize
                    </button>
                </form>
                <form method="post" action="{{ route('admin.payroll.runs.export') }}" class="inline">
                    @csrf
                    <input type="hidden" name="fortnight_start" value="{{ $fortnightStart }}" />
                    <button type="submit" class="inline-flex items-center rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text shadow-sm transition hover:bg-brand-surface">
                        Export CSV
                    </button>
                </form>
            </div>

            @php
                $payableCount = collect($previewRows)->filter(fn ($r) => ($r['skipped_reason'] ?? null) === null && (($r['total_hours'] ?? 0) > 0 || ($r['total_amount'] ?? 0) > 0))->count();
                $grandTotal = collect($previewRows)->sum(fn ($r) => (float) ($r['total_amount'] ?? 0));
                $blockerStats = $blockerStats ?? ['payable' => $payableCount, 'blocked' => 0, 'reasons' => []];
            @endphp

            @if ($payableCount === 0 && ! empty($blockerStats['reasons']))
                <div class="mx-5 mb-0 mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 sm:mx-6">
                    <p class="text-sm font-semibold text-amber-950">Why this fortnight cannot be generated yet</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-amber-900">
                        @foreach ($blockerStats['reasons'] as $reason => $count)
                            <li><strong>{{ $count }}</strong> employee(s) — {{ $reason }}</li>
                        @endforeach
                    </ul>
                    @if ($requireApprovedTimesheets ?? true)
                        <p class="mt-3 text-xs text-amber-900/90">Payroll only counts clock time from <strong>HR-approved</strong> weeks. A fortnight spans <strong>two</strong> Mon–Sun weeks — approve each week separately under <a href="{{ route('admin.employees.time-clock') }}" class="font-semibold underline">Time clock records → Timesheet approval</a>.</p>
                    @endif
                    <p class="mt-2 text-xs text-amber-900/90">Set <strong>Employment type</strong> and <strong>Award level</strong> on each employee under their registration profile → Payroll Information.</p>
                </div>
            @endif

            @if (count($previewRows) === 0)
                <div class="px-6 py-14 text-center">
                    <p class="text-sm font-medium text-brand-text">No active employees in this organization.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-brand-border bg-brand-surface/80 text-xs font-semibold uppercase tracking-wide text-brand-label">
                            <tr>
                                <th class="px-5 py-3">Employee</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Worked</th>
                                <th class="px-5 py-3 text-right">Roster</th>
                                <th class="px-5 py-3">Roster vs actual</th>
                                <th class="px-5 py-3 text-right">Gross pay</th>
                                <th class="px-5 py-3">Breakdown</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border/80">
                            @foreach ($previewRows as $row)
                                @php
                                    /** @var \App\Models\Employee $emp */
                                    $emp = $row['employee'];
                                    $skipped = $row['skipped_reason'] ?? null;
                                    $isPayable = $skipped === null && (($row['total_hours'] ?? 0) > 0 || ($row['total_amount'] ?? 0) > 0);
                                @endphp
                                <tr class="{{ $isPayable ? 'hover:bg-brand-surface/20' : 'bg-brand-surface/40' }}">
                                    <td class="px-5 py-4 align-top">
                                        <p class="font-semibold text-brand-text">{{ $emp->full_legal_name ?: $emp->email }}</p>
                                        <p class="text-xs text-brand-text-secondary">{{ $emp->employee_code ?: 'No code' }}</p>
                                    </td>
                                    <td class="px-5 py-4 align-top text-xs">
                                        @if ($isPayable)
                                            <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-800 ring-1 ring-emerald-200">Ready</span>
                                            <p class="mt-1 text-brand-text-secondary">{{ PayrollRateTypes::employmentTypeLabel($emp->employment_type) }} · {{ PayrollRateTypes::awardLevelLabel($emp->award_level) }}</p>
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-900 ring-1 ring-amber-200">Blocked</span>
                                            <p class="mt-1 text-amber-900">{{ $skipped ?? 'No payable hours' }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 align-top text-right font-mono tabular-nums">
                                        {{ $isPayable ? number_format((float) $row['total_hours'], 2) : '—' }}
                                    </td>
                                    <td class="px-5 py-4 align-top text-right font-mono tabular-nums text-brand-text-secondary">
                                        {{ ($row['scheduled_hours'] ?? 0) > 0 ? number_format((float) $row['scheduled_hours'], 2) : '—' }}
                                    </td>
                                    <td class="px-5 py-4 align-top text-xs text-brand-text-secondary">
                                        {{ $isPayable ? ($row['roster_variance'] ?? '—') : '—' }}
                                    </td>
                                    <td class="px-5 py-4 align-top text-right font-mono tabular-nums font-semibold text-brand-text">
                                        {{ $isPayable ? AdminPayroll::formatMoney((float) $row['total_amount']) : '—' }}
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        @if ($isPayable && ! empty($row['lines']))
                                            <details class="group">
                                                <summary class="cursor-pointer text-xs font-semibold text-brand-link hover:underline">View lines ({{ count($row['lines']) }})</summary>
                                                <ul class="mt-2 space-y-1 text-xs text-brand-text-secondary">
                                                    @foreach ($row['lines'] as $line)
                                                        <li class="flex justify-between gap-4 border-b border-brand-border/50 py-1 last:border-0">
                                                            <span>{{ $line['label'] }}</span>
                                                            <span class="shrink-0 font-mono tabular-nums">
                                                                @if (($line['hours'] ?? 0) > 0)
                                                                    {{ number_format((float) $line['hours'], 2) }}h × {{ AdminPayroll::formatMoney((float) $line['rate']) }}
                                                                @endif
                                                                @if (($line['amount'] ?? 0) > 0)
                                                                    = {{ AdminPayroll::formatMoney((float) $line['amount']) }}
                                                                @elseif (($line['hours'] ?? 0) > 0 && ($line['rate'] ?? 0) == 0)
                                                                    (accrual)
                                                                @endif
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @else
                                            <span class="text-xs text-brand-text-secondary">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($payableCount > 0)
                        <tfoot class="border-t border-brand-border bg-brand-surface/60">
                            <tr>
                                <td colspan="5" class="px-5 py-4 text-right text-sm font-bold text-brand-text">Fortnight total ({{ $payableCount }} employee{{ $payableCount === 1 ? '' : 's' }})</td>
                                <td class="px-5 py-4 text-right font-mono text-sm font-bold text-brand-primary">{{ AdminPayroll::formatMoney($grandTotal) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </section>
    </div>
</div>
