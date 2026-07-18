@php
    /** @var array<string, string|null> $redirectQuery */
@endphp

<div
    id="time-clock-row-modal"
    class="fixed inset-0 z-[85] hidden items-center justify-center bg-brand-primary-dark/50 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="time-clock-row-modal-title"
    data-update-url="{{ route('admin.employees.time-clock.timesheets.update-punches') }}"
    data-approve-url="{{ route('admin.employees.time-clock.timesheets.approve') }}"
    data-reject-url="{{ route('admin.employees.time-clock.timesheets.reject') }}"
>
    <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-2xl ring-1 ring-black/[0.06]">
        <header class="border-b border-brand-border border-l-4 border-l-brand-primary bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label" data-time-clock-row-kicker>Timesheet record</p>
                    <h2 id="time-clock-row-modal-title" class="mt-1 truncate text-lg font-bold text-brand-text" data-time-clock-row-title>—</h2>
                    <p class="mt-1 text-sm text-brand-text-secondary" data-time-clock-row-employee>—</p>
                </div>
                <button
                    type="button"
                    class="rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm transition hover:bg-brand-surface hover:text-brand-text"
                    data-time-clock-row-close
                    aria-label="Close"
                >
                    <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mt-3">
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1"
                    data-time-clock-row-status-badge
                >—</span>
            </div>
        </header>

        <form method="post" action="" class="flex min-h-0 flex-1 flex-col" data-time-clock-row-form>
            @csrf
            <input type="hidden" name="employee" value="" data-time-clock-row-employee-input>
            <input type="hidden" name="work_date" value="" data-time-clock-row-work-date-input>
            <input type="hidden" name="clock_in_entry_id" value="" data-time-clock-row-clock-in-id>
            <input type="hidden" name="clock_out_entry_id" value="" data-time-clock-row-clock-out-id>
            @foreach ($redirectQuery as $key => $value)
                @if ($value !== null && $value !== '')
                    @if ($key === 'employee')
                        <input type="hidden" name="list_employee" value="{{ $value }}">
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endif
            @endforeach

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Date</dt>
                        <dd class="mt-1 text-sm font-medium text-brand-text" data-time-clock-row-date>—</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Position</dt>
                        <dd class="mt-1 text-sm font-medium text-brand-text" data-time-clock-row-position>—</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Location</dt>
                        <dd class="mt-1 text-sm font-medium text-brand-text" data-time-clock-row-location>—</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Scheduled time</dt>
                        <dd class="mt-1 text-sm font-medium text-brand-text" data-time-clock-row-scheduled>—</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Actual time</dt>
                        <dd class="mt-1 text-sm font-medium text-brand-text" data-time-clock-row-actual>—</dd>
                    </div>
                </dl>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Clock in</span>
                        <input
                            type="datetime-local"
                            name="clock_in_at"
                            data-time-clock-row-clock-in
                            class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20 disabled:bg-brand-surface/80 disabled:text-brand-text-secondary"
                        >
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Clock out</span>
                        <input
                            type="datetime-local"
                            name="clock_out_at"
                            data-time-clock-row-clock-out
                            class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20 disabled:bg-brand-surface/80 disabled:text-brand-text-secondary"
                        >
                    </label>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Comment</span>
                    <div
                        data-time-clock-row-clock-out-comment
                        class="hidden min-h-[4.5rem] w-full rounded-xl border border-brand-border bg-brand-surface/60 px-3 py-2.5 text-sm text-brand-text whitespace-pre-wrap"
                    ></div>
                    <p
                        data-time-clock-row-clock-out-comment-empty
                        class="hidden text-sm italic text-brand-text-secondary"
                    >No comment left at clock-out.</p>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Review notes</span>
                    <textarea
                        name="review_notes"
                        rows="3"
                        maxlength="2000"
                        data-time-clock-row-comment
                        class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20 disabled:bg-brand-surface/80 disabled:text-brand-text-secondary"
                        placeholder="Optional notes for approval or rejection"
                    ></textarea>
                </label>
            </div>

            <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-border bg-brand-surface/40 px-5 py-4">
                <button
                    type="button"
                    class="hidden rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text shadow-sm transition hover:bg-brand-surface"
                    data-time-clock-row-action="close"
                >
                    Close
                </button>
                <button
                    type="submit"
                    class="hidden rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text shadow-sm transition hover:bg-brand-surface"
                    data-time-clock-row-action="save"
                >
                    Save
                </button>
                <button
                    type="submit"
                    class="hidden rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    data-time-clock-row-action="approve"
                    data-confirm="Approve this day's timesheet?"
                    data-confirm-title="Approve timesheet?"
                    data-confirm-confirm="Approve"
                    data-confirm-cancel="Close"
                    data-confirm-icon="question"
                >
                    Approve
                </button>
                <button
                    type="submit"
                    class="hidden rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800"
                    data-time-clock-row-action="reject"
                    data-confirm="Reject this day's timesheet?"
                    data-confirm-title="Reject timesheet?"
                    data-confirm-confirm="Reject"
                    data-confirm-cancel="Close"
                    data-confirm-danger="1"
                >
                    Reject
                </button>
            </footer>
        </form>
    </div>
</div>
