@php
    /** @var list<array{start: string, end: string, label: string, has_run: bool, run_id: int|null, status: string|null}> $recentFortnights */
    /** @var list<array<string, mixed>> $previewRows */
    /** @var \Illuminate\Support\Collection<int, \App\Models\PublicHoliday> $publicHolidays */
    /** @var \App\Models\PayrollRun|null $currentRun */
    use App\Support\AdminPayroll;
    use App\Support\PayrollRateTypes;

    $payPeriodHistory = (int) ($fortnightHistory ?? 8);
    $payPeriodQuery = $payPeriodHistory > 8 ? ['history' => $payPeriodHistory] : [];
    $payPeriodStatus = static function (array $fn): array {
        if (! ($fn['has_run'] ?? false)) {
            return ['label' => 'Not started', 'class' => 'bg-brand-surface text-brand-text-secondary ring-brand-border'];
        }
        if (($fn['status'] ?? '') === 'finalized') {
            return ['label' => 'Finalized', 'class' => 'bg-emerald-50 text-emerald-800 ring-emerald-200'];
        }

        return ['label' => 'Draft', 'class' => 'bg-amber-50 text-amber-900 ring-amber-200'];
    };
    $selectedPayPeriod = null;
    foreach ($recentFortnights as $fn) {
        if ($fn['start'] === $fortnightStart) {
            $selectedPayPeriod = $fn;
            break;
        }
    }
    if ($selectedPayPeriod === null) {
        $selectedPayPeriod = [
            'start' => $fortnightStart,
            'label' => $fortnightLabel ?? $fortnightStart,
            'has_run' => $currentRun !== null,
            'status' => $currentRun->status ?? null,
        ];
    }
    $selectedPayStatus = $payPeriodStatus($selectedPayPeriod);
@endphp

<div class="min-w-0 space-y-4">
        <section class="min-w-0 overflow-visible rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <header class="relative z-20 flex flex-wrap items-end justify-between gap-4 rounded-t-2xl border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-5 sm:px-6">
                <div class="w-full max-w-xl">
                    <label id="pay-period-label" class="text-xs font-semibold uppercase tracking-wide text-brand-label">Pay period</label>
                    <div class="relative mt-2" data-pay-period-combo>
                        <button
                            type="button"
                            data-pay-period-toggle
                            class="flex w-full items-center gap-3 rounded-xl border border-brand-border bg-white px-3.5 py-2.5 text-left shadow-sm transition hover:border-brand-primary/35 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20"
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-labelledby="pay-period-label"
                            aria-controls="pay-period-list"
                        >
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M4.5 5.25h15a.75.75 0 01.75.75v12a.75.75 0 01-.75.75h-15a.75.75 0 01-.75-.75v-12a.75.75 0 01.75-.75z" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-brand-text">{{ $selectedPayPeriod['label'] }}</span>
                                <span class="mt-0.5 block text-xs text-brand-text-secondary">Newest first · approved timesheets only</span>
                            </span>
                            <span class="hidden shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 sm:inline-flex {{ $selectedPayStatus['class'] }}">{{ $selectedPayStatus['label'] }}</span>
                            <svg data-pay-period-chevron class="size-4 shrink-0 text-brand-text-secondary transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div
                            id="pay-period-list"
                            data-pay-period-panel
                            class="absolute left-0 right-0 z-30 mt-2 hidden overflow-hidden rounded-2xl border border-brand-border bg-white shadow-xl shadow-black/10"
                        >
                            <div class="max-h-72 overflow-y-auto p-1.5" role="listbox" aria-labelledby="pay-period-label">
                                    @foreach ($recentFortnights as $fn)
                                        @php $status = $payPeriodStatus($fn); @endphp
                                        <a
                                            href="{{ route('admin.payroll.runs', array_filter(['fortnight' => $fn['start']] + $payPeriodQuery)) }}"
                                            role="option"
                                            aria-selected="{{ $fortnightStart === $fn['start'] ? 'true' : 'false' }}"
                                            class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition {{ $fortnightStart === $fn['start'] ? 'bg-brand-primary/10 ring-1 ring-brand-primary/20' : 'hover:bg-brand-surface' }}"
                                        >
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-semibold text-brand-text">{{ $fn['label'] }}</span>
                                            </span>
                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $status['class'] }}">{{ $status['label'] }}</span>
                                        </a>
                                    @endforeach
                            </div>
                            @if ($canLoadOlderFortnights ?? false)
                                <div class="border-t border-brand-border bg-brand-surface/70 p-1.5">
                                    <a
                                        href="{{ route('admin.payroll.runs', ['fortnight' => $fortnightStart, 'history' => $payPeriodHistory + 8, 'period_menu' => 1]) }}"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold text-brand-primary transition hover:bg-white"
                                    >
                                        Load more
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if ($publicHolidays->isNotEmpty())
                        <p class="mt-3 text-xs text-brand-text-secondary">
                            <span class="font-semibold text-brand-text">Public holidays:</span>
                            @foreach ($publicHolidays as $holiday)
                                {{ $holiday->name }} ({{ \App\Support\DisplayTimezone::format($holiday->holiday_date, 'M j') }})@if (! $loop->last), @endif
                            @endforeach
                        </p>
                    @endif
                </div>
            </header>

            <div class="flex flex-wrap gap-3 border-b border-brand-border px-5 py-4 sm:px-6">
                @if ($currentRun && $currentRun->status === 'finalized')
                    <p class="text-sm text-brand-text-secondary">This pay period is finalized. The amounts below are a fresh preview and will not change the saved pay run or leave balances.</p>
                @else
                <form method="post" action="{{ route('admin.payroll.runs.generate') }}" class="inline">
                    @csrf
                    <input type="hidden" name="fortnight_start" value="{{ $fortnightStart }}" />
                    <input type="hidden" name="finalize" value="0" />
                    <button type="submit" class="inline-flex items-center rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-primary shadow-sm transition hover:bg-brand-surface">
                        Save draft
                    </button>
                </form>
                <form method="post" action="{{ route('admin.payroll.runs.generate') }}" class="inline" data-confirm="Finalize this pay period? Leave balances will be updated for included employees." data-confirm-title="Finalize pay run?" data-confirm-confirm="Finalize" data-confirm-cancel="Cancel">
                    @csrf
                    <input type="hidden" name="fortnight_start" value="{{ $fortnightStart }}" />
                    <input type="hidden" name="finalize" value="1" />
                    <button type="submit" class="inline-flex items-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                        Finalize pay run
                    </button>
                </form>
                @endif
                <div class="ml-auto flex flex-wrap gap-2">
                    <form method="post" action="{{ route('admin.payroll.runs.export') }}">
                        @csrf
                        <input type="hidden" name="fortnight_start" value="{{ $fortnightStart }}" />
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/30 hover:bg-brand-surface">
                            <svg class="size-4 text-brand-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12M12 16.5V3" /></svg>
                            Export CSV
                        </button>
                    </form>
                    <form method="post" action="{{ route('admin.payroll.runs.export-pdf') }}">
                        @csrf
                        <input type="hidden" name="fortnight_start" value="{{ $fortnightStart }}" />
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-brand-primary/25 bg-brand-primary/5 px-4 py-2.5 text-sm font-semibold text-brand-primary shadow-sm transition hover:bg-brand-primary/10">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            Export PDF
                        </button>
                    </form>
                </div>
            </div>

            @php
                $payableCount = collect($previewRows)->filter(fn ($r) => ($r['skipped_reason'] ?? null) === null && (($r['total_hours'] ?? 0) > 0 || ($r['total_amount'] ?? 0) > 0))->count();
                $grandTotal = collect($previewRows)->sum(fn ($r) => (float) ($r['total_amount'] ?? 0));
                $blockerStats = $blockerStats ?? ['payable' => $payableCount, 'blocked' => 0, 'reasons' => []];
            @endphp

            @if ($payableCount === 0 && ! empty($blockerStats['reasons']))
                <div class="mx-5 mb-0 mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 sm:mx-6">
                    <p class="text-sm font-semibold text-amber-950">This pay period is not ready</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-amber-900">
                        @foreach ($blockerStats['reasons'] as $reason => $count)
                            <li><strong>{{ $count }}</strong> employee(s) — {{ $reason }}</li>
                        @endforeach
                    </ul>
                    @if ($requireApprovedTimesheets ?? true)
                        <p class="mt-3 text-xs text-amber-900/90">Only approved shifts are paid. Open <a href="{{ route('admin.employees.time-clock') }}" class="font-semibold underline">Time clock records</a> and approve each worked shift in this pay period.</p>
                    @endif
                    <p class="mt-2 text-xs text-amber-900/90">Pay is approved hours times the hourly wage on the job title for that shift.</p>
                </div>
            @endif

            @if (count($previewRows) === 0)
                <div class="px-6 py-14 text-center">
                    <p class="text-sm font-medium text-brand-text">No active employees in this organization.</p>
                </div>
            @else
                <div class="schedule-table-scroll rounded-b-2xl" data-payrun-scroll>
                    <table class="w-full min-w-[70rem] table-fixed text-left text-sm">
                        <colgroup>
                            <col class="w-[22%]" />
                            <col class="w-[24%]" />
                            <col class="w-[10%]" />
                            <col class="w-[11%]" />
                            <col class="w-[13%]" />
                            <col class="w-[12%]" />
                            <col class="w-[8%]" />
                        </colgroup>
                        <thead class="border-b border-brand-border bg-brand-surface/80 text-xs font-semibold uppercase tracking-wide text-brand-label">
                            <tr>
                                <th class="px-4 py-3 text-left">Employee</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-right">Worked</th>
                                <th class="px-4 py-3 text-right">Scheduled</th>
                                <th class="px-4 py-3 text-right">Vs schedule</th>
                                <th class="px-4 py-3 text-right">Gross pay</th>
                                <th class="px-4 py-3 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border/80">
                            @foreach ($previewRows as $row)
                                @php
                                    /** @var \App\Models\Employee $emp */
                                    $emp = $row['employee'];
                                    $skipped = $row['skipped_reason'] ?? null;
                                    $isPayable = $skipped === null && (($row['total_hours'] ?? 0) > 0 || ($row['total_amount'] ?? 0) > 0);
                                    $payLines = $isPayable ? AdminPayroll::payableLines($row['lines'] ?? []) : [];
                                    $jobLabel = $emp->assignedJobTitle?->name ?: ($emp->job_title ?: '');
                                    if ($isPayable && $emp->assignedJobTitle?->hasHourlyWage()) {
                                        $jobLabel = trim($jobLabel.' · $'.$emp->assignedJobTitle->formattedWage().'/hr');
                                    }
                                    $varianceFull = (string) ($row['roster_variance'] ?? '—');
                                    $varianceShort = match (true) {
                                        ! $isPayable, $varianceFull === '—', $varianceFull === '' => '—',
                                        $varianceFull === 'Matches schedule' => 'On schedule',
                                        default => (string) preg_replace('/\s*hrs vs schedule$/', ' hrs', $varianceFull),
                                    };
                                    $cardLines = [];
                                    foreach ($payLines as $line) {
                                        $lineHours = round((float) ($line['hours'] ?? 0), 2);
                                        $lineRate = round((float) ($line['rate'] ?? 0), 2);
                                        $lineAmount = round((float) ($line['amount'] ?? 0), 2);
                                        $cardLines[] = [
                                            'label' => AdminPayroll::payLineLabel($line),
                                            'meta' => ($lineHours > 0 && $lineRate > 0)
                                                ? number_format($lineHours, 2).' hrs × '.AdminPayroll::formatMoney($lineRate).'/hr'
                                                : ($lineHours > 0 ? number_format($lineHours, 2).' hrs' : ''),
                                            'amount' => AdminPayroll::formatMoney($lineAmount),
                                        ];
                                    }
                                    $cardPayload = [
                                        'name' => $emp->full_legal_name ?: $emp->email,
                                        'code' => $emp->employee_code ?: '',
                                        'status' => $isPayable ? 'Ready' : 'Not included',
                                        'reason' => $isPayable ? '' : (string) ($skipped ?? 'No hours to pay'),
                                        'worked' => $isPayable ? number_format((float) $row['total_hours'], 2).' hrs' : '—',
                                        'scheduled' => ($row['scheduled_hours'] ?? 0) > 0 ? number_format((float) $row['scheduled_hours'], 2).' hrs' : '—',
                                        'variance' => $varianceShort,
                                        'total' => $isPayable ? AdminPayroll::formatMoney((float) $row['total_amount']) : '—',
                                        'lines' => $cardLines,
                                    ];
                                @endphp
                                <tr class="{{ $isPayable ? 'hover:bg-brand-surface/30' : 'bg-brand-surface/40' }}" data-pay-card="{{ json_encode($cardPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) }}">
                                    <td class="px-4 py-3.5 align-middle">
                                        <p class="truncate font-semibold text-brand-text">{{ $emp->full_legal_name ?: $emp->email }}</p>
                                        <p class="truncate text-xs text-brand-text-secondary">{{ $emp->employee_code ?: 'No code' }}</p>
                                    </td>
                                    <td class="px-4 py-3.5 align-middle">
                                        @if ($isPayable)
                                            <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">Ready</span>
                                            @if ($jobLabel !== '')
                                                <p class="mt-1 truncate text-xs text-brand-text-secondary" title="{{ $jobLabel }}">{{ $jobLabel }}</p>
                                            @endif
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">Not included</span>
                                            <p class="mt-1 truncate text-xs text-amber-900" title="{{ $skipped ?? 'No hours to pay' }}">{{ $skipped ?? 'No hours to pay' }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right align-middle font-mono tabular-nums text-brand-text">
                                        {{ $isPayable ? number_format((float) $row['total_hours'], 2) : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right align-middle font-mono tabular-nums text-brand-text-secondary">
                                        {{ ($row['scheduled_hours'] ?? 0) > 0 ? number_format((float) $row['scheduled_hours'], 2) : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right align-middle font-mono text-xs tabular-nums text-brand-text-secondary">
                                        {{ $varianceShort }}
                                    </td>
                                    <td class="pay-breakdown-trigger cursor-help whitespace-nowrap px-4 py-3.5 text-right align-middle font-mono text-sm font-semibold tabular-nums text-brand-text">
                                        {{ $isPayable ? AdminPayroll::formatMoney((float) $row['total_amount']) : '—' }}
                                    </td>
                                    <td class="px-3 py-3.5 text-right align-middle">
                                        <button
                                            type="button"
                                            class="pay-breakdown-trigger inline-flex items-center justify-center rounded-lg px-2 py-1 text-xs font-semibold text-brand-link transition hover:bg-brand-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30"
                                            aria-describedby="pay-breakdown-card"
                                        >
                                            View
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($payableCount > 0)
                        <tfoot class="border-t border-brand-border bg-brand-surface/60">
                            <tr>
                                <td colspan="5" class="px-4 py-3.5 text-right text-sm font-bold text-brand-text">Pay period total ({{ $payableCount }} employee{{ $payableCount === 1 ? '' : 's' }})</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right font-mono text-sm font-bold tabular-nums text-brand-primary">{{ AdminPayroll::formatMoney($grandTotal) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </section>
</div>

<div class="schedule-scroll-dock" data-payrun-scroll-dock aria-label="Payrun table scroll">
    <button
        type="button"
        class="inline-flex size-8 shrink-0 items-center justify-center rounded-full border border-brand-border bg-white text-sm font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface"
        data-payrun-scroll-prev
        aria-label="Scroll left"
    >←</button>
    <div class="min-w-0 flex-1">
        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-brand-label">Payrun</p>
        <input type="range" min="0" max="1" value="0" data-payrun-scroll-range aria-label="Scroll payrun columns">
    </div>
    <button
        type="button"
        class="inline-flex size-8 shrink-0 items-center justify-center rounded-full border border-brand-border bg-white text-sm font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface"
        data-payrun-scroll-next
        aria-label="Scroll right"
    >→</button>
</div>

<div
    id="pay-breakdown-card"
    class="pointer-events-none fixed z-[80] hidden w-[22rem] max-w-[calc(100vw-1.5rem)] rounded-2xl border border-brand-border bg-white p-4 shadow-2xl shadow-black/15 ring-1 ring-black/[0.04]"
    role="tooltip"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-brand-text" data-card-name></p>
            <p class="truncate text-xs text-brand-text-secondary" data-card-code></p>
        </div>
        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" data-card-status></span>
    </div>
    <p class="mt-3 hidden text-sm leading-relaxed text-amber-950" data-card-reason></p>
    <dl class="mt-3 grid grid-cols-3 gap-2 rounded-xl bg-brand-surface px-3 py-2.5 text-center" data-card-stats>
        <div>
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-brand-label">Worked</dt>
            <dd class="mt-0.5 whitespace-nowrap font-mono text-xs font-semibold tabular-nums text-brand-text" data-card-worked></dd>
        </div>
        <div>
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-brand-label">Scheduled</dt>
            <dd class="mt-0.5 whitespace-nowrap font-mono text-xs font-semibold tabular-nums text-brand-text" data-card-scheduled></dd>
        </div>
        <div>
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-brand-label">Variance</dt>
            <dd class="mt-0.5 whitespace-nowrap font-mono text-xs font-semibold tabular-nums text-brand-text" data-card-variance></dd>
        </div>
    </dl>
    <ul class="mt-3 space-y-2" data-card-lines></ul>
    <div class="mt-3 flex items-center justify-between border-t border-brand-border pt-3">
        <span class="text-xs font-semibold uppercase tracking-wide text-brand-label">Gross pay</span>
        <span class="font-mono text-base font-bold tabular-nums text-brand-primary" data-card-total></span>
    </div>
</div>

<script>
    (function () {
        var root = document.querySelector('[data-pay-period-combo]');
        if (!root) return;
        var button = root.querySelector('[data-pay-period-toggle]');
        var panel = root.querySelector('[data-pay-period-panel]');
        var chevron = root.querySelector('[data-pay-period-chevron]');
        if (!button || !panel) return;

        function setOpen(open, scrollToEnd) {
            panel.classList.toggle('hidden', !open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (chevron) chevron.classList.toggle('rotate-180', open);
            if (!open) return;
            var list = panel.querySelector('[role="listbox"]');
            if (scrollToEnd && list) {
                list.scrollTop = list.scrollHeight;
                return;
            }
            var selected = panel.querySelector('[aria-selected="true"]');
            if (selected && selected.scrollIntoView) {
                selected.scrollIntoView({ block: 'nearest' });
            }
        }

        button.addEventListener('click', function () {
            setOpen(button.getAttribute('aria-expanded') !== 'true', false);
        });

        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) setOpen(false, false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setOpen(false, false);
                hideCard();
            }
        });

        var card = document.getElementById('pay-breakdown-card');
        var cardName = card && card.querySelector('[data-card-name]');
        var cardCode = card && card.querySelector('[data-card-code]');
        var cardStatus = card && card.querySelector('[data-card-status]');
        var cardReason = card && card.querySelector('[data-card-reason]');
        var cardStats = card && card.querySelector('[data-card-stats]');
        var cardWorked = card && card.querySelector('[data-card-worked]');
        var cardScheduled = card && card.querySelector('[data-card-scheduled]');
        var cardVariance = card && card.querySelector('[data-card-variance]');
        var cardLines = card && card.querySelector('[data-card-lines]');
        var cardTotal = card && card.querySelector('[data-card-total]');
        var cardTrigger = null;

        function hideCard() {
            if (!card) return;
            card.classList.add('hidden');
            cardTrigger = null;
        }

        function showCard(trigger) {
            if (!card || !trigger) return;
            var payload = {};
            try {
                payload = JSON.parse((trigger.closest('[data-pay-card]') || trigger).getAttribute('data-pay-card') || '{}');
            } catch (error) {
                return;
            }
            cardTrigger = trigger;
            cardName.textContent = payload.name || '';
            cardCode.textContent = payload.code || '';
            cardCode.classList.toggle('hidden', !payload.code);
            cardStatus.textContent = payload.status || '';
            var ready = payload.status === 'Ready';
            cardStatus.className = 'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ' + (ready
                ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                : 'bg-amber-50 text-amber-900 ring-amber-200');
            cardReason.textContent = payload.reason || '';
            cardReason.classList.toggle('hidden', !payload.reason);
            cardStats.classList.toggle('hidden', !ready);
            cardWorked.textContent = payload.worked || '—';
            cardScheduled.textContent = payload.scheduled || '—';
            cardVariance.textContent = payload.variance || '—';
            cardLines.innerHTML = '';
            (payload.lines || []).forEach(function (line) {
                var item = document.createElement('li');
                item.className = 'rounded-xl border border-brand-border/80 px-3 py-2';
                var top = document.createElement('div');
                top.className = 'flex items-baseline justify-between gap-3';
                var label = document.createElement('span');
                label.className = 'min-w-0 truncate text-sm font-semibold text-brand-text';
                label.textContent = line.label || '';
                var amount = document.createElement('span');
                amount.className = 'shrink-0 font-mono text-sm font-semibold tabular-nums text-brand-text';
                amount.textContent = line.amount || '';
                top.appendChild(label);
                top.appendChild(amount);
                item.appendChild(top);
                if (line.meta) {
                    var meta = document.createElement('p');
                    meta.className = 'mt-0.5 text-xs text-brand-text-secondary';
                    meta.textContent = line.meta;
                    item.appendChild(meta);
                }
                cardLines.appendChild(item);
            });
            cardLines.classList.toggle('hidden', !(payload.lines || []).length);
            cardTotal.textContent = payload.total || '—';
            card.classList.remove('hidden');
            card.style.visibility = 'hidden';
            var rect = trigger.getBoundingClientRect();
            var width = card.offsetWidth;
            var height = card.offsetHeight;
            var margin = 12;
            var left = rect.right - width;
            if (left < margin) left = margin;
            if (left + width > window.innerWidth - margin) left = window.innerWidth - margin - width;
            var top = rect.bottom + 8;
            if (top + height > window.innerHeight - margin) top = Math.max(margin, rect.top - height - 8);
            card.style.left = left + 'px';
            card.style.top = top + 'px';
            card.style.visibility = '';
        }

        document.querySelectorAll('.pay-breakdown-trigger').forEach(function (trigger) {
            trigger.addEventListener('mouseenter', function () { showCard(trigger); });
            trigger.addEventListener('focus', function () { showCard(trigger); });
            trigger.addEventListener('mouseleave', hideCard);
            trigger.addEventListener('blur', hideCard);
        });
        window.addEventListener('scroll', hideCard, true);
        window.addEventListener('resize', hideCard);

        var params = new URLSearchParams(window.location.search);
        if (params.get('period_menu') === '1') {
            setOpen(true, true);
            params.delete('period_menu');
            var query = params.toString();
            window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
        }
    })();

    (function () {
        var scrollEl = document.querySelector('[data-payrun-scroll]');
        var dock = document.querySelector('[data-payrun-scroll-dock]');
        var range = document.querySelector('[data-payrun-scroll-range]');
        var prev = document.querySelector('[data-payrun-scroll-prev]');
        var next = document.querySelector('[data-payrun-scroll-next]');
        if (!scrollEl || !dock || !range) return;

        var syncing = false;

        function maxScroll() {
            return Math.max(0, scrollEl.scrollWidth - scrollEl.clientWidth);
        }

        function syncDock() {
            var max = maxScroll();
            if (max <= 2) {
                dock.classList.remove('is-visible');
                return;
            }
            dock.classList.add('is-visible');
            syncing = true;
            range.max = String(max);
            range.value = String(Math.min(max, scrollEl.scrollLeft));
            range.disabled = false;
            syncing = false;
        }

        function scrollTo(left, smooth) {
            var value = Math.max(0, Math.min(maxScroll(), left));
            if (smooth) {
                scrollEl.scrollTo({ left: value, behavior: 'smooth' });
            } else {
                scrollEl.scrollLeft = value;
            }
        }

        scrollEl.addEventListener('scroll', syncDock, { passive: true });
        window.addEventListener('resize', syncDock);
        range.addEventListener('input', function () {
            if (syncing) return;
            scrollTo(Number(range.value) || 0, false);
        });
        if (prev) {
            prev.addEventListener('click', function () {
                scrollTo(scrollEl.scrollLeft - Math.max(240, scrollEl.clientWidth * 0.7), true);
            });
        }
        if (next) {
            next.addEventListener('click', function () {
                scrollTo(scrollEl.scrollLeft + Math.max(240, scrollEl.clientWidth * 0.7), true);
            });
        }
        scrollEl.addEventListener('wheel', function (event) {
            if (maxScroll() <= 2) return;
            var mostlyHorizontal = Math.abs(event.deltaX) > Math.abs(event.deltaY);
            if (event.shiftKey || event.altKey || mostlyHorizontal) {
                event.preventDefault();
                scrollTo(scrollEl.scrollLeft + ((event.shiftKey || event.altKey) ? event.deltaY : event.deltaX), false);
            }
        }, { passive: false });

        syncDock();
    })();
</script>
