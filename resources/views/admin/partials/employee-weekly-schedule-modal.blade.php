@php
    use App\Models\EmployeeScheduleShift;

    $fieldInput = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm placeholder:text-brand-text-secondary/60 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
    $fieldSelect = $fieldInput;
    $fieldLabel = 'mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label';
    $shiftDaysMap = [
        'mon' => 'Mon',
        'tue' => 'Tue',
        'wed' => 'Wed',
        'thu' => 'Thu',
        'fri' => 'Fri',
        'sat' => 'Sat',
        'sun' => 'Sun',
    ];
    $shiftDayLabel = 'schedule-repeat-day';
    $timeOptions = [];
    for ($hour = 0; $hour < 24; $hour++) {
        for ($minute = 0; $minute < 60; $minute += 15) {
            $value = sprintf('%02d:%02d', $hour, $minute);
            $hour12 = $hour % 12;
            if ($hour12 === 0) {
                $hour12 = 12;
            }
            $timeOptions[$value] = sprintf('%d:%02d %s', $hour12, $minute, $hour < 12 ? 'AM' : 'PM');
        }
    }
@endphp

<style>
    #schedule-shift-modal select:not([data-time-meridiem]) {
        -webkit-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.85rem center;
        background-size: 1rem 1rem;
        padding-right: 2.5rem;
    }
    #schedule-shift-modal select:not([data-time-meridiem]):focus {
        border-color: var(--color-brand-primary, #1e3a5f);
    }
    #schedule-shift-modal .schedule-time-segment {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.75rem;
        height: 2.75rem;
        margin: 0;
        flex: 0 0 2.75rem;
        border-radius: 0.65rem;
        background: color-mix(in srgb, var(--color-brand-surface, #f3f4f6) 90%, white);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-brand-border, #e5e7eb) 90%, transparent);
    }
    #schedule-shift-modal .schedule-time-segment-input {
        width: 100% !important;
        min-width: 0 !important;
        max-width: none !important;
        height: 100%;
        border: 0;
        background: transparent;
        padding: 0;
        text-align: center;
        font-size: 0.875rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1;
        color: inherit;
        outline: none;
        -webkit-appearance: none;
        appearance: none;
        background-image: none !important;
    }
    #schedule-shift-modal .schedule-time-meridiem {
        cursor: pointer;
        font-size: 0.7rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--color-brand-primary, #1e3a5f);
    }
    #schedule-shift-modal .schedule-time-colon {
        width: 0.75rem;
        flex: 0 0 0.75rem;
        text-align: center;
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--color-brand-text-secondary, #6b7280);
    }
    #schedule-shift-modal .schedule-time-toggle {
        cursor: pointer;
        color: var(--color-brand-text-secondary, #6b7280);
        transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    }
    #schedule-shift-modal .schedule-time-toggle:hover {
        color: var(--color-brand-primary, #1e3a5f);
        background: color-mix(in srgb, var(--color-brand-primary, #1e3a5f) 8%, white);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-brand-primary, #1e3a5f) 25%, transparent);
    }
    #schedule-shift-modal .schedule-time-segment:focus-within {
        background: color-mix(in srgb, var(--color-brand-primary, #1e3a5f) 8%, white);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-brand-primary, #1e3a5f) 30%, transparent);
    }
    #schedule-shift-modal .schedule-repeat-day {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 2.25rem;
        min-width: 0;
        cursor: pointer;
        user-select: none;
        border-radius: 0.5rem;
        border: 1px solid transparent;
        background: #e8eaee;
        font-size: 0.7rem;
        font-weight: 600;
        color: #6b7280;
    }
    #schedule-shift-modal .schedule-repeat-day:has(input:checked) {
        border-color: var(--color-brand-primary, #1e3a5f);
        background: #fff;
        color: var(--color-brand-primary, #1e3a5f);
    }
    #schedule-shift-modal .schedule-repeat-days {
        display: grid !important;
        grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
        gap: 0.25rem;
        width: 100%;
    }
</style>

<div
    id="schedule-shift-modal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-brand-primary-dark/50 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="schedule-shift-modal-title"
>
    <div id="schedule-shift-panel" class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-2xl ring-1 ring-black/[0.06]">
        {{-- Details view (existing entry) --}}
        <div id="schedule-shift-details" class="hidden min-h-0 flex-1 flex-col overflow-hidden">
            <header class="shrink-0 border-b border-brand-border border-l-4 border-l-brand-primary bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p id="schedule-modal-mode" class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Scheduled shift</p>
                        <h2 id="schedule-shift-modal-title" class="mt-1 text-lg font-bold text-brand-text">Shift details</h2>
                    </div>
                    <button type="button" id="schedule-shift-modal-close-details" class="shrink-0 rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm hover:bg-brand-surface hover:text-brand-text" aria-label="Close">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="mt-3 rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm shadow-sm">
                    <p id="schedule-modal-employee" class="font-semibold text-brand-text">—</p>
                    <p id="schedule-modal-date-label" class="mt-0.5 text-xs text-brand-text-secondary">—</p>
                    <span id="schedule-modal-status" class="mt-1.5 hidden items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset"></span>
                </div>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div id="schedule-detail-date-row" class="sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Date / day</dt>
                        <dd id="schedule-detail-date" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                    </div>
                    <div id="schedule-detail-position-row" class="sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Job title</dt>
                        <dd id="schedule-detail-position" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                    </div>
                    <div id="schedule-detail-time-row">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Start and finish</dt>
                        <dd id="schedule-detail-time" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                    </div>
                    <div id="schedule-detail-duration-row">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Duration</dt>
                        <dd id="schedule-detail-duration" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                    </div>
                    <div id="schedule-detail-breaks-row" class="hidden sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Breaks</dt>
                        <dd id="schedule-detail-breaks" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                    </div>
                    <div id="schedule-detail-notes-row" class="hidden sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Notes</dt>
                        <dd id="schedule-detail-notes" class="mt-1 whitespace-pre-wrap text-sm font-medium text-brand-text">—</dd>
                    </div>
                    <div id="schedule-detail-recurrence-row" class="sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Repeat</dt>
                        <dd id="schedule-detail-recurrence" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                    </div>
                    <div id="schedule-detail-meta-row" class="sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Location</dt>
                        <dd id="schedule-detail-meta" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                    </div>
                    <div id="schedule-detail-shift-row" class="hidden sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Leave type</dt>
                        <dd id="schedule-detail-shift" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                    </div>
                </dl>
            </div>
            <div class="shrink-0 flex flex-wrap items-center justify-between gap-2 border-t border-brand-border px-5 py-4">
                <button type="button" id="schedule-detail-delete" class="text-sm font-semibold text-red-600 hover:text-red-700">Delete</button>
                <div class="ml-auto flex items-center gap-2">
                    <div id="schedule-detail-actions" class="relative">
                        <button type="button" id="schedule-detail-menu-toggle" class="hidden rounded-xl border border-brand-border bg-white p-2.5 text-brand-text-secondary shadow-sm hover:bg-brand-surface hover:text-brand-text" aria-label="Shift actions" aria-haspopup="true" aria-expanded="false">
                            <svg class="size-5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="12" cy="19" r="1.9"/></svg>
                        </button>
                        <div id="schedule-detail-menu" class="absolute bottom-full right-0 z-10 mb-2 hidden w-56 overflow-hidden rounded-xl border border-brand-border bg-white py-1 shadow-lg ring-1 ring-black/[0.06]">
                            <button type="button" data-status-action="{{ EmployeeScheduleShift::STATUS_SICK_CALL_OUT }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-brand-text hover:bg-brand-surface">
                                <span class="size-2 shrink-0 rounded-full bg-amber-400"></span>
                                Mark as Sick Call Out
                            </button>
                            <button type="button" data-status-action="{{ EmployeeScheduleShift::STATUS_NO_SHOW }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-brand-text hover:bg-brand-surface">
                                <span class="size-2 shrink-0 rounded-full bg-red-500"></span>
                                Mark as No Show
                            </button>
                            <button type="button" id="schedule-clear-status" data-status-action="" class="hidden w-full items-center gap-2 border-t border-brand-border px-3 py-2 text-left text-sm font-medium text-brand-text-secondary hover:bg-brand-surface">
                                Clear status
                            </button>
                        </div>
                    </div>
                    <button type="button" id="schedule-detail-edit" class="rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text shadow-sm hover:bg-brand-surface">
                        Edit shift
                    </button>
                </div>
            </div>
        </div>

        {{-- Create / edit form --}}
        <form id="schedule-shift-form" method="post" action="{{ route('admin.employees.weekly-schedule.shifts.store') }}" class="flex min-h-0 flex-1 flex-col">
            @csrf
            @foreach ($redirectQuery as $key => $value)
                @if ($value !== null && $value !== '')
                    <input type="hidden" name="redirect[{{ $key }}]" value="{{ $value }}">
                @endif
            @endforeach
            <input type="hidden" name="employee_public_id" id="schedule-employee-hidden">
            <input type="hidden" name="entry_type" id="schedule-entry-type-hidden" value="{{ EmployeeScheduleShift::TYPE_SHIFT }}">
            <input type="hidden" name="time_off_request_id" id="schedule-time-off-request-id">

            <header class="shrink-0 border-b border-brand-border border-l-4 border-l-brand-primary bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Weekly schedule</p>
                        <h2 id="schedule-form-title" class="mt-1 text-lg font-bold text-brand-text">New shift</h2>
                    </div>
                    <button type="button" id="schedule-shift-modal-close" class="shrink-0 rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm hover:bg-brand-surface hover:text-brand-text" aria-label="Close">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="mt-3 flex items-center gap-3 rounded-xl border border-brand-border bg-white px-3 py-2.5 shadow-sm">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-primary/10 text-sm font-bold text-brand-primary" id="schedule-employee-avatar" aria-hidden="true">—</span>
                    <div class="min-w-0 flex-1">
                        <p id="schedule-employee-chip-name" class="truncate text-sm font-semibold text-brand-text">Employee</p>
                        <p id="schedule-employee-chip-meta" class="mt-0.5 truncate text-xs text-brand-text-secondary"></p>
                    </div>
                    <span id="schedule-employee-chip" class="sr-only"></span>
                    <span id="schedule-employee-chip-name-timeoff" class="sr-only"></span>
                </div>

                <div id="schedule-entry-tabs" class="mt-4 grid grid-cols-2 gap-1 rounded-xl bg-brand-surface/80 p-1 ring-1 ring-brand-border/70" role="tablist">
                    <button type="button" data-schedule-tab="{{ EmployeeScheduleShift::TYPE_SHIFT }}" class="schedule-tab rounded-lg bg-white px-3 py-2 text-xs font-bold uppercase tracking-wide text-brand-primary shadow-sm ring-1 ring-brand-border/60" aria-selected="true">
                        Shift
                    </button>
                    <button type="button" data-schedule-tab="{{ EmployeeScheduleShift::TYPE_TIME_OFF }}" class="schedule-tab rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide text-brand-text-secondary hover:text-brand-text" aria-selected="false">
                        Time off
                    </button>
                </div>
            </header>

            <p id="schedule-suggestion-banner" class="mx-5 mt-4 hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                Suggested from work assignment — save to add to this week.
            </p>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5">
                <label class="block">
                    <span class="{{ $fieldLabel }}">Date</span>
                    <input type="date" name="scheduled_date" id="schedule-date" required class="{{ $fieldInput }}">
                </label>

                <div id="schedule-shift-fields" class="space-y-4">
                    <div>
                        <span class="{{ $fieldLabel }}">Time</span>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ([
                                ['id' => 'start', 'label' => 'Start', 'hiddenId' => 'schedule-start', 'value' => '09:00', 'hour' => '09', 'minute' => '00', 'meridiem' => 'AM'],
                                ['id' => 'end', 'label' => 'End', 'hiddenId' => 'schedule-end', 'value' => '17:00', 'hour' => '05', 'minute' => '00', 'meridiem' => 'PM'],
                            ] as $timeField)
                                <div class="schedule-time-picker relative" data-schedule-time-picker>
                                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">{{ $timeField['label'] }}</span>
                                    <input type="hidden" name="{{ $timeField['id'] === 'start' ? 'start_time' : 'end_time' }}" id="{{ $timeField['hiddenId'] }}" value="{{ $timeField['value'] }}" required>
                                    <div class="inline-flex items-center gap-1 rounded-xl border border-brand-border bg-white p-1 shadow-sm focus-within:border-brand-primary focus-within:ring-2 focus-within:ring-brand-primary/20">
                                        <label class="schedule-time-segment">
                                            <span class="sr-only">{{ $timeField['label'] }} hour</span>
                                            <input
                                                id="schedule-{{ $timeField['id'] }}-hour"
                                                type="text"
                                                inputmode="numeric"
                                                maxlength="2"
                                                size="2"
                                                class="schedule-time-segment-input"
                                                value="{{ $timeField['hour'] }}"
                                                data-time-hour
                                                autocomplete="off"
                                            >
                                        </label>
                                        <span class="schedule-time-colon" aria-hidden="true">:</span>
                                        <label class="schedule-time-segment">
                                            <span class="sr-only">{{ $timeField['label'] }} minutes</span>
                                            <input
                                                id="schedule-{{ $timeField['id'] }}-minute"
                                                type="text"
                                                inputmode="numeric"
                                                maxlength="2"
                                                size="2"
                                                class="schedule-time-segment-input"
                                                value="{{ $timeField['minute'] }}"
                                                data-time-minute
                                                autocomplete="off"
                                            >
                                        </label>
                                        <label class="schedule-time-segment">
                                            <span class="sr-only">{{ $timeField['label'] }} AM or PM</span>
                                            <select
                                                id="schedule-{{ $timeField['id'] }}-meridiem"
                                                class="schedule-time-segment-input schedule-time-meridiem"
                                                data-time-meridiem
                                            >
                                                <option value="AM" @selected($timeField['meridiem'] === 'AM')>AM</option>
                                                <option value="PM" @selected($timeField['meridiem'] === 'PM')>PM</option>
                                            </select>
                                        </label>
                                        <button
                                            type="button"
                                            class="schedule-time-segment schedule-time-toggle"
                                            data-time-picker-toggle
                                            aria-label="Choose {{ strtolower($timeField['label']) }} time"
                                            aria-expanded="false"
                                        >
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                    </div>
                                    <div
                                        class="absolute left-0 right-0 z-30 mt-1.5 hidden max-h-60 min-w-full overflow-y-auto rounded-xl border border-brand-border bg-white py-1.5 shadow-xl ring-1 ring-black/[0.06]"
                                        style="min-width: 100%; width: max(100%, 14rem);"
                                        data-time-picker-menu
                                        role="listbox"
                                        aria-label="{{ $timeField['label'] }} time options"
                                    >
                                        @foreach ($timeOptions as $value => $label)
                                            <button
                                                type="button"
                                                class="flex w-full items-center px-3.5 py-2.5 text-left text-sm font-medium text-brand-text transition hover:bg-brand-primary/10 hover:text-brand-primary {{ $value === $timeField['value'] ? 'bg-brand-primary/10 font-semibold text-brand-primary' : '' }}"
                                                data-time-value="{{ $value }}"
                                                data-time-label="{{ $label }}"
                                                role="option"
                                                @if ($value === $timeField['value']) aria-selected="true" @endif
                                            >{{ $label }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div id="schedule-breaks-wrap">
                        @include('admin.partials.shift-breaks-fields', [
                            'breaks' => [['label' => '', 'minutes' => '', 'paid' => false]],
                            'fieldId' => 'schedule-shift-breaks',
                            'in' => $fieldSelect,
                            'lbl' => $fieldLabel,
                        ])
                    </div>

                    <label class="block">
                        <span class="{{ $fieldLabel }}">Notes</span>
                        <textarea name="notes" id="schedule-shift-notes" rows="2" maxlength="500" class="{{ $fieldInput }} min-h-[4.5rem] resize-y"></textarea>
                    </label>

                    <div id="schedule-recurrence-wrap" class="space-y-3">
                        <label class="block">
                            <span class="{{ $fieldLabel }}">Repeat</span>
                            <select name="recurrence" id="schedule-recurrence-mode" class="{{ $fieldSelect }}">
                                @foreach (\App\Support\AdminWeeklySchedule::recurrenceModeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div id="schedule-recurrence-details" class="hidden space-y-3 rounded-xl bg-[#f3f4f6] p-3">
                            <div>
                                <span class="mb-1.5 block text-[10px] font-semibold uppercase tracking-wide text-brand-text-secondary">Repeat on</span>
                                <div class="schedule-repeat-days">
                                    @foreach ($shiftDaysMap as $dayKey => $dayLabel)
                                        <label class="{{ $shiftDayLabel }}">
                                            <span class="relative z-0">{{ $dayLabel }}</span>
                                            <input type="checkbox" name="shift_days[]" value="{{ $dayKey }}" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" />
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="grid grid-cols-2 items-stretch gap-2">
                                    <label class="flex min-h-[3.25rem] min-w-0 flex-col justify-center rounded-lg bg-[#e8eaee] px-3 py-2">
                                        <span class="block text-[10px] font-semibold uppercase tracking-wide text-brand-text-secondary">Starts</span>
                                        <input type="date" id="schedule-recurrence-starts" class="mt-0.5 w-full border-0 bg-transparent p-0 text-sm font-medium leading-5 text-brand-text outline-none focus:ring-0">
                                    </label>
                                    <label id="schedule-recurrence-ends-wrap" class="flex min-h-[3.25rem] min-w-0 flex-col justify-center rounded-lg bg-[#e8eaee] px-3 py-2">
                                        <span class="block text-[10px] font-semibold uppercase tracking-wide text-brand-text-secondary">Ends</span>
                                        <input type="date" name="recurrence_until" id="schedule-recurrence-until" class="mt-0.5 w-full border-0 bg-transparent p-0 text-sm font-medium leading-5 text-brand-text outline-none focus:ring-0">
                                    </label>
                                </div>
                                <div id="schedule-recurrence-one-year-wrap" class="flex justify-end">
                                    <button
                                        type="button"
                                        id="schedule-recurrence-one-year"
                                        class="inline-flex items-center rounded-md bg-white px-2.5 py-1 text-xs font-medium text-brand-primary shadow-sm ring-1 ring-brand-border hover:bg-brand-surface"
                                    >
                                        Repeat for one year
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <label class="block">
                        <span class="{{ $fieldLabel }}">Job title</span>
                        <select name="job_title_id" id="schedule-job-title" class="{{ $fieldSelect }}">
                        </select>
                    </label>

                    <label class="block">
                        <span class="{{ $fieldLabel }}">Location</span>
                        <select name="work_location_id" id="schedule-work-location" class="{{ $fieldSelect }}">
                            <option value="">Add location…</option>
                            @foreach ($workLocations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div id="schedule-timeoff-fields" class="hidden space-y-4">
                    <label class="block">
                        <span class="{{ $fieldLabel }}">Leave type</span>
                        <select name="leave_type_id" id="schedule-leave-type" class="{{ $fieldSelect }}">
                            <option value="">No leave type (unpaid day off)</option>
                        </select>
                    </label>

                    <div id="schedule-leave-balance" class="hidden rounded-xl border border-brand-border bg-brand-surface/50 px-3 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span id="schedule-leave-balance-name" class="truncate text-xs font-semibold text-brand-text"></span>
                            <span id="schedule-leave-balance-paid" class="shrink-0 text-[10px] font-bold uppercase tracking-wide"></span>
                        </div>
                        <div class="mt-2 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-white px-1.5 py-1.5 ring-1 ring-brand-border">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-brand-label">Allocated</p>
                                <p id="schedule-leave-allocated" class="mt-0.5 font-mono text-sm font-bold tabular-nums text-brand-text">—</p>
                            </div>
                            <div class="rounded-lg bg-white px-1.5 py-1.5 ring-1 ring-brand-border">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-brand-label">Used</p>
                                <p id="schedule-leave-used" class="mt-0.5 font-mono text-sm font-bold tabular-nums text-brand-text">—</p>
                            </div>
                            <div class="rounded-lg bg-white px-1.5 py-1.5 ring-1 ring-brand-border">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-brand-label">Remaining</p>
                                <p id="schedule-leave-remaining" class="mt-0.5 font-mono text-sm font-bold tabular-nums text-brand-primary">—</p>
                            </div>
                        </div>
                    </div>

                    <label class="block" id="schedule-leave-hours-wrap">
                        <span class="{{ $fieldLabel }}">Leave hours</span>
                        <input type="number" step="0.25" min="0.25" max="24" name="leave_hours" id="schedule-leave-hours" class="{{ $fieldInput }}">
                    </label>

                    <label class="block">
                        <span class="{{ $fieldLabel }}">Reason / notes</span>
                        <input type="text" name="notes" id="schedule-notes" maxlength="500" class="{{ $fieldInput }}" disabled>
                    </label>
                </div>

                <div id="schedule-employees-field">
                    <span class="{{ $fieldLabel }}">Employee</span>
                    <div id="schedule-employees-picker" class="rounded-xl border border-brand-border bg-white p-2.5 shadow-sm">
                        <div id="schedule-employees-selected" class="mb-2 flex flex-wrap gap-1.5 empty:mb-0 empty:hidden"></div>
                        <select id="schedule-employee-add" class="{{ $fieldSelect }}">
                            <option value="">Add employee(s)…</option>
                        </select>
                    </div>
                    <div id="schedule-employees-edit-only" class="hidden rounded-xl border border-brand-border bg-brand-surface/40 px-3 py-2.5 text-sm text-brand-text">
                        <div id="schedule-employees-selected-edit" class="flex flex-wrap gap-1.5"></div>
                        <span id="schedule-employees-edit-name" class="sr-only">Employee</span>
                    </div>
                </div>
            </div>

            <div class="shrink-0 flex flex-wrap items-center justify-between gap-2 border-t border-brand-border bg-brand-surface/30 px-5 py-4">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" id="schedule-shift-delete" class="hidden text-sm font-semibold text-red-600 hover:text-red-700">
                        Delete
                    </button>
                    <button type="button" id="schedule-convert-to-shift" class="hidden text-sm font-semibold text-brand-primary hover:text-brand-primary-dark">
                        Add shift instead
                    </button>
                </div>
                <div class="ml-auto flex gap-2">
                    <button type="button" id="schedule-shift-cancel" class="rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text-secondary shadow-sm hover:bg-white/80">
                        Cancel
                    </button>
                    <button type="submit" id="schedule-shift-submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-brand-primary/20 hover:bg-brand-primary-dark">
                        Save shift
                    </button>
                </div>
            </div>
        </form>

        <form
            id="schedule-shift-delete-form"
            method="post"
            action="#"
            class="hidden"
            data-confirm="This entry will be removed from the weekly schedule."
            data-confirm-title="Remove from schedule?"
            data-confirm-confirm="Remove"
            data-confirm-cancel="Keep it"
            data-confirm-danger="1"
        >
            @csrf
            @method('DELETE')
            @foreach ($redirectQuery as $key => $value)
                @if ($value !== null && $value !== '')
                    <input type="hidden" name="redirect[{{ $key }}]" value="{{ $value }}">
                @endif
            @endforeach
        </form>

        <form id="schedule-shift-status-form" method="post" action="#" class="hidden">
            @csrf
            @foreach ($redirectQuery as $key => $value)
                @if ($value !== null && $value !== '')
                    <input type="hidden" name="redirect[{{ $key }}]" value="{{ $value }}">
                @endif
            @endforeach
            <input type="hidden" name="status" id="schedule-status-value">
            <input type="hidden" name="notes" id="schedule-status-notes" value="">
        </form>
    </div>
</div>

<script>
    (function () {
        const TYPE_SHIFT = @json(EmployeeScheduleShift::TYPE_SHIFT);
        const TYPE_TIME_OFF = @json(EmployeeScheduleShift::TYPE_TIME_OFF);

        const modal = document.getElementById('schedule-shift-modal');
        const form = document.getElementById('schedule-shift-form');
        const deleteForm = document.getElementById('schedule-shift-delete-form');
        const deleteButton = document.getElementById('schedule-shift-delete');
        const convertToShiftButton = document.getElementById('schedule-convert-to-shift');
        const submitButton = document.getElementById('schedule-shift-submit');
        const entryTypeHidden = document.getElementById('schedule-entry-type-hidden');
        const shiftFields = document.getElementById('schedule-shift-fields');
        const timeOffFields = document.getElementById('schedule-timeoff-fields');
        const workLocationEl = document.getElementById('schedule-work-location');
        const jobTitleEl = document.getElementById('schedule-job-title');
        const dateInput = document.getElementById('schedule-date');
        const startInput = document.getElementById('schedule-start');
        const endInput = document.getElementById('schedule-end');
        const breaksWrap = document.getElementById('schedule-breaks-wrap');
        const shiftNotesEl = document.getElementById('schedule-shift-notes');
        const notesEl = document.getElementById('schedule-notes');
        const recurrenceWrap = document.getElementById('schedule-recurrence-wrap');
        const recurrenceModeEl = document.getElementById('schedule-recurrence-mode');
        const recurrenceDetails = document.getElementById('schedule-recurrence-details');
        const recurrenceStartsEl = document.getElementById('schedule-recurrence-starts');
        const recurrenceUntilEl = document.getElementById('schedule-recurrence-until');
        const recurrenceOneYearBtn = document.getElementById('schedule-recurrence-one-year');
        const recurrenceOneYearWrap = document.getElementById('schedule-recurrence-one-year-wrap');
        const recurrenceEndsWrap = document.getElementById('schedule-recurrence-ends-wrap');
        const leaveTypeEl = document.getElementById('schedule-leave-type');
        const leaveHoursEl = document.getElementById('schedule-leave-hours');
        const leaveHoursWrap = document.getElementById('schedule-leave-hours-wrap');
        const leaveBalancePanel = document.getElementById('schedule-leave-balance');
        const leaveBalanceName = document.getElementById('schedule-leave-balance-name');
        const leaveBalancePaid = document.getElementById('schedule-leave-balance-paid');
        const leaveAllocatedEl = document.getElementById('schedule-leave-allocated');
        const leaveUsedEl = document.getElementById('schedule-leave-used');
        const leaveRemainingEl = document.getElementById('schedule-leave-remaining');
        const leaveBalances = @json($leaveBalances ?? []);
        const modalEmployees = @json($modalEmployees ?? []);
        const employeeHidden = document.getElementById('schedule-employee-hidden');
        const timeOffRequestIdInput = document.getElementById('schedule-time-off-request-id');
        const formTitleEl = document.getElementById('schedule-form-title');
        const employeeChipName = document.getElementById('schedule-employee-chip-name');
        const employeeChipNameTimeoff = document.getElementById('schedule-employee-chip-name-timeoff');
        const employeeChipMeta = document.getElementById('schedule-employee-chip-meta');
        const employeeAvatarEl = document.getElementById('schedule-employee-avatar');
        const employeeAddEl = document.getElementById('schedule-employee-add');
        const employeesSelectedEl = document.getElementById('schedule-employees-selected');
        const employeesPickerEl = document.getElementById('schedule-employees-picker');
        const employeesEditOnlyEl = document.getElementById('schedule-employees-edit-only');
        const employeesEditNameEl = document.getElementById('schedule-employees-edit-name');
        const entryTabs = document.getElementById('schedule-entry-tabs');
        let selectedEmployeeIds = [];
        let employeePickerLocked = false;

        const modeEl = document.getElementById('schedule-modal-mode');
        const titleEl = document.getElementById('schedule-shift-modal-title');
        const employeeLabelEl = document.getElementById('schedule-modal-employee');
        const dateLabelEl = document.getElementById('schedule-modal-date-label');
        const suggestionBanner = document.getElementById('schedule-suggestion-banner');
        const detailsPanel = document.getElementById('schedule-shift-details');
        const detailDeleteButton = document.getElementById('schedule-detail-delete');
        const detailEditButton = document.getElementById('schedule-detail-edit');
        const menuToggle = document.getElementById('schedule-detail-menu-toggle');
        const detailMenu = document.getElementById('schedule-detail-menu');
        const clearStatusButton = document.getElementById('schedule-clear-status');
        const statusForm = document.getElementById('schedule-shift-status-form');
        const statusValueInput = document.getElementById('schedule-status-value');
        const statusNotesInput = document.getElementById('schedule-status-notes');
        const statusBadge = document.getElementById('schedule-modal-status');
        const STATUS_LABELS = { {{ EmployeeScheduleShift::STATUS_SICK_CALL_OUT }}: 'Sick call out', {{ EmployeeScheduleShift::STATUS_NO_SHOW }}: 'No show' };
        const detailDateRow = document.getElementById('schedule-detail-date-row');
        const detailPositionEl = document.getElementById('schedule-detail-position');
        const detailTimeRow = document.getElementById('schedule-detail-time-row');
        const detailDurationRow = document.getElementById('schedule-detail-duration-row');
        const detailBreaksRow = document.getElementById('schedule-detail-breaks-row');
        const detailRecurrenceRow = document.getElementById('schedule-detail-recurrence-row');
        const detailShiftRow = document.getElementById('schedule-detail-shift-row');
        const detailMetaRow = document.getElementById('schedule-detail-meta-row');
        const detailNotesRow = document.getElementById('schedule-detail-notes-row');
        const detailDateEl = document.getElementById('schedule-detail-date');
        const detailTimeEl = document.getElementById('schedule-detail-time');
        const detailDurationEl = document.getElementById('schedule-detail-duration');
        const detailBreaksEl = document.getElementById('schedule-detail-breaks');
        const detailRecurrenceEl = document.getElementById('schedule-detail-recurrence');
        const detailShiftEl = document.getElementById('schedule-detail-shift');
        const detailMetaEl = document.getElementById('schedule-detail-meta');
        const detailNotesEl = document.getElementById('schedule-detail-notes');

        const storeUrl = @json(route('admin.employees.weekly-schedule.shifts.store'));
        const updateUrlTemplate = @json(route('admin.employees.weekly-schedule.shifts.update', ['scheduleShift' => '__ID__']));
        const destroyUrlTemplate = @json(route('admin.employees.weekly-schedule.shifts.destroy', ['scheduleShift' => '__ID__']));
        const statusUrlTemplate = @json(route('admin.employees.weekly-schedule.shifts.status', ['scheduleShift' => '__ID__']));

        if (!modal || !form) return;

        /** @type {Record<string, string>|null} */
        let currentPayload = null;
        let openedFromDetails = false;

        function formatDateLabel(isoDate) {
            if (!isoDate) return '—';
            const date = new Date(isoDate + 'T12:00:00');
            if (Number.isNaN(date.getTime())) return isoDate;
            return date.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        }

        function setDetailText(el, value) {
            if (el) {
                el.textContent = value && String(value).trim() !== '' ? value : '—';
            }
        }

        function toggleDetailRow(row, visible) {
            if (row) {
                row.classList.toggle('hidden', !visible);
            }
        }

        function showDetailsView() {
            if (detailsPanel) detailsPanel.classList.remove('hidden');
            form.classList.add('hidden');
        }

        function showFormView() {
            if (detailsPanel) detailsPanel.classList.add('hidden');
            form.classList.remove('hidden');
        }

        function closeDetailMenu() {
            detailMenu?.classList.add('hidden');
            menuToggle?.setAttribute('aria-expanded', 'false');
        }

        function fmtHours(value) {
            if (value === null || value === undefined || value === '') return '—';
            const n = Number(value);
            if (Number.isNaN(n)) return '—';
            return (Math.round(n * 100) / 100).toString();
        }

        function parseBreaks(value) {
            if (Array.isArray(value)) return value;
            if (typeof value !== 'string' || value.trim() === '') return [];
            try {
                const parsed = JSON.parse(value);
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        function normalizeTimeValue(value) {
            const raw = String(value || '').trim();
            if (!raw) return '';

            const ampmMatch = raw.match(/^(\d{1,2})(?::(\d{2}))?\s*(a\.?m\.?|p\.?m\.?)$/i);
            if (ampmMatch) {
                let hours = Number(ampmMatch[1]);
                const minutes = ampmMatch[2] != null ? Number(ampmMatch[2]) : 0;
                const isPm = /^p/i.test(ampmMatch[3]);
                if (hours === 12) hours = isPm ? 12 : 0;
                else if (isPm) hours += 12;
                if (hours > 23 || minutes > 59) return '';
                return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
            }

            const twentyFour = raw.match(/^(\d{1,2}):(\d{2})$/);
            if (twentyFour) {
                const hours = Number(twentyFour[1]);
                const minutes = Number(twentyFour[2]);
                if (hours > 23 || minutes > 59) return '';
                return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
            }

            const compact = raw.match(/^(\d{1,2})(\d{2})$/);
            if (compact) {
                const hours = Number(compact[1]);
                const minutes = Number(compact[2]);
                if (hours > 23 || minutes > 59) return '';
                return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
            }

            const hourOnly = raw.match(/^(\d{1,2})$/);
            if (hourOnly) {
                const hours = Number(hourOnly[1]);
                if (hours > 23) return '';
                return String(hours).padStart(2, '0') + ':00';
            }

            return '';
        }

        function formatTimeLabel(value) {
            const normalized = normalizeTimeValue(value);
            if (!normalized) return '';
            const parts = splitTimeParts(normalized);
            return `${parts.hour}:${parts.minute} ${parts.meridiem}`;
        }

        function splitTimeParts(value) {
            const normalized = normalizeTimeValue(value) || '09:00';
            const [hoursRaw, minutes] = normalized.split(':');
            let hours24 = Number(hoursRaw);
            const meridiem = hours24 >= 12 ? 'PM' : 'AM';
            let hour12 = hours24 % 12;
            if (hour12 === 0) hour12 = 12;
            return {
                hour: String(hour12).padStart(2, '0'),
                minute: String(minutes).padStart(2, '0'),
                meridiem,
            };
        }

        function digitsOnly(value, maxLength) {
            return String(value || '').replace(/\D/g, '').slice(0, maxLength);
        }

        function clampHour12(raw) {
            const digits = digitsOnly(raw, 2);
            if (digits === '') return '';
            let n = Number(digits);
            if (Number.isNaN(n)) return '';
            if (n > 12) n = 12;
            if (n < 1 && digits.length === 2) n = 1;
            return digits.length === 2 ? String(n).padStart(2, '0') : String(n);
        }

        function clampMinute(raw) {
            const digits = digitsOnly(raw, 2);
            if (digits === '') return '';
            if (digits.length === 1) return digits;
            let n = Number(digits);
            if (Number.isNaN(n) || n > 59) return '59';
            return String(n).padStart(2, '0');
        }

        function buildTimeFromParts(hourRaw, minuteRaw, meridiemRaw) {
            let hour12 = Number(clampHour12(hourRaw) || '12');
            if (hour12 < 1) hour12 = 12;
            if (hour12 > 12) hour12 = 12;
            let minute = Number(clampMinute(minuteRaw || '0') || '0');
            if (minute > 59) minute = 59;
            const meridiem = String(meridiemRaw || 'AM').toUpperCase() === 'PM' ? 'PM' : 'AM';
            let hours24 = hour12 % 12;
            if (meridiem === 'PM') hours24 += 12;
            return String(hours24).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
        }

        function closeAllTimePickerMenus(exceptRoot) {
            document.querySelectorAll('[data-schedule-time-picker]').forEach((root) => {
                if (exceptRoot && root === exceptRoot) return;
                const menu = root.querySelector('[data-time-picker-menu]');
                const toggle = root.querySelector('[data-time-picker-toggle]');
                menu?.classList.add('hidden');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            });
        }

        function highlightTimeOption(root, value) {
            const normalized = normalizeTimeValue(value);
            root.querySelectorAll('[data-time-value]').forEach((btn) => {
                const active = btn.getAttribute('data-time-value') === normalized;
                btn.classList.toggle('bg-brand-primary/10', active);
                btn.classList.toggle('font-semibold', active);
                btn.classList.toggle('text-brand-primary', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
                if (active) {
                    btn.scrollIntoView({ block: 'nearest' });
                }
            });
        }

        function syncPartsToHidden(root) {
            const hidden = root.querySelector('input[type="hidden"]');
            const hourEl = root.querySelector('[data-time-hour]');
            const minuteEl = root.querySelector('[data-time-minute]');
            const meridiemEl = root.querySelector('[data-time-meridiem]');
            if (!(hidden instanceof HTMLInputElement)) return;

            const hour = clampHour12(hourEl?.value || '');
            const minute = clampMinute(minuteEl?.value || '');
            if (hour === '' || minute === '' || minute.length < 2) {
                return;
            }

            const value = buildTimeFromParts(hour, minute, meridiemEl?.value || 'AM');
            hidden.value = value;
            highlightTimeOption(root, value);
        }

        function setTimePickerValue(hiddenInput, value, options = {}) {
            if (!(hiddenInput instanceof HTMLInputElement)) return;
            const root = hiddenInput.closest('[data-schedule-time-picker]');
            const normalized = normalizeTimeValue(value) || (options.fallback ? normalizeTimeValue(options.fallback) : '');
            if (!normalized) return;

            hiddenInput.value = normalized;
            const parts = splitTimeParts(normalized);
            const hourEl = root?.querySelector('[data-time-hour]');
            const minuteEl = root?.querySelector('[data-time-minute]');
            const meridiemEl = root?.querySelector('[data-time-meridiem]');
            if (hourEl instanceof HTMLInputElement) hourEl.value = parts.hour;
            if (minuteEl instanceof HTMLInputElement) minuteEl.value = parts.minute;
            if (meridiemEl instanceof HTMLSelectElement) meridiemEl.value = parts.meridiem;
            if (root) highlightTimeOption(root, normalized);
        }

        function commitTimeParts(root) {
            const hidden = root.querySelector('input[type="hidden"]');
            const hourEl = root.querySelector('[data-time-hour]');
            const minuteEl = root.querySelector('[data-time-minute]');
            const meridiemEl = root.querySelector('[data-time-meridiem]');
            if (!(hidden instanceof HTMLInputElement)) return;

            let hour = clampHour12(hourEl?.value || '');
            let minute = clampMinute(minuteEl?.value || '');
            if (hour === '') hour = '12';
            if (minute === '') minute = '00';
            if (minute.length === 1) minute = minute.padStart(2, '0');
            if (Number(hour) < 1) hour = '12';
            if (Number(hour) > 12) hour = '12';
            hour = String(Number(hour)).padStart(2, '0');

            if (hourEl instanceof HTMLInputElement) hourEl.value = hour;
            if (minuteEl instanceof HTMLInputElement) minuteEl.value = minute;

            const value = buildTimeFromParts(hour, minute, meridiemEl?.value || 'AM');
            hidden.value = value;
            highlightTimeOption(root, value);
        }

        function openTimePickerMenu(root) {
            closeAllTimePickerMenus(root);
            const menu = root.querySelector('[data-time-picker-menu]');
            const toggle = root.querySelector('[data-time-picker-toggle]');
            const hidden = root.querySelector('input[type="hidden"]');
            menu?.classList.remove('hidden');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
            if (hidden instanceof HTMLInputElement) {
                highlightTimeOption(root, hidden.value);
            }
        }

        function initTimePickers() {
            document.querySelectorAll('[data-schedule-time-picker]').forEach((root) => {
                if (!(root instanceof HTMLElement) || root.dataset.bound === '1') return;
                root.dataset.bound = '1';

                const hidden = root.querySelector('input[type="hidden"]');
                const hourEl = root.querySelector('[data-time-hour]');
                const minuteEl = root.querySelector('[data-time-minute]');
                const meridiemEl = root.querySelector('[data-time-meridiem]');
                const toggle = root.querySelector('[data-time-picker-toggle]');
                const menu = root.querySelector('[data-time-picker-menu]');

                toggle?.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    if (menu?.classList.contains('hidden')) {
                        openTimePickerMenu(root);
                    } else {
                        closeAllTimePickerMenus();
                    }
                });

                const bindSegment = (el, kind) => {
                    if (!(el instanceof HTMLInputElement || el instanceof HTMLSelectElement)) return;

                    el.addEventListener('focus', () => {
                        closeAllTimePickerMenus();
                        if (el instanceof HTMLInputElement) el.select();
                    });

                    if (el instanceof HTMLInputElement) {
                        el.addEventListener('input', () => {
                            if (kind === 'hour') {
                                el.value = digitsOnly(el.value, 2);
                                const clamped = clampHour12(el.value);
                                if (clamped !== '' && Number(clamped) > 12) {
                                    el.value = '12';
                                }
                                if (el.value.length === 2) {
                                    minuteEl?.focus();
                                    minuteEl?.select();
                                }
                            } else if (kind === 'minute') {
                                el.value = digitsOnly(el.value, 2);
                                if (el.value.length === 2 && Number(el.value) > 59) {
                                    el.value = '59';
                                }
                                if (el.value.length === 2) {
                                    meridiemEl?.focus();
                                }
                            }
                            syncPartsToHidden(root);
                        });

                        el.addEventListener('blur', () => {
                            window.setTimeout(() => commitTimeParts(root), 120);
                        });

                        el.addEventListener('keydown', (event) => {
                            if (event.key === 'ArrowDown') {
                                event.preventDefault();
                                openTimePickerMenu(root);
                            } else if (event.key === 'Escape') {
                                closeAllTimePickerMenus();
                            } else if (event.key === 'Enter') {
                                event.preventDefault();
                                commitTimeParts(root);
                                closeAllTimePickerMenus();
                                el.blur();
                            }
                        });
                    }

                    if (el instanceof HTMLSelectElement) {
                        el.addEventListener('change', () => {
                            commitTimeParts(root);
                        });
                    }
                };

                bindSegment(hourEl, 'hour');
                bindSegment(minuteEl, 'minute');
                bindSegment(meridiemEl, 'meridiem');

                menu?.querySelectorAll('[data-time-value]').forEach((btn) => {
                    btn.addEventListener('click', (event) => {
                        event.preventDefault();
                        const value = btn.getAttribute('data-time-value') || '';
                        if (hidden instanceof HTMLInputElement) {
                            setTimePickerValue(hidden, value);
                        }
                        closeAllTimePickerMenus();
                        hourEl?.focus();
                    });
                });
            });

            document.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Element)) return;
                if (target.closest('[data-schedule-time-picker]')) return;
                closeAllTimePickerMenus();
            });
        }

        function setTimeSelectValue(select, value) {
            setTimePickerValue(select, value, { fallback: '09:00' });
        }

        function reindexScheduleBreaks() {
            const list = breaksWrap?.querySelector('[data-shift-breaks-list]');
            const addBtn = breaksWrap?.querySelector('[data-shift-breaks-add]');
            if (!list) return;
            list.querySelectorAll('[data-shift-breaks-row]').forEach((row, index) => {
                row.querySelectorAll('input, select').forEach((field) => {
                    const name = field.getAttribute('name');
                    if (!name) return;
                    field.setAttribute(
                        'name',
                        name.replace(/shift_breaks\[(?:\d+|__INDEX__)]/, `shift_breaks[${index}]`),
                    );
                });
            });
            if (addBtn instanceof HTMLButtonElement) {
                addBtn.disabled = list.querySelectorAll('[data-shift-breaks-row]').length >= 8;
            }
        }

        function setBreakFieldsDisabled(disabled) {
            breaksWrap?.querySelectorAll('input, select, button').forEach((el) => {
                if (el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLButtonElement) {
                    el.disabled = disabled;
                }
            });
        }

        function fillBreaks(breaks) {
            const list = breaksWrap?.querySelector('[data-shift-breaks-list]');
            const template = breaksWrap?.querySelector('[data-shift-breaks-template]');
            if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) return;

            const rows = Array.isArray(breaks)
                ? breaks.filter((row) => row && Number(row.minutes) > 0)
                : [];
            const toRender = rows.length > 0
                ? rows
                : [{ label: '', minutes: '', paid: false }];

            list.innerHTML = '';
            toRender.forEach((breakRow) => {
                const fragment = template.content.cloneNode(true);
                const row = fragment.querySelector('[data-shift-breaks-row]');
                if (!(row instanceof HTMLElement)) return;

                const labelInput = row.querySelector('input[name*="[label]"]');
                const minutesInput = row.querySelector('input[name*="[minutes]"]');
                const paidSelect = row.querySelector('select[name*="[paid]"]');

                if (labelInput instanceof HTMLInputElement) {
                    labelInput.value = breakRow.label != null ? String(breakRow.label) : '';
                }
                if (minutesInput instanceof HTMLInputElement) {
                    minutesInput.value = breakRow.minutes != null && breakRow.minutes !== ''
                        ? String(breakRow.minutes)
                        : '';
                }
                if (paidSelect instanceof HTMLSelectElement) {
                    const paid = breakRow.paid === true
                        || breakRow.paid === 1
                        || breakRow.paid === '1'
                        || breakRow.paid === 'paid';
                    paidSelect.value = paid ? '1' : '0';
                }

                list.appendChild(fragment);
            });

            reindexScheduleBreaks();
        }

        function weekdayKeyFromIso(isoDate) {
            if (!isoDate) return null;
            const date = new Date(isoDate + 'T12:00:00');
            if (Number.isNaN(date.getTime())) return null;
            return ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][date.getDay()] || null;
        }

        function addWeeksIso(isoDate, weeks) {
            if (!isoDate) return '';
            const date = new Date(isoDate + 'T12:00:00');
            if (Number.isNaN(date.getTime())) return '';
            date.setDate(date.getDate() + (weeks * 7));
            return date.toISOString().slice(0, 10);
        }

        function addYearsIso(isoDate, years) {
            if (!isoDate) return '';
            const date = new Date(isoDate + 'T12:00:00');
            if (Number.isNaN(date.getTime())) return '';
            date.setFullYear(date.getFullYear() + years);
            return date.toISOString().slice(0, 10);
        }

        function setRecurrenceUntilOneYear() {
            const startIso = dateInput?.value || '';
            if (!recurrenceUntilEl || !startIso) return;
            recurrenceUntilEl.value = addYearsIso(startIso, 1);
            recurrenceUntilEl.min = startIso;
        }

        function syncRecurrenceStartsLabel() {
            if (!recurrenceStartsEl) return;
            const iso = dateInput?.value || '';
            recurrenceStartsEl.value = iso;
            if (iso) {
                recurrenceStartsEl.max = '';
                if (recurrenceUntilEl?.value && recurrenceUntilEl.value < iso) {
                    recurrenceUntilEl.value = addWeeksIso(iso, 12);
                }
                if (recurrenceUntilEl) recurrenceUntilEl.min = iso;
            }
        }

        function setRecurrenceMode(mode) {
            const value = String(mode || 'never');
            const allowed = [
                'never', 'every_week',
                'every_2_weeks', 'every_3_weeks', 'every_4_weeks',
                'every_5_weeks', 'every_6_weeks', 'every_7_weeks', 'every_8_weeks',
            ];
            const next = allowed.includes(value) ? value : 'never';
            if (recurrenceModeEl) recurrenceModeEl.value = next;

            const showDetails = next !== 'never';
            recurrenceDetails?.classList.toggle('hidden', !showDetails);
            recurrenceEndsWrap?.classList.toggle('hidden', !showDetails);
            recurrenceOneYearWrap?.classList.toggle('hidden', !showDetails);
            if (recurrenceUntilEl) {
                recurrenceUntilEl.disabled = !showDetails;
            }
            if (recurrenceStartsEl) {
                recurrenceStartsEl.disabled = !showDetails;
            }
            if (recurrenceOneYearBtn) {
                recurrenceOneYearBtn.disabled = !showDetails;
            }
            if (showDetails && recurrenceUntilEl && !recurrenceUntilEl.value && dateInput?.value) {
                recurrenceUntilEl.value = addWeeksIso(dateInput.value, 12);
                recurrenceUntilEl.min = dateInput.value;
            }
            syncRecurrenceStartsLabel();
        }

        function resetRecurrence() {
            setRecurrenceMode('never');
            const dayKey = weekdayKeyFromIso(dateInput?.value || '');
            form.querySelectorAll('#schedule-recurrence-details input[name="shift_days[]"]').forEach((input) => {
                if (input instanceof HTMLInputElement) {
                    input.checked = Boolean(dayKey) && input.value === dayKey;
                }
            });
            if (recurrenceUntilEl) {
                recurrenceUntilEl.value = addWeeksIso(dateInput?.value || '', 12);
            }
            syncRecurrenceStartsLabel();
        }

        function updateTabUi(type) {
            entryTabs?.querySelectorAll('[data-schedule-tab]').forEach((tab) => {
                const active = tab.getAttribute('data-schedule-tab') === type;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.classList.toggle('bg-white', active);
                tab.classList.toggle('text-brand-primary', active);
                tab.classList.toggle('shadow-sm', active);
                tab.classList.toggle('ring-1', active);
                tab.classList.toggle('ring-brand-border/60', active);
                tab.classList.toggle('text-brand-text-secondary', !active);
            });
        }

        function initialsFromName(name) {
            const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
            if (parts.length >= 2) {
                return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
            }
            if (parts.length === 1 && parts[0].length > 0) {
                return parts[0].slice(0, 2).toUpperCase();
            }
            return '—';
        }

        function findModalEmployee(id) {
            return modalEmployees.find((row) => String(row.id) === String(id)) || null;
        }

        function populateJobTitleOptions(employeePublicId, selectedId) {
            if (!jobTitleEl) return;
            const emp = findModalEmployee(employeePublicId);
            const titles = Array.isArray(emp?.job_titles) ? emp.job_titles : [];
            const preferred = selectedId || emp?.job_title_id || titles[0]?.id || '';
            jobTitleEl.innerHTML = '';
            if (!titles.length) {
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = 'No titles assigned';
                jobTitleEl.appendChild(empty);
                jobTitleEl.value = '';
                return;
            }
            titles.forEach((jt) => {
                const opt = document.createElement('option');
                opt.value = String(jt.id);
                opt.textContent = jt.wage ? `${jt.name} ($${jt.wage})` : jt.name;
                jobTitleEl.appendChild(opt);
            });
            if (preferred && titles.some((jt) => String(jt.id) === String(preferred))) {
                jobTitleEl.value = String(preferred);
            } else {
                jobTitleEl.value = String(titles[0].id);
            }
        }

        function syncEmployeeHiddenInputs() {
            const ids = selectedEmployeeIds.slice();
            if (employeeHidden) {
                employeeHidden.value = ids[0] || '';
            }

            form.querySelectorAll('input[name="employee_public_ids[]"]').forEach((el) => el.remove());
            ids.forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'employee_public_ids[]';
                input.value = id;
                form.appendChild(input);
            });
        }

        function refreshEmployeeSummary() {
            const primary = findModalEmployee(selectedEmployeeIds[0]);
            const primaryName = primary?.label
                || (selectedEmployeeIds.length ? 'Employee' : 'Select employees');
            const extra = Math.max(0, selectedEmployeeIds.length - 1);

            if (employeeChipName) {
                employeeChipName.textContent = extra > 0
                    ? `${primaryName} +${extra}`
                    : primaryName;
            }
            if (employeeChipNameTimeoff) employeeChipNameTimeoff.textContent = primaryName;
            if (employeeAvatarEl) employeeAvatarEl.textContent = initialsFromName(primaryName);
            if (employeeChipMeta) {
                if (selectedEmployeeIds.length > 1) {
                    employeeChipMeta.textContent = `${selectedEmployeeIds.length} employees selected`;
                } else if (primary?.job_title && primary.job_title !== '—') {
                    employeeChipMeta.textContent = primary.job_title;
                } else {
                    employeeChipMeta.textContent = '';
                }
            }

            const chipHost = employeePickerLocked
                ? document.getElementById('schedule-employees-selected-edit')
                : employeesSelectedEl;

            if (chipHost) {
                chipHost.innerHTML = '';
                selectedEmployeeIds.forEach((id) => {
                    const row = findModalEmployee(id);
                    const chip = document.createElement('span');
                    chip.className = 'inline-flex max-w-full items-center gap-1.5 rounded-full bg-brand-primary/10 px-2.5 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-brand-primary/20';
                    const label = document.createElement('span');
                    label.className = 'truncate';
                    label.textContent = row?.label || id;
                    chip.appendChild(label);
                    if (!employeePickerLocked) {
                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'rounded-full p-0.5 hover:bg-brand-primary/15';
                        remove.setAttribute('aria-label', `Remove ${row?.label || 'employee'}`);
                        remove.innerHTML = '<svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
                        remove.addEventListener('click', () => {
                            selectedEmployeeIds = selectedEmployeeIds.filter((value) => value !== id);
                            syncEmployeeSelectionUi();
                        });
                        chip.appendChild(remove);
                    }
                    chipHost.appendChild(chip);
                });
            }

            if (employeesEditNameEl) {
                employeesEditNameEl.textContent = primaryName;
            }

            syncEmployeeHiddenInputs();
        }

        function refreshEmployeeAddSelect() {
            if (!employeeAddEl) return;
            const available = modalEmployees.filter((row) => !selectedEmployeeIds.includes(String(row.id)));
            employeeAddEl.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = available.length ? 'Add employee(s)…' : 'All employees added';
            placeholder.disabled = false;
            placeholder.selected = true;
            employeeAddEl.appendChild(placeholder);

            available.forEach((row) => {
                const opt = document.createElement('option');
                opt.value = String(row.id);
                const title = row.job_title && row.job_title !== '—' ? ` — ${row.job_title}` : '';
                opt.textContent = `${row.label || 'Employee'}${title}`;
                employeeAddEl.appendChild(opt);
            });

            employeeAddEl.disabled = employeePickerLocked || available.length === 0;
        }

        function setEmployeePickerMode(locked) {
            employeePickerLocked = Boolean(locked);
            employeesPickerEl?.classList.toggle('hidden', employeePickerLocked);
            employeesEditOnlyEl?.classList.toggle('hidden', !employeePickerLocked);
            if (employeeAddEl) employeeAddEl.value = '';
        }

        function syncEmployeeSelectionUi() {
            refreshEmployeeSummary();
            refreshEmployeeAddSelect();

            const primaryId = selectedEmployeeIds[0] || '';
            if (leaveTypeEl && entryTypeHidden?.value === TYPE_TIME_OFF) {
                populateLeaveTypeOptions(primaryId, leaveTypeEl.value || '');
                updateLeaveBalanceUi();
            }
        }

        function setSelectedEmployees(ids, options = {}) {
            const unique = [];
            (ids || []).forEach((id) => {
                const value = String(id || '').trim();
                if (value !== '' && !unique.includes(value)) unique.push(value);
            });
            selectedEmployeeIds = unique;
            setEmployeePickerMode(Boolean(options.locked));
            syncEmployeeSelectionUi();
            populateJobTitleOptions(unique[0] || '', options.jobTitleId || '');
        }

        function setEmployeeChip(name) {
            const label = name && String(name).trim() !== '' ? name : 'Employee';
            if (employeeChipName) employeeChipName.textContent = label;
            if (employeeChipNameTimeoff) employeeChipNameTimeoff.textContent = label;
            if (employeeAvatarEl) employeeAvatarEl.textContent = initialsFromName(label);
        }

        function populateLeaveTypeOptions(employeePublicId, selectedId) {
            if (!leaveTypeEl) return;
            const list = leaveBalances[employeePublicId] || [];
            leaveTypeEl.innerHTML = '<option value="">No leave type (unpaid day off)</option>';
            list.forEach((item) => {
                const opt = document.createElement('option');
                opt.value = String(item.id);
                opt.textContent = item.name + (item.is_paid ? '' : ' (Unpaid)');
                opt.dataset.allocated = item.allocated === null ? '' : String(item.allocated);
                opt.dataset.used = item.used === null ? '' : String(item.used);
                opt.dataset.remaining = item.remaining === null ? '' : String(item.remaining);
                opt.dataset.isPaid = item.is_paid ? '1' : '0';
                leaveTypeEl.appendChild(opt);
            });
            leaveTypeEl.value = selectedId ? String(selectedId) : '';
            if (selectedId && leaveTypeEl.value !== String(selectedId)) {
                leaveTypeEl.value = '';
            }
        }

        function updateLeaveBalanceUi() {
            if (!leaveTypeEl) return;
            const option = leaveTypeEl.selectedOptions[0];
            const hasType = Boolean(leaveTypeEl.value);
            const isTimeOff = entryTypeHidden.value === TYPE_TIME_OFF;

            if (leaveHoursWrap) leaveHoursWrap.classList.toggle('hidden', !hasType);
            if (leaveHoursEl) leaveHoursEl.required = hasType && isTimeOff;

            if (!hasType || !option) {
                leaveBalancePanel?.classList.add('hidden');
                return;
            }

            leaveBalancePanel?.classList.remove('hidden');
            const isPaid = option.dataset.isPaid === '1';
            if (leaveBalanceName) leaveBalanceName.textContent = option.textContent;
            if (leaveBalancePaid) {
                leaveBalancePaid.textContent = isPaid ? 'Paid' : 'Unpaid';
                leaveBalancePaid.classList.toggle('text-emerald-600', isPaid);
                leaveBalancePaid.classList.toggle('text-slate-500', !isPaid);
            }

            const allocated = option.dataset.allocated;
            const remaining = option.dataset.remaining;
            if (leaveAllocatedEl) leaveAllocatedEl.textContent = allocated === '' ? '—' : fmtHours(allocated) + 'h';
            if (leaveUsedEl) leaveUsedEl.textContent = fmtHours(option.dataset.used) + 'h';
            if (leaveRemainingEl) leaveRemainingEl.textContent = remaining === '' ? '—' : fmtHours(remaining) + 'h';
        }

        const STATUS_NO_SHOW = @json(EmployeeScheduleShift::STATUS_NO_SHOW);

        function updateStatusUi(status) {
            const label = STATUS_LABELS[status] || '';

            if (statusBadge) {
                statusBadge.textContent = label;
                statusBadge.classList.toggle('hidden', label === '');
                statusBadge.classList.toggle('inline-flex', label !== '');
                const isNoShow = status === STATUS_NO_SHOW;
                statusBadge.classList.toggle('bg-red-100', isNoShow);
                statusBadge.classList.toggle('text-red-700', isNoShow);
                statusBadge.classList.toggle('ring-red-200', isNoShow);
                statusBadge.classList.toggle('bg-amber-100', label !== '' && !isNoShow);
                statusBadge.classList.toggle('text-amber-800', label !== '' && !isNoShow);
                statusBadge.classList.toggle('ring-amber-200', label !== '' && !isNoShow);
            }

            if (clearStatusButton) {
                clearStatusButton.classList.toggle('hidden', label === '');
                clearStatusButton.classList.toggle('flex', label !== '');
            }
        }

        function hideDetailActions() {
            menuToggle?.classList.add('hidden');
            closeDetailMenu();
            updateStatusUi('');
        }

        function populateSharedHeader(payload, isSuggestion) {
            employeeLabelEl.textContent = payload.employeeName || 'Employee';
            dateLabelEl.textContent = payload.dayLabel || formatDateLabel(payload.scheduledDate);
            suggestionBanner.classList.toggle('hidden', !isSuggestion);
            setEmployeeChip(payload.employeeName);
        }

        function openDetails(payload) {
            currentPayload = payload;
            openedFromDetails = false;

            const entryType = payload.entryType || TYPE_SHIFT;
            const isTimeOff = entryType === TYPE_TIME_OFF;

            populateSharedHeader(payload, false);

            modeEl.textContent = isTimeOff ? 'Day off' : 'Scheduled shift';
            titleEl.textContent = isTimeOff ? 'Day off details' : 'Shift details';

            setDetailText(detailDateEl, payload.dayLabel || formatDateLabel(payload.scheduledDate));
            const positionText = findModalEmployee(payload.employeePublicId)?.job_title
                || payload.blockTitle
                || '';
            setDetailText(
                detailPositionEl,
                positionText && positionText !== '—' ? positionText : 'No job title set',
            );
            setDetailText(detailTimeEl, isTimeOff ? 'All day' : payload.timeRange);
            setDetailText(detailDurationEl, isTimeOff ? 'Day off' : payload.durationLabel);
            setDetailText(detailBreaksEl, payload.breaksLabel);
            setDetailText(detailRecurrenceEl, payload.recurrenceLabel || 'This date only');
            setDetailText(detailMetaEl, payload.blockMeta);
            setDetailText(detailShiftEl, payload.leaveTypeName);

            toggleDetailRow(detailDateRow, true);
            toggleDetailRow(detailTimeRow, true);
            toggleDetailRow(detailDurationRow, true);
            toggleDetailRow(detailBreaksRow, !isTimeOff && Boolean(payload.breaksLabel));
            toggleDetailRow(detailRecurrenceRow, !isTimeOff);
            toggleDetailRow(detailMetaRow, !isTimeOff && Boolean(payload.blockMeta));
            toggleDetailRow(detailShiftRow, isTimeOff && Boolean(payload.leaveTypeName));

            if (isTimeOff && payload.leaveTypeName) {
                setDetailText(detailDurationEl, payload.leaveHours ? fmtHours(payload.leaveHours) + 'h leave' : 'Day off');
            }

            const notes = (payload.notes || '').trim();
            const reasonText = isTimeOff
                ? (notes || payload.blockSubtitle || '')
                : notes;
            const showNotes = isTimeOff || reasonText !== '';
            setDetailText(detailNotesEl, reasonText);
            if (detailNotesRow) {
                detailNotesRow.classList.toggle('hidden', !showNotes);
                const notesLabel = detailNotesRow.querySelector('dt');
                if (notesLabel) {
                    notesLabel.textContent = isTimeOff
                        ? 'Reason'
                        : (payload.status ? 'Comment' : 'Notes');
                }
            }

            deleteForm.action = destroyUrlTemplate.replace('__ID__', payload.shiftId);

            const canMark = !isTimeOff && Boolean(payload.shiftId);
            closeDetailMenu();
            if (menuToggle) {
                menuToggle.classList.toggle('hidden', !canMark);
            }
            if (canMark) {
                statusForm.action = statusUrlTemplate.replace('__ID__', payload.shiftId);
                updateStatusUi(payload.status || '');
            } else {
                updateStatusUi('');
            }

            if (detailEditButton) {
                detailEditButton.textContent = isTimeOff ? 'Edit day off' : 'Edit shift';
            }

            showDetailsView();

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            detailEditButton?.focus();
        }

        function applyEntryType(type) {
            entryTypeHidden.value = type;
            const isTimeOff = type === TYPE_TIME_OFF;

            shiftFields.classList.toggle('hidden', isTimeOff);
            timeOffFields.classList.toggle('hidden', !isTimeOff);
            updateTabUi(type);

            if (startInput) startInput.required = !isTimeOff;
            if (endInput) endInput.required = !isTimeOff;
            if (workLocationEl) workLocationEl.required = !isTimeOff;
            if (workLocationEl) workLocationEl.disabled = isTimeOff;
            if (jobTitleEl) jobTitleEl.disabled = isTimeOff;
            if (startInput) startInput.disabled = isTimeOff;
            if (endInput) endInput.disabled = isTimeOff;
            document.querySelectorAll('[data-schedule-time-picker]').forEach((root) => {
                root.querySelectorAll('[data-time-hour], [data-time-minute], [data-time-meridiem], [data-time-picker-toggle]').forEach((el) => {
                    if (el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLButtonElement) {
                        el.disabled = isTimeOff;
                    }
                });
                if (isTimeOff) {
                    root.querySelector('[data-time-picker-menu]')?.classList.add('hidden');
                }
            });
            setBreakFieldsDisabled(isTimeOff);
            if (shiftNotesEl) shiftNotesEl.disabled = isTimeOff;
            if (recurrenceModeEl) recurrenceModeEl.disabled = isTimeOff;
            if (recurrenceUntilEl) recurrenceUntilEl.disabled = isTimeOff || recurrenceModeEl?.value === 'never';
            if (recurrenceStartsEl) recurrenceStartsEl.disabled = isTimeOff || recurrenceModeEl?.value === 'never';
            if (recurrenceOneYearBtn) recurrenceOneYearBtn.disabled = isTimeOff || recurrenceModeEl?.value === 'never';
            form.querySelectorAll('#schedule-recurrence-details input[name="shift_days[]"]').forEach((input) => {
                if (input instanceof HTMLInputElement) input.disabled = isTimeOff;
            });
            if (notesEl) notesEl.disabled = !isTimeOff;

            if (leaveTypeEl) leaveTypeEl.disabled = !isTimeOff;
            if (leaveHoursEl) leaveHoursEl.disabled = !isTimeOff;

            const isEdit = entryTypeHidden.dataset.edit === '1';
            if (formTitleEl) {
                formTitleEl.textContent = isTimeOff
                    ? (isEdit ? 'Edit day off' : 'New day off')
                    : (isEdit ? 'Edit shift' : 'New shift');
            }
            submitButton.textContent = isTimeOff ? 'Save day off' : 'Save';
        }

        function switchToShiftMode(defaultLocationId) {
            entryTypeHidden.dataset.edit = '0';
            if (timeOffRequestIdInput) timeOffRequestIdInput.value = '';
            applyEntryType(TYPE_SHIFT);
            form.action = storeUrl;
            deleteButton.classList.add('hidden');
            convertToShiftButton.classList.add('hidden');
            workLocationEl.value = defaultLocationId ? String(defaultLocationId) : '';
            if (shiftNotesEl) shiftNotesEl.value = '';
            if (notesEl) notesEl.value = '';
            setTimeSelectValue(startInput, '09:00');
            setTimeSelectValue(endInput, '17:00');
            fillBreaks([]);
            resetRecurrence();
            recurrenceWrap?.classList.remove('hidden');
            if (leaveTypeEl) leaveTypeEl.value = '';
            updateLeaveBalanceUi();
            document.getElementById('schedule-start-hour')?.focus();
        }

        function openEditForm(payload, fromDetails) {
            currentPayload = payload;
            openedFromDetails = Boolean(fromDetails);

            if (timeOffRequestIdInput) timeOffRequestIdInput.value = '';

            const isEdit = Boolean(payload.shiftId);
            const isSuggestion = payload.isSuggestion === '1' || payload.isSuggestion === true;
            const entryType = isSuggestion ? TYPE_SHIFT : (payload.entryType || TYPE_SHIFT);

            entryTypeHidden.dataset.edit = isEdit ? '1' : '0';
            hideDetailActions();
            populateSharedHeader(payload, isSuggestion);

            setSelectedEmployees(
                [payload.employeePublicId || ''],
                { locked: isEdit, jobTitleId: payload.jobTitleId || '' },
            );
            if (dateInput) dateInput.value = payload.scheduledDate || '';

            applyEntryType(entryType);

            workLocationEl.value = payload.workLocationId ? String(payload.workLocationId) : '';
            if (jobTitleEl && payload.jobTitleId) {
                jobTitleEl.value = String(payload.jobTitleId);
            }
            setTimeSelectValue(startInput, payload.startTime || '09:00');
            setTimeSelectValue(endInput, payload.endTime || '17:00');
            if (shiftNotesEl) shiftNotesEl.value = entryType === TYPE_SHIFT ? (payload.notes || '') : '';
            if (notesEl) notesEl.value = entryType === TYPE_TIME_OFF ? (payload.notes || '') : '';
            fillBreaks(parseBreaks(payload.breaks));
            resetRecurrence();
            recurrenceWrap?.classList.toggle('hidden', isEdit && entryType === TYPE_SHIFT);
            populateLeaveTypeOptions(payload.employeePublicId, payload.leaveTypeId);
            if (leaveHoursEl) leaveHoursEl.value = payload.leaveHours || '';
            updateLeaveBalanceUi();
            if (convertToShiftButton) {
                convertToShiftButton.dataset.defaultLocationId = payload.workLocationId || '';
            }

            if (isSuggestion) {
                if (formTitleEl) formTitleEl.textContent = 'Suggested shift';
                submitButton.textContent = 'Save to roster';
                recurrenceWrap?.classList.add('hidden');
            }

            form.action = isEdit ? updateUrlTemplate.replace('__ID__', payload.shiftId) : storeUrl;
            deleteButton.classList.toggle('hidden', !isEdit);
            convertToShiftButton.classList.toggle('hidden', !isEdit || entryType !== TYPE_TIME_OFF);
            if (isEdit) {
                deleteForm.action = destroyUrlTemplate.replace('__ID__', payload.shiftId);
            }

            showFormView();

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            if (entryType === TYPE_TIME_OFF) {
                notesEl?.focus();
            } else {
                document.getElementById('schedule-start-hour')?.focus();
            }
        }

        function openModal(payload) {
            const isSuggestion = payload.isSuggestion === '1' || payload.isSuggestion === true;
            const hasExistingShift = Boolean(payload.shiftId) && !isSuggestion;

            if (hasExistingShift) {
                openDetails(payload);
                return;
            }

            openEditForm(payload, false);
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            closeAllTimePickerMenus();
            closeDetailMenu();
            currentPayload = null;
            openedFromDetails = false;
        }

        function handleCancel() {
            if (openedFromDetails && currentPayload) {
                openDetails(currentPayload);
                return;
            }

            closeModal();
        }

        function payloadFromTrigger(el) {
            return {
                shiftId: el.dataset.shiftId || '',
                employeePublicId: el.dataset.employeePublicId || '',
                employeeName: el.dataset.employeeName || '',
                dayLabel: el.dataset.dayLabel || '',
                scheduledDate: el.dataset.scheduledDate || '',
                entryType: el.dataset.entryType || TYPE_SHIFT,
                workLocationId: el.dataset.workLocationId || '',
                jobTitleId: el.dataset.jobTitleId || '',
                notes: el.dataset.notes || '',
                isSuggestion: el.dataset.isSuggestion || '0',
                timeRange: el.dataset.timeRange || '',
                durationLabel: el.dataset.durationLabel || '',
                startTime: el.dataset.startTime || '',
                endTime: el.dataset.endTime || '',
                breaks: el.dataset.breaks || '[]',
                breaksLabel: el.dataset.breaksLabel || '',
                recurrenceLabel: el.dataset.recurrenceLabel || '',
                blockTitle: el.dataset.blockTitle || '',
                blockSubtitle: el.dataset.blockSubtitle || '',
                blockMeta: el.dataset.blockMeta || '',
                status: el.dataset.status || '',
                leaveTypeId: el.dataset.leaveTypeId || '',
                leaveHours: el.dataset.leaveHours || '',
                leaveTypeName: el.dataset.leaveTypeName || '',
            };
        }

        document.querySelectorAll('[data-schedule-open]').forEach((el) => {
            el.addEventListener('click', () => openModal(payloadFromTrigger(el)));
        });
        leaveTypeEl?.addEventListener('change', updateLeaveBalanceUi);
        recurrenceModeEl?.addEventListener('change', () => {
            setRecurrenceMode(recurrenceModeEl.value);
        });
        recurrenceOneYearBtn?.addEventListener('click', () => {
            setRecurrenceUntilOneYear();
        });
        recurrenceStartsEl?.addEventListener('change', () => {
            if (!dateInput || !recurrenceStartsEl.value) return;
            dateInput.value = recurrenceStartsEl.value;
            dateInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
        dateInput?.addEventListener('change', () => {
            if (dateLabelEl) dateLabelEl.textContent = formatDateLabel(dateInput.value);
            syncRecurrenceStartsLabel();
            const dayKey = weekdayKeyFromIso(dateInput.value);
            const checked = form.querySelectorAll('#schedule-recurrence-details input[name="shift_days[]"]:checked');
            if (checked.length === 0 && dayKey) {
                form.querySelectorAll('#schedule-recurrence-details input[name="shift_days[]"]').forEach((input) => {
                    if (input instanceof HTMLInputElement) {
                        input.checked = input.value === dayKey;
                    }
                });
            }
            if (recurrenceUntilEl && recurrenceModeEl?.value !== 'never') {
                if (!recurrenceUntilEl.value || recurrenceUntilEl.value < dateInput.value) {
                    recurrenceUntilEl.value = addWeeksIso(dateInput.value, 12);
                }
                recurrenceUntilEl.min = dateInput.value || '';
            }
        });

        entryTabs?.querySelectorAll('[data-schedule-tab]').forEach((tab) => {
            tab.addEventListener('click', () => {
                const type = tab.getAttribute('data-schedule-tab') || TYPE_SHIFT;
                if (entryTypeHidden.dataset.edit === '1' && type !== entryTypeHidden.value) {
                    // Allow converting day off → shift via tab when editing
                    if (type === TYPE_SHIFT && entryTypeHidden.value === TYPE_TIME_OFF) {
                        switchToShiftMode(convertToShiftButton?.dataset.defaultLocationId || workLocationEl.value);
                        return;
                    }
                }
                applyEntryType(type);
            });
        });

        form.addEventListener('submit', (event) => {
            document.querySelectorAll('[data-schedule-time-picker]').forEach((root) => {
                if (root instanceof HTMLElement) commitTimeParts(root);
            });
            closeAllTimePickerMenus();
            syncEmployeeHiddenInputs();

            if (selectedEmployeeIds.length === 0) {
                event.preventDefault();
                employeeAddEl?.focus();
                return;
            }

            if (entryTypeHidden.value === TYPE_TIME_OFF) {
                if (leaveTypeEl && leaveTypeEl.value && (!leaveHoursEl.value || Number(leaveHoursEl.value) <= 0)) {
                    event.preventDefault();
                    leaveHoursEl.focus();
                }
                return;
            }
            if (!workLocationEl.value || !startInput.value || !endInput.value || !dateInput.value) {
                event.preventDefault();
                if (!dateInput.value) dateInput.focus();
                else if (!startInput.value) document.getElementById('schedule-start-hour')?.focus();
                else if (!endInput.value) document.getElementById('schedule-end-hour')?.focus();
                else workLocationEl.focus();
            }
        });

        employeeAddEl?.addEventListener('change', () => {
            const id = String(employeeAddEl.value || '').trim();
            if (!id) return;
            if (!selectedEmployeeIds.includes(id)) {
                selectedEmployeeIds.push(id);
                syncEmployeeSelectionUi();
            }
            employeeAddEl.value = '';
        });

        document.getElementById('schedule-shift-modal-close')?.addEventListener('click', closeModal);
        document.getElementById('schedule-shift-modal-close-details')?.addEventListener('click', closeModal);
        document.getElementById('schedule-shift-cancel')?.addEventListener('click', handleCancel);

        menuToggle?.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = detailMenu.classList.contains('hidden');
            detailMenu.classList.toggle('hidden', !willOpen);
            menuToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        detailMenu?.querySelectorAll('[data-status-action]').forEach((button) => {
            button.addEventListener('click', async () => {
                if (!currentPayload || !currentPayload.shiftId) return;

                const status = button.dataset.statusAction || '';
                const isClearing = status === '';
                let notes = '';

                if (!isClearing) {
                    const statusLabel = STATUS_LABELS[status] || 'status';
                    const dialog = window.CruLynkDialog;
                    const existingNotes = (currentPayload.notes || '').trim();

                    if (dialog && typeof dialog.promptNote === 'function') {
                        const note = await dialog.promptNote({
                            title: 'Mark as ' + statusLabel + '?',
                            text: (currentPayload.employeeName || 'Employee') + ' · ' + (currentPayload.dayLabel || formatDateLabel(currentPayload.scheduledDate)),
                            inputLabel: 'Comment (optional)',
                            inputPlaceholder: 'e.g. Worked 2 hours before calling in sick',
                            inputValue: existingNotes,
                            confirmText: 'Mark ' + statusLabel.toLowerCase(),
                            cancelText: 'Cancel',
                            danger: status === STATUS_NO_SHOW,
                        });

                        if (note === null) {
                            closeDetailMenu();
                            return;
                        }

                        notes = note;
                    } else {
                        const fallback = window.prompt(
                            'Comment (optional) for ' + statusLabel.toLowerCase() + ':',
                            existingNotes
                        );
                        if (fallback === null) {
                            closeDetailMenu();
                            return;
                        }
                        notes = fallback.trim();
                    }
                }

                closeDetailMenu();
                statusForm.action = statusUrlTemplate.replace('__ID__', currentPayload.shiftId);
                statusValueInput.value = status;
                if (statusNotesInput) {
                    statusNotesInput.value = notes;
                }
                statusForm.requestSubmit();
            });
        });

        document.addEventListener('click', (event) => {
            if (!detailMenu || detailMenu.classList.contains('hidden')) return;
            if (detailMenu.contains(event.target) || menuToggle?.contains(event.target)) return;
            closeDetailMenu();
        });
        detailEditButton?.addEventListener('click', () => {
            if (currentPayload) {
                openEditForm(currentPayload, true);
            }
        });
        detailDeleteButton?.addEventListener('click', () => deleteForm.requestSubmit());
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            if (modal.classList.contains('hidden')) return;
            const openTimeMenu = modal.querySelector('[data-time-picker-menu]:not(.hidden)');
            if (openTimeMenu) {
                closeAllTimePickerMenus();
                return;
            }
            if (detailMenu && !detailMenu.classList.contains('hidden')) {
                closeDetailMenu();
                return;
            }
            closeModal();
        });
        deleteButton.addEventListener('click', () => deleteForm.requestSubmit());
        convertToShiftButton?.addEventListener('click', () => {
            switchToShiftMode(convertToShiftButton.dataset.defaultLocationId);
        });

        initTimePickers();
    })();
</script>
