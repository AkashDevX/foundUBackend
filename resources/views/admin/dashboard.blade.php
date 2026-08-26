@extends('layouts.admin')

@section('title', 'Dashboard')

@section('heading', 'Dashboard')

@section('subheading')
    {{ $currentCompany->name }}
@endsection

@section('content')
    @php
        /** @var \App\Models\Company $currentCompany */
        /** @var string|null $tenantError */
        /** @var array{sections: list<array>, alert_count: int} $notifications */
        use App\Support\DisplayTimezone;
        /** @var int $statsTotal */
        /** @var int $statsPending */
        /** @var int $statsActive */
        /** @var int $statsDeclined */
        /** @var array<string, list<array<string, mixed>>> $timeOffLeaveBalances */
        /** @var int|null $openTimeOffRequestId */

        $severityStyles = [
            'urgent' => 'border-l-red-500 bg-red-50/60',
            'warning' => 'border-l-amber-500 bg-amber-50/50',
            'info' => 'border-l-brand-primary-light bg-brand-surface/40',
            'success' => 'border-l-emerald-500 bg-emerald-50/50',
        ];

        $severityDots = [
            'urgent' => 'bg-red-500',
            'warning' => 'bg-amber-500',
            'info' => 'bg-brand-primary-light',
            'success' => 'bg-emerald-500',
        ];

        $timeOffReviewsById = [];
        foreach ($notifications['sections'] ?? [] as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (! empty($item['time_off_review']['id'])) {
                    $timeOffReviewsById[(int) $item['time_off_review']['id']] = $item['time_off_review'];
                }
            }
        }
    @endphp

    @if ($tenantError !== null)
        <div data-flash-warning="{{ e('Could not reach this organization\'s database. '.$tenantError) }}" hidden></div>
    @endif

    <div class="mb-6 overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
        <div class="border-b border-brand-border border-l-4 border-l-brand-primary bg-white px-5 py-4 sm:px-6 sm:py-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-brand-text sm:text-2xl">{{ $currentCompany->name }}</h2>
                    <p class="mt-1.5 text-sm text-brand-text-secondary">
                        <time datetime="{{ DisplayTimezone::now()->toDateString() }}">{{ DisplayTimezone::now()->format('l, F j, Y') }}</time>
                        <span class="px-1.5 text-brand-border" aria-hidden="true">·</span>
                        <span class="font-mono text-xs text-brand-text-secondary/90">{{ $currentCompany->slug }}</span>
                    </p>
                </div>
                @if (($notifications['alert_count'] ?? 0) > 0)
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-950 ring-1 ring-amber-200/80">
                        {{ $notifications['alert_count'] }} alert{{ $notifications['alert_count'] === 1 ? '' : 's' }}
                    </span>
                @endif
            </div>
        </div>

        <div class="flex flex-col divide-y divide-brand-border sm:flex-row sm:divide-x sm:divide-y-0">
            <div class="min-w-0 flex-1 px-5 py-3.5 sm:px-5 sm:py-4">
                <span class="block text-2xl font-medium tabular-nums text-brand-text sm:text-3xl">{{ $statsTotal }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Total employees</span>
            </div>
            <a
                href="{{ route('admin.registrations.index', ['status' => 'pending']) }}"
                class="min-w-0 flex-1 px-5 py-3.5 text-left transition hover:bg-brand-surface/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-primary sm:px-5 sm:py-4"
            >
                <span class="block text-2xl font-medium tabular-nums text-brand-primary-light sm:text-3xl">{{ $statsPending }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Pending registrations</span>
            </a>
            <div class="min-w-0 flex-1 px-5 py-3.5 sm:px-5 sm:py-4">
                <span class="block text-2xl font-medium tabular-nums text-brand-primary sm:text-3xl">{{ $statsActive }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Active employees</span>
            </div>
            <div class="min-w-0 flex-1 px-5 py-3.5 sm:px-5 sm:py-4">
                <span class="block text-2xl font-medium tabular-nums text-brand-text sm:text-3xl">{{ $statsDeclined }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Declined</span>
            </div>
        </div>
    </div>

    <section class="space-y-4">
        @foreach ($notifications['sections'] ?? [] as $section)
            <div class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-brand-border bg-brand-surface/50 px-5 py-4 sm:px-6">
                    <h3 class="text-base font-bold text-brand-text">{{ $section['title'] }}</h3>
                    @if (! ($section['unavailable'] ?? false))
                        <span class="text-xs font-semibold uppercase tracking-wide text-brand-label">
                            {{ $section['total_count'] }} {{ $section['total_count'] === 1 ? 'item' : 'items' }}
                        </span>
                    @endif
                </div>

                @if ($section['unavailable'] ?? false)
                    <div class="px-5 py-6 text-sm text-brand-text-secondary sm:px-6">
                        {{ $section['unavailable_reason'] ?? 'Not available yet.' }}
                    </div>
                @elseif (($section['total_count'] ?? 0) === 0)
                    <div class="px-5 py-6 text-sm text-brand-text-secondary sm:px-6">
                        No notifications in this category right now.
                    </div>
                @else
                    <ul class="divide-y divide-brand-border">
                        @foreach ($section['items'] as $item)
                            @php
                                $severity = $item['severity'] ?? 'info';
                                $rowClass = $severityStyles[$severity] ?? $severityStyles['info'];
                                $dotClass = $severityDots[$severity] ?? $severityDots['info'];
                                $timeOffReview = $item['time_off_review'] ?? null;
                            @endphp
                            <li class="border-l-4 {{ $rowClass }}">
                                @if (is_array($timeOffReview) && ! empty($timeOffReview['id']))
                                    <button
                                        type="button"
                                        class="flex w-full items-start gap-3 px-5 py-3.5 text-left text-sm transition hover:bg-white/70 sm:px-6"
                                        data-open-time-off-review="{{ (int) $timeOffReview['id'] }}"
                                    >
                                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                                        <span class="text-brand-text">{{ $item['message'] }}</span>
                                    </button>
                                @elseif (! empty($item['url']))
                                    <a href="{{ $item['url'] }}" class="flex items-start gap-3 px-5 py-3.5 text-sm transition hover:bg-white/70 sm:px-6">
                                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                                        <span class="text-brand-text">{{ $item['message'] }}</span>
                                    </a>
                                @else
                                    <div class="flex items-start gap-3 px-5 py-3.5 text-sm sm:px-6">
                                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                                        <span class="text-brand-text">{{ $item['message'] }}</span>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if (($section['total_count'] ?? 0) > count($section['items']))
                        <div class="border-t border-brand-border bg-brand-surface/30 px-5 py-3 text-xs text-brand-text-secondary sm:px-6">
                            Showing {{ count($section['items']) }} of {{ $section['total_count'] }} notifications.
                        </div>
                    @endif
                @endif
            </div>
        @endforeach
    </section>

    <div
        id="time-off-review-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-brand-primary-dark/50 p-4"
        aria-hidden="true"
        role="dialog"
        aria-modal="true"
        aria-labelledby="time-off-review-title"
    >
        <div class="w-full max-w-md overflow-hidden rounded-2xl border border-brand-border bg-white shadow-2xl ring-1 ring-black/[0.06]">
            <header class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Pending request</p>
                        <h2 id="time-off-review-title" class="mt-1 text-lg font-bold text-brand-text">Time off request</h2>
                    </div>
                    <button
                        type="button"
                        id="time-off-review-close"
                        class="shrink-0 rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm hover:bg-brand-surface hover:text-brand-text"
                        aria-label="Close"
                    >
                        <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="mt-3 rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm shadow-sm">
                    <p id="time-off-review-employee" class="font-semibold text-brand-text">—</p>
                    <p id="time-off-review-date" class="mt-0.5 text-xs text-brand-text-secondary">—</p>
                </div>
            </header>

            <form id="time-off-approve-form" method="post" action="#" class="px-5 py-5">
                @csrf
                <div class="space-y-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Reason</p>
                        <p id="time-off-review-reason" class="mt-1 text-sm font-medium text-brand-text whitespace-pre-wrap">—</p>
                    </div>

                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Leave type</span>
                        <select
                            name="leave_type_id"
                            id="time-off-leave-type"
                            class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20"
                        >
                            <option value="">No leave type (unpaid day off)</option>
                        </select>
                    </label>

                    <div id="time-off-leave-balance" class="hidden rounded-xl border border-brand-border bg-brand-surface/50 px-3 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span id="time-off-leave-balance-name" class="truncate text-xs font-semibold text-brand-text"></span>
                            <span id="time-off-leave-balance-paid" class="shrink-0 text-[10px] font-bold uppercase tracking-wide"></span>
                        </div>
                        <div class="mt-2 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-white px-1.5 py-1.5 ring-1 ring-brand-border">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-brand-label">Allocated</p>
                                <p id="time-off-leave-allocated" class="mt-0.5 font-mono text-sm font-bold tabular-nums text-brand-text">—</p>
                            </div>
                            <div class="rounded-lg bg-white px-1.5 py-1.5 ring-1 ring-brand-border">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-brand-label">Used</p>
                                <p id="time-off-leave-used" class="mt-0.5 font-mono text-sm font-bold tabular-nums text-brand-text">—</p>
                            </div>
                            <div class="rounded-lg bg-white px-1.5 py-1.5 ring-1 ring-brand-border">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-brand-label">Remaining</p>
                                <p id="time-off-leave-remaining" class="mt-0.5 font-mono text-sm font-bold tabular-nums text-brand-primary">—</p>
                            </div>
                        </div>
                    </div>

                    <label id="time-off-leave-hours-wrap" class="hidden block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Leave hours</span>
                        <input
                            type="number"
                            step="0.25"
                            min="0.25"
                            max="24"
                            name="leave_hours"
                            id="time-off-leave-hours"
                            class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20"
                            placeholder="e.g. 7.6"
                        >
                        <span id="time-off-leave-hours-hint" class="mt-1 block text-[11px] text-brand-text-secondary"></span>
                    </label>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-2 border-t border-brand-border pt-4">
                    <button
                        type="button"
                        id="time-off-review-reject"
                        class="inline-flex items-center justify-center rounded-xl border-2 border-red-200 bg-white px-4 py-2.5 text-sm font-bold text-red-800 shadow-sm transition hover:bg-red-50"
                    >
                        Reject
                    </button>
                    <button
                        type="submit"
                        id="time-off-review-approve"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700"
                    >
                        Approve
                    </button>
                </div>
            </form>

            <form id="time-off-reject-form" method="post" action="#" class="hidden">
                @csrf
                <input type="hidden" name="decision_note" id="time-off-decision-note" value="">
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const reviews = @json($timeOffReviewsById);
                const leaveBalances = @json($timeOffLeaveBalances ?? []);
                const openRequestId = @json($openTimeOffRequestId);
                const modal = document.getElementById('time-off-review-modal');
                const approveForm = document.getElementById('time-off-approve-form');
                const rejectForm = document.getElementById('time-off-reject-form');
                const leaveTypeEl = document.getElementById('time-off-leave-type');
                const leaveHoursWrap = document.getElementById('time-off-leave-hours-wrap');
                const leaveHoursEl = document.getElementById('time-off-leave-hours');
                const leaveHoursHint = document.getElementById('time-off-leave-hours-hint');
                const leaveBalancePanel = document.getElementById('time-off-leave-balance');
                const leaveBalanceName = document.getElementById('time-off-leave-balance-name');
                const leaveBalancePaid = document.getElementById('time-off-leave-balance-paid');
                const leaveAllocated = document.getElementById('time-off-leave-allocated');
                const leaveUsed = document.getElementById('time-off-leave-used');
                const leaveRemaining = document.getElementById('time-off-leave-remaining');
                const decisionNoteEl = document.getElementById('time-off-decision-note');
                const employeeEl = document.getElementById('time-off-review-employee');
                const dateEl = document.getElementById('time-off-review-date');
                const reasonEl = document.getElementById('time-off-review-reason');
                let activeReview = null;

                function formatBalance(value) {
                    if (value === null || value === undefined || value === '') {
                        return '—';
                    }
                    const n = Number(value);
                    if (Number.isNaN(n)) {
                        return '—';
                    }
                    return (Math.round(n * 100) / 100).toString();
                }

                function populateLeaveTypes(employeePublicId) {
                    if (!(leaveTypeEl instanceof HTMLSelectElement)) {
                        return;
                    }
                    const list = leaveBalances[employeePublicId] || [];
                    leaveTypeEl.innerHTML = '<option value="">No leave type (unpaid day off)</option>';
                    list.forEach((item) => {
                        const opt = document.createElement('option');
                        opt.value = String(item.id);
                        opt.textContent = item.name + (item.is_paid ? '' : ' (Unpaid)');
                        opt.dataset.allocated = item.allocated === null || item.allocated === undefined ? '' : String(item.allocated);
                        opt.dataset.used = item.used === null || item.used === undefined ? '' : String(item.used);
                        opt.dataset.remaining = item.remaining === null || item.remaining === undefined ? '' : String(item.remaining);
                        opt.dataset.isPaid = item.is_paid ? '1' : '0';
                        leaveTypeEl.appendChild(opt);
                    });
                    leaveTypeEl.value = '';
                    updateLeaveBalanceUi();
                }

                function updateLeaveBalanceUi() {
                    const hasType = Boolean(leaveTypeEl && leaveTypeEl.value);
                    const option = leaveTypeEl?.selectedOptions?.[0];

                    leaveHoursWrap?.classList.toggle('hidden', !hasType);
                    if (leaveHoursEl instanceof HTMLInputElement) {
                        leaveHoursEl.required = hasType;
                        if (!hasType) {
                            leaveHoursEl.value = '';
                        } else if (!leaveHoursEl.value) {
                            leaveHoursEl.value = '8';
                        }
                    }

                    if (!hasType || !option) {
                        leaveBalancePanel?.classList.add('hidden');
                        if (leaveHoursHint) leaveHoursHint.textContent = '';
                        return;
                    }

                    leaveBalancePanel?.classList.remove('hidden');
                    const isPaid = option.dataset.isPaid === '1';
                    if (leaveBalanceName) leaveBalanceName.textContent = option.textContent || '';
                    if (leaveBalancePaid) {
                        leaveBalancePaid.textContent = isPaid ? 'Paid' : 'Unpaid';
                        leaveBalancePaid.classList.toggle('text-emerald-600', isPaid);
                        leaveBalancePaid.classList.toggle('text-slate-500', !isPaid);
                    }
                    if (leaveAllocated) leaveAllocated.textContent = formatBalance(option.dataset.allocated);
                    if (leaveUsed) leaveUsed.textContent = formatBalance(option.dataset.used);
                    if (leaveRemaining) leaveRemaining.textContent = formatBalance(option.dataset.remaining);
                    if (leaveHoursHint) {
                        const remaining = option.dataset.remaining;
                        leaveHoursHint.textContent = remaining !== '' && remaining !== undefined
                            ? 'Remaining balance: ' + formatBalance(remaining) + ' h'
                            : '';
                    }
                }

                function closeModal() {
                    if (!modal) {
                        return;
                    }
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    modal.setAttribute('aria-hidden', 'true');
                    activeReview = null;
                }

                function openModal(review) {
                    if (!modal || !review) {
                        return;
                    }
                    activeReview = review;
                    if (employeeEl) employeeEl.textContent = review.employee_name || 'Employee';
                    if (dateEl) dateEl.textContent = review.date_label || review.requested_date || '—';
                    if (reasonEl) {
                        const reason = (review.reason || '').trim();
                        reasonEl.textContent = reason !== '' ? reason : '—';
                    }
                    if (approveForm instanceof HTMLFormElement) {
                        approveForm.action = review.approve_url || '#';
                    }
                    if (rejectForm instanceof HTMLFormElement) {
                        rejectForm.action = review.reject_url || '#';
                    }
                    if (decisionNoteEl instanceof HTMLInputElement) {
                        decisionNoteEl.value = '';
                    }
                    populateLeaveTypes(review.employee_public_id || '');
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.setAttribute('aria-hidden', 'false');
                }

                document.querySelectorAll('[data-open-time-off-review]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const id = button.getAttribute('data-open-time-off-review');
                        const review = reviews[id] || reviews[String(id)];
                        if (review) {
                            openModal(review);
                        }
                    });
                });

                leaveTypeEl?.addEventListener('change', updateLeaveBalanceUi);
                document.getElementById('time-off-review-close')?.addEventListener('click', closeModal);
                modal?.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        closeModal();
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                        closeModal();
                    }
                });

                document.getElementById('time-off-review-reject')?.addEventListener('click', async () => {
                    if (!(rejectForm instanceof HTMLFormElement) || !activeReview) {
                        return;
                    }

                    const employeeName = activeReview.employee_name || 'this employee';
                    const dateLabel = activeReview.date_label || 'that date';
                    const dialog = window.CruLynkDialog;

                    if (!dialog || typeof dialog.promptNote !== 'function') {
                        if (window.confirm('Reject time off for ' + employeeName + ' on ' + dateLabel + '?')) {
                            rejectForm.requestSubmit();
                        }
                        return;
                    }

                    const note = await dialog.promptNote({
                        title: 'Reject time-off request?',
                        text: employeeName + ' · ' + dateLabel,
                        inputLabel: 'Note to employee (optional)',
                        inputPlaceholder: 'e.g. Too many people off that day',
                        confirmText: 'Reject request',
                        cancelText: 'Keep pending',
                        danger: true,
                    });

                    if (note === null) {
                        return;
                    }

                    if (decisionNoteEl instanceof HTMLInputElement) {
                        decisionNoteEl.value = note;
                    }

                    rejectForm.requestSubmit();
                });

                if (openRequestId && (reviews[openRequestId] || reviews[String(openRequestId)])) {
                    openModal(reviews[openRequestId] || reviews[String(openRequestId)]);
                }
            })();
        </script>
    @endpush
@endsection
