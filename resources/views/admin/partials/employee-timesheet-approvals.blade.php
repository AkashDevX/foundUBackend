@php
    /** @var list<array<string, mixed>> $timesheetRows */
    /** @var string $timesheetStatusFilter */
    /** @var \App\Models\Employee|null $selectedEmployee */
    use App\Models\TimesheetApproval;
    use App\Support\AdminTimesheetApproval;
    use App\Support\DisplayTimezone;

    $timesheetRows = $timesheetRows ?? [];
    $timesheetStatusFilter = $timesheetStatusFilter ?? 'pending';

    $filterLink = static function (string $filter) use ($selectedEmployee): string {
        return route('admin.employees.time-clock', array_filter([
            'employee' => $selectedEmployee?->public_id,
            'timesheet_status' => $filter !== 'pending' ? $filter : null,
        ]));
    };
@endphp

<div class="space-y-5">
    <div class="rounded-xl border border-brand-border bg-gradient-to-br from-white to-brand-surface/70 p-4 shadow-sm sm:p-5">
        <p class="text-sm font-semibold text-brand-text">HR timesheet approval</p>
        @if ($selectedEmployee)
            <p class="mt-1 text-xs leading-relaxed text-brand-text-secondary">Showing timesheets for <strong>{{ $selectedEmployee->full_legal_name ?: $selectedEmployee->email }}</strong> only.</p>
        @endif
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-border bg-white px-4 py-3 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Filter by status</p>
        <div class="inline-flex flex-wrap rounded-xl border border-brand-border bg-brand-surface/60 p-1 shadow-inner">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                <a
                    href="{{ $filterLink($key) }}"
                    class="rounded-lg px-4 py-2 text-xs font-bold transition {{ $timesheetStatusFilter === $key ? 'bg-white text-brand-primary shadow-sm ring-1 ring-brand-border/80' : 'text-brand-text-secondary hover:text-brand-text' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if ($timesheetRows === [])
        <div class="rounded-2xl border border-dashed border-brand-border bg-brand-surface/50 px-6 py-10 text-center">
            <p class="text-sm font-semibold text-brand-text">No timesheets match this filter</p>
            <p class="mt-2 text-xs text-brand-text-secondary">
                @if ($timesheetStatusFilter === 'pending')
                    There are no daily timesheets waiting for approval right now.
                @else
                    Try another status filter or check that employees have clock in/out activity in the last 12 weeks.
                @endif
            </p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-brand-border bg-brand-surface/80 text-xs font-semibold uppercase tracking-wide text-brand-label">
                        <tr>
                            @if (! $selectedEmployee)
                                <th class="whitespace-nowrap px-4 py-3 sm:px-5">Employee</th>
                            @endif
                            <th class="whitespace-nowrap px-4 py-3 sm:px-5">Work day</th>
                            <th class="whitespace-nowrap px-4 py-3 sm:px-5">Total hours</th>
                            <th class="hidden whitespace-nowrap px-4 py-3 md:table-cell sm:px-5">Sessions</th>
                            <th class="whitespace-nowrap px-4 py-3 sm:px-5">Status</th>
                            <th class="hidden whitespace-nowrap px-4 py-3 lg:table-cell sm:px-5">Reviewed</th>
                            <th class="whitespace-nowrap px-4 py-3 sm:px-5">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border/80">
                        @foreach ($timesheetRows as $row)
                            @php
                                /** @var \App\Models\Employee $rowEmployee */
                                $rowEmployee = $row['employee'];
                                $canReview = in_array($row['status'], [TimesheetApproval::STATUS_PENDING, TimesheetApproval::STATUS_REJECTED], true);
                            @endphp
                            <tr class="transition hover:bg-brand-surface/40">
                                @if (! $selectedEmployee)
                                    <td class="px-4 py-3.5 sm:px-5">
                                        <p class="font-semibold text-brand-text">{{ $rowEmployee->full_legal_name ?: $rowEmployee->email }}</p>
                                        <p class="mt-0.5 text-xs text-brand-text-secondary">{{ $rowEmployee->email }}</p>
                                    </td>
                                @endif
                                <td class="whitespace-nowrap px-4 py-3.5 text-brand-text sm:px-5">
                                    <span class="font-semibold">{{ $row['day_label'] }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 tabular-nums font-bold text-brand-text sm:px-5">
                                    {{ $row['total_hours_label'] }}
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-3.5 tabular-nums text-brand-text-secondary md:table-cell sm:px-5">
                                    {{ $row['completed_sessions'] }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">
                                    <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1 {{ AdminTimesheetApproval::statusBadgeClasses($row['status']) }}">
                                        {{ $row['status_label'] }}
                                    </span>
                                </td>
                                <td class="hidden px-4 py-3.5 text-xs text-brand-text-secondary lg:table-cell sm:px-5">
                                    @if ($row['reviewed_at'])
                                        <span class="block font-medium text-brand-text">{{ $row['reviewed_by'] }}</span>
                                        <span class="block tabular-nums">{{ DisplayTimezone::formatDateTime($row['reviewed_at']) }}</span>
                                        @if ($row['review_notes'])
                                            <span class="mt-1 block italic">{{ $row['review_notes'] }}</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 sm:px-5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($canReview)
                                            <form
                                                method="post"
                                                action="{{ route('admin.employees.time-clock.timesheets.approve') }}"
                                                class="inline"
                                                data-confirm="This day's timesheet will be marked as approved."
                                                data-confirm-title="Approve timesheet?"
                                                data-confirm-confirm="Approve"
                                                data-confirm-cancel="Not yet"
                                                data-confirm-icon="question"
                                            >
                                                @csrf
                                                <input type="hidden" name="employee" value="{{ $rowEmployee->public_id }}" />
                                                <input type="hidden" name="work_date" value="{{ $row['work_date'] }}" />
                                                <input type="hidden" name="timesheet_status" value="{{ $timesheetStatusFilter }}" />
                                                <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700">
                                                    Approve
                                                </button>
                                            </form>
                                            <details class="group/reject inline-block">
                                                <summary class="inline-flex cursor-pointer list-none items-center rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-bold text-red-800 transition hover:bg-red-50 [&::-webkit-details-marker]:hidden">
                                                    Reject
                                                </summary>
                                                <form
                                                    method="post"
                                                    action="{{ route('admin.employees.time-clock.timesheets.reject') }}"
                                                    class="mt-2 min-w-[16rem] rounded-xl border border-brand-border bg-white p-3 shadow-lg"
                                                    data-confirm="This day's timesheet will be marked as rejected."
                                                    data-confirm-title="Reject timesheet?"
                                                    data-confirm-confirm="Reject"
                                                    data-confirm-cancel="Go back"
                                                    data-confirm-danger="1"
                                                >
                                                    @csrf
                                                    <input type="hidden" name="employee" value="{{ $rowEmployee->public_id }}" />
                                                    <input type="hidden" name="work_date" value="{{ $row['work_date'] }}" />
                                                    <input type="hidden" name="timesheet_status" value="{{ $timesheetStatusFilter }}" />
                                                    <label class="block text-[10px] font-semibold uppercase tracking-wide text-brand-label">Notes (optional)</label>
                                                    <textarea name="review_notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-brand-border px-2 py-1.5 text-xs" placeholder="Reason for rejection"></textarea>
                                                    <button type="submit" class="mt-2 w-full rounded-lg bg-red-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-800">
                                                        Confirm reject
                                                    </button>
                                                </form>
                                            </details>
                                        @else
                                            <span class="text-xs text-brand-text-secondary">Signed off</span>
                                        @endif
                                        @if (! $selectedEmployee || $selectedEmployee->public_id !== $rowEmployee->public_id)
                                            <a
                                                href="{{ route('admin.employees.time-clock', ['employee' => $rowEmployee->public_id]) }}#punch-records"
                                                class="inline-flex items-center rounded-lg border border-brand-border px-3 py-1.5 text-xs font-semibold text-brand-primary hover:bg-brand-surface"
                                            >
                                                View punches
                                            </a>
                                        @else
                                            <a
                                                href="#punch-records"
                                                class="inline-flex items-center rounded-lg border border-brand-border px-3 py-1.5 text-xs font-semibold text-brand-primary hover:bg-brand-surface"
                                            >
                                                View punches
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
