@extends('layouts.admin')

@section('title', 'Employees — Weekly schedule')

@section('heading', 'Weekly schedule')

@section('subheading')
    {{ $company->name }}
@endsection


@section('content')
    @php
        use App\Models\EmployeeScheduleShift;

        /** @var \Carbon\CarbonInterface $weekStart */
        /** @var list<array<string, mixed>> $weekDays */
        /** @var list<array<string, mixed>> $scheduleRows */
        /** @var array<string, mixed> $scheduleStats */
        /** @var array<string, string> $filters */
        /** @var array{prev: string, next: string, today: string} $weekLinks */
        /** @var array<string, string|null> $redirectQuery */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Department> $departments */
        /** @var \Illuminate\Support\Collection<int, \App\Models\WorkLocation> $workLocations */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Shift> $shiftTemplates */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Employee> $employees */

        $employeeOptions = $employees->map(static function ($employee): array {
            $label = trim((string) ($employee->full_legal_name ?: $employee->email ?: ''));
            $email = trim((string) ($employee->email ?: ''));
            $code = trim((string) ($employee->employee_code ?: ''));

            return [
                'id' => $employee->public_id,
                'label' => $label,
                'email' => $email,
                'search' => strtolower(trim($label.' '.$email.' '.$code)),
            ];
        })->values();

        $selectedEmployee = $filters['employee'] !== ''
            ? $employees->firstWhere('public_id', $filters['employee'])
            : null;
        $selectedEmployeeLabel = $selectedEmployee
            ? ($selectedEmployee->full_legal_name ?: $selectedEmployee->email)
            : '';
    @endphp

    @push('scripts')
        @vite(['resources/js/employee-autocomplete.js'])
    @endpush

    <section class="mb-6 rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
        <div class="flex flex-col gap-4 rounded-t-2xl border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-4 py-4 lg:flex-row lg:items-center lg:justify-between sm:px-6">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Full schedule</p>
                    <p class="mt-1 text-lg font-bold text-brand-text">{{ $weekLabel }}</p>
                </div>
                <div class="inline-flex flex-wrap items-center gap-2">
                    <a href="{{ $weekLinks['prev'] }}" class="inline-flex size-9 items-center justify-center rounded-xl border border-brand-border bg-white text-brand-text shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface" aria-label="Previous week">←</a>
                    <a href="{{ $weekLinks['today'] }}" class="inline-flex items-center rounded-xl border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface">Today</a>
                    <a href="{{ $weekLinks['next'] }}" class="inline-flex size-9 items-center justify-center rounded-xl border border-brand-border bg-white text-brand-text shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface" aria-label="Next week">→</a>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="post" action="{{ route('admin.employees.weekly-schedule.fill-from-assignments') }}">
                    @csrf
                    @foreach ($redirectQuery as $key => $value)
                        @if ($value !== null && $value !== '')
                            <input type="hidden" name="redirect[{{ $key }}]" value="{{ $value }}">
                        @endif
                    @endforeach
                    <button type="submit" class="inline-flex items-center rounded-xl border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface">
                        Fill from assignments
                    </button>
                </form>
                <button
                    type="button"
                    id="schedule-create-shift"
                    class="inline-flex items-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-primary-dark"
                >
                    + Create shift
                </button>
            </div>
        </div>

        <form method="get" action="{{ route('admin.employees.weekly-schedule') }}" class="grid gap-3 border-b border-brand-border bg-brand-surface/40 px-4 py-4 sm:grid-cols-2 lg:grid-cols-5 sm:px-6">
            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">

            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Department</span>
                <select name="department_id" class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Work location</span>
                <select name="work_location_id" class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                    <option value="">All locations</option>
                    @foreach ($workLocations as $location)
                        <option value="{{ $location->id }}" @selected((string) $filters['work_location_id'] === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Employee</span>
                <div data-employee-autocomplete data-employees='@json($employeeOptions)'>
                    <input type="hidden" name="employee" value="{{ $filters['employee'] }}" data-employee-autocomplete-value>
                    <div class="relative">
                        <input
                            type="text"
                            value="{{ $selectedEmployeeLabel }}"
                            data-employee-autocomplete-input
                            class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 pe-9 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20"
                            placeholder="Search all employees…"
                            autocomplete="off"
                            aria-autocomplete="list"
                        >
                        <button
                            type="button"
                            data-employee-autocomplete-clear
                            class="absolute right-1 top-1/2 z-10 -translate-y-1/2 rounded-md p-1 text-brand-text-secondary/80 transition hover:bg-brand-surface hover:text-brand-text focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 {{ $selectedEmployeeLabel === '' ? 'hidden' : '' }}"
                            aria-label="Clear employee"
                        >
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                        <div data-employee-autocomplete-suggestions class="hidden" role="listbox" aria-label="Employee suggestions"></div>
                    </div>
                </div>
            </div>

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
                <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-primary-dark">
                    Apply filters
                </button>
                @if ($filters['department_id'] !== '' || $filters['work_location_id'] !== '' || $filters['employee'] !== '')
                    <a href="{{ route('admin.employees.weekly-schedule', ['week' => $weekStart->toDateString()]) }}" class="inline-flex items-center justify-center rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text-secondary transition hover:bg-brand-surface">
                        Clear
                    </a>
                @endif
            </div>
        </form>

        <div class="grid gap-px overflow-hidden rounded-b-2xl bg-brand-border/70 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-white px-4 py-3 sm:px-6">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Scheduled hours</p>
                <p class="mt-1 text-lg font-bold text-brand-text">{{ $scheduleStats['scheduled_hours_label'] }}</p>
            </div>
            <div class="bg-white px-4 py-3 sm:px-6">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Shifts</p>
                <p class="mt-1 text-lg font-bold text-brand-text">{{ $scheduleStats['shifts'] }}</p>
            </div>
            <div class="bg-white px-4 py-3 sm:px-6">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Absences</p>
                <p class="mt-1 text-lg font-bold text-brand-text">{{ $scheduleStats['absences'] }}</p>
            </div>
            <div class="bg-white px-4 py-3 sm:px-6">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Employees</p>
                <p class="mt-1 text-lg font-bold text-brand-text">{{ $scheduleStats['employees'] }}</p>
            </div>
        </div>
    </section>

    @if ($scheduleRows === [])
        <div class="rounded-2xl border border-dashed border-brand-border bg-brand-surface/50 px-6 py-12 text-center">
            <p class="text-sm font-semibold text-brand-text">No employees match these filters</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-brand-border bg-brand-surface/80 text-xs font-semibold uppercase tracking-wide text-brand-label">
                            <th class="sticky left-0 z-10 min-w-[240px] border-r border-brand-border bg-brand-surface/95 px-4 py-3 backdrop-blur sm:px-5">Employees</th>
                            @foreach ($weekDays as $day)
                                <th class="min-w-[148px] px-2 py-3 text-center {{ $day['is_today'] ? 'bg-brand-primary/[0.06] text-brand-primary' : '' }}">
                                    <span class="block">{{ $day['weekday_label'] }}</span>
                                    <span class="mt-0.5 block text-base font-bold {{ $day['is_today'] ? 'text-brand-primary' : 'text-brand-text' }}">{{ $day['day_number'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border/80">
                        @foreach ($scheduleRows as $row)
                            <tr class="align-top">
                                <td class="sticky left-0 z-10 border-r border-brand-border bg-white px-4 py-4 sm:px-5">
                                    <div class="flex items-start gap-3">
                                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-xs font-bold text-brand-primary ring-1 ring-brand-primary/15">
                                            {{ $row['initials'] }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-brand-text">{{ $row['name'] }}</p>
                                            <p class="mt-0.5 truncate text-xs text-brand-text-secondary">{{ $row['job_title'] }}</p>
                                            <p class="mt-2 text-[11px] font-semibold text-brand-label">
                                                {{ $row['week_scheduled_label'] }} scheduled
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                @foreach ($weekDays as $day)
                                    @php
                                        $cell = $row['cells'][$day['key']] ?? ['is_day_off' => false, 'blocks' => []];
                                        $blocks = $cell['blocks'] ?? [];
                                        $isDayOff = (bool) ($cell['is_day_off'] ?? false);
                                    @endphp
                                    <td class="px-1.5 py-1.5 align-top {{ $isDayOff ? 'bg-slate-50' : ($day['is_today'] ? 'bg-brand-primary/[0.03]' : '') }}">
                                        <div class="flex min-h-[108px] flex-col gap-1.5">
                                            @if ($isDayOff && $blocks !== [])
                                                @php
                                                    $block = $blocks[0];
                                                    $palette = $block['palette'];
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="schedule-shift-card group flex w-full flex-1 flex-col items-center justify-center rounded-xl border-2 border-dashed p-3 text-center shadow-sm transition {{ $palette['border'] }} {{ $palette['bg'] }} hover:ring-2 hover:ring-slate-300/50"
                                                    data-schedule-open="1"
                                                    data-shift-id="{{ $block['id'] ?? '' }}"
                                                    data-employee-public-id="{{ $block['employee_public_id'] ?? $row['employee_public_id'] }}"
                                                    data-employee-name="{{ $row['name'] }}"
                                                    data-day-label="{{ $day['date']->format('l, j M Y') }}"
                                                    data-scheduled-date="{{ $block['scheduled_date'] ?? $day['date_string'] }}"
                                                    data-entry-type="{{ EmployeeScheduleShift::TYPE_TIME_OFF }}"
                                                    data-shift-template-id="{{ $row['employee']->shift_id ?? '' }}"
                                                    data-work-location-id="{{ $row['employee']->work_location_id ?? '' }}"
                                                    data-notes="{{ $block['notes'] ?? '' }}"
                                                    data-is-suggestion="0"
                                                    data-time-range="{{ $block['time_range'] ?? 'All day' }}"
                                                    data-duration-label="{{ $block['duration_label'] ?? 'Day off' }}"
                                                    data-block-title="{{ $block['title'] ?? 'Day off' }}"
                                                    data-block-subtitle="{{ $block['subtitle'] ?? '' }}"
                                                    data-block-meta="{{ $block['meta'] ?? '' }}"
                                                    data-leave-type-id="{{ $block['leave_type_id'] ?? '' }}"
                                                    data-leave-type-name="{{ $block['leave_type_name'] ?? '' }}"
                                                    data-leave-hours="{{ $block['leave_hours'] ?? '' }}"
                                                >
                                                    <p class="text-sm font-bold uppercase tracking-wide {{ $palette['text'] }}">Day off</p>
                                                    <p class="mt-1 text-xs {{ $palette['text'] }} opacity-80">{{ ($block['leave_hours'] ?? null) ? rtrim(rtrim(number_format((float) $block['leave_hours'], 2), '0'), '.').'h leave' : 'All day' }}</p>
                                                    @if (($block['leave_type_name'] ?? '') !== '')
                                                        <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-white/70 px-2 py-0.5 text-[10px] font-semibold {{ $palette['text'] }} ring-1 ring-inset ring-slate-300/60">{{ $block['leave_type_name'] }}</span>
                                                    @elseif (($block['subtitle'] ?? '') !== '' && ($block['subtitle'] ?? '') !== 'Unavailable')
                                                        <p class="mt-2 text-xs font-medium {{ $palette['text'] }}">{{ $block['subtitle'] }}</p>
                                                    @endif
                                                </button>
                                            @else
                                            @foreach ($blocks as $block)
                                                @php
                                                    $palette = $block['palette'];
                                                    $isSuggestion = (bool) ($block['is_suggestion'] ?? false);
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="schedule-shift-card group relative w-full rounded-xl border p-2.5 text-left shadow-sm transition {{ $palette['border'] }} {{ $palette['bg'] }} {{ $isSuggestion ? 'border-dashed opacity-80 hover:opacity-100' : 'hover:ring-2 hover:ring-brand-primary/20' }}"
                                                    data-schedule-open="1"
                                                    data-shift-id="{{ $block['id'] ?? '' }}"
                                                    data-employee-public-id="{{ $block['employee_public_id'] ?? $row['employee_public_id'] }}"
                                                    data-employee-name="{{ $row['name'] }}"
                                                    data-employee-initials="{{ $row['initials'] }}"
                                                    data-day-label="{{ $day['date']->format('l, j M Y') }}"
                                                    data-scheduled-date="{{ $block['scheduled_date'] ?? $day['date_string'] }}"
                                                    data-entry-type="{{ $isSuggestion ? EmployeeScheduleShift::TYPE_SHIFT : ($block['type'] ?? EmployeeScheduleShift::TYPE_SHIFT) }}"
                                                    data-start-time="{{ $block['start_time'] ?? '' }}"
                                                    data-end-time="{{ $block['end_time'] ?? '' }}"
                                                    data-shift-template-id="{{ $block['shift_id'] ?? '' }}"
                                                    data-job-title-id="{{ $block['job_title_id'] ?? '' }}"
                                                    data-department-id="{{ $block['department_id'] ?? '' }}"
                                                    data-work-location-id="{{ $block['work_location_id'] ?? '' }}"
                                                    data-notes="{{ $block['notes'] ?? '' }}"
                                                    data-is-suggestion="{{ $isSuggestion ? '1' : '0' }}"
                                                    data-time-range="{{ $block['time_range'] ?? '' }}"
                                                    data-duration-label="{{ $block['duration_label'] ?? '' }}"
                                                    data-block-title="{{ $block['title'] ?? '' }}"
                                                    data-block-subtitle="{{ $block['subtitle'] ?? '' }}"
                                                    data-block-meta="{{ $block['meta'] ?? '' }}"
                                                    data-status="{{ $block['status'] ?? '' }}"
                                                    data-status-label="{{ $block['status_label'] ?? '' }}"
                                                >
                                                    @php
                                                        $blockStatus = $block['status'] ?? null;
                                                        $blockStatusLabel = $block['status_label'] ?? null;
                                                        $statusBadgeClass = $blockStatus === \App\Models\EmployeeScheduleShift::STATUS_NO_SHOW
                                                            ? 'bg-red-100 text-red-700 ring-red-200'
                                                            : 'bg-amber-100 text-amber-800 ring-amber-200';
                                                    @endphp
                                                    <p class="text-xs font-semibold leading-snug {{ $palette['text'] }} {{ $blockStatus ? 'line-through opacity-60' : '' }}">{{ $block['time_range'] }}</p>
                                                    <p class="mt-0.5 text-[11px] {{ $palette['text'] }} opacity-80">{{ $block['duration_label'] }}</p>
                                                    <p class="mt-2 text-xs font-semibold leading-snug {{ $palette['text'] }}">{{ $block['title'] }}</p>
                                                    @if (($block['subtitle'] ?? '') !== '')
                                                        <p class="mt-0.5 text-[11px] leading-snug {{ $palette['text'] }} opacity-85">{{ $block['subtitle'] }}</p>
                                                    @endif
                                                    @if (($block['meta'] ?? '') !== '')
                                                        <p class="mt-1 text-[10px] leading-snug {{ $palette['text'] }} opacity-70">{{ $block['meta'] }}</p>
                                                    @endif
                                                    @if ($blockStatusLabel)
                                                        <span class="mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ $statusBadgeClass }}">{{ $blockStatusLabel }}</span>
                                                    @endif
                                                    @unless ($isSuggestion)
                                                        <span class="absolute bottom-1.5 right-1.5 text-[10px] text-brand-text-secondary/50" aria-hidden="true">✥</span>
                                                    @endunless
                                                </button>
                                            @endforeach
                                            @endif

                                            <div class="flex flex-col gap-1 {{ $isDayOff ? '' : 'flex-1' }}">
                                                <button
                                                    type="button"
                                                    class="schedule-add-cell flex items-center justify-center rounded-xl border border-dashed border-brand-border/80 px-2 py-2 text-[11px] font-semibold text-brand-text-secondary transition hover:border-brand-primary/40 hover:bg-brand-primary/[0.04] hover:text-brand-primary"
                                                    data-schedule-open="1"
                                                    data-shift-id=""
                                                    data-employee-public-id="{{ $row['employee_public_id'] }}"
                                                    data-employee-name="{{ $row['name'] }}"
                                                    data-day-label="{{ $day['date']->format('l, j M Y') }}"
                                                    data-scheduled-date="{{ $day['date_string'] }}"
                                                    data-entry-type="{{ EmployeeScheduleShift::TYPE_SHIFT }}"
                                                    data-shift-template-id="{{ $row['employee']->shift_id ?? '' }}"
                                                    data-work-location-id="{{ $row['employee']->work_location_id ?? '' }}"
                                                    data-notes=""
                                                    data-is-suggestion="0"
                                                >
                                                    + Add shift
                                                </button>
                                                @if (! $isDayOff)
                                                <button
                                                    type="button"
                                                    class="schedule-add-day-off flex items-center justify-center rounded-xl border border-dashed border-slate-300/80 px-2 py-2 text-[11px] font-semibold text-slate-500 transition hover:border-slate-400 hover:bg-slate-50 hover:text-slate-700"
                                                    data-schedule-open="1"
                                                    data-shift-id=""
                                                    data-employee-public-id="{{ $row['employee_public_id'] }}"
                                                    data-employee-name="{{ $row['name'] }}"
                                                    data-day-label="{{ $day['date']->format('l, j M Y') }}"
                                                    data-scheduled-date="{{ $day['date_string'] }}"
                                                    data-entry-type="{{ EmployeeScheduleShift::TYPE_TIME_OFF }}"
                                                    data-shift-template-id=""
                                                    data-work-location-id=""
                                                    data-notes=""
                                                    data-is-suggestion="0"
                                                >
                                                    + Day off
                                                </button>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-4 text-xs text-brand-text-secondary">
            Use <strong>+ Add shift</strong> or <strong>+ Day off</strong> in any cell. On a day off, use <strong>+ Add shift</strong> to schedule work instead. Click an existing card to view shift details, then edit or delete if needed.
            Dashed cards are suggestions from work assignments — click to save them to this week.
        </p>
    @endif

    @include('admin.partials.employee-weekly-schedule-modal', [
        'redirectQuery' => $redirectQuery,
        'workLocations' => $workLocations,
        'shiftTemplates' => $shiftTemplates,
    ])
@endsection
