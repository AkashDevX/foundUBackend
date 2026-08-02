@extends('layouts.admin')

@section('title', 'Employees — Timesheets')

@section('heading', 'Time clock records')

@section('subheading')
    {{ $company->name }}
@endsection

@push('scripts')
    @vite(['resources/js/admin-time-clock-timesheet.js', 'resources/js/admin-time-clock-punch-map.js', 'resources/js/admin-time-clock-row-actions.js'])
@endpush

@section('content')
    @php
        /** @var list<array<string, mixed>> $weekIndex */
        /** @var list<array<string, mixed>> $timesheetGroups */
        /** @var array<string, string> $filters */
        /** @var array<string, string|null> $redirectQuery */
        /** @var array<string, string|null> $filterParams */
        /** @var callable(string): string $weekDetailsUrl */
        /** @var string $listUrl */
        /** @var string $clearFiltersUrl */

        $hasActiveFilters = $filters['department_id'] !== '' || $filters['work_location_id'] !== '' || $filters['employee'] !== '' || ($timesheetStatusFilter ?? 'all') !== 'all';
        $modalPendingDays = collect($timesheetGroups)->sum(fn (array $group): int => (int) ($group['pending_days'] ?? 0));
        $modalShiftRows = collect($timesheetGroups)->sum(fn (array $group): int => (int) ($group['shift_rows'] ?? 0));
        $th = 'whitespace-nowrap px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-brand-label';
        $td = 'whitespace-nowrap px-3 py-2.5 text-xs tabular-nums text-brand-text';
        $tdText = 'whitespace-nowrap px-3 py-2.5 text-xs text-brand-text';
    @endphp

    <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
        <div class="flex flex-col gap-4 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.14em] text-brand-primary">Timesheets</p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface"
                data-timesheet-filter-toggle
                aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}"
            >
                <svg class="size-4 text-brand-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                </svg>
                Filter
                @if ($hasActiveFilters)
                    <span class="inline-flex size-5 items-center justify-center rounded-full bg-brand-primary text-[10px] font-bold text-white">!</span>
                @endif
            </button>
        </div>

        <div class="{{ $hasActiveFilters ? '' : 'hidden' }} border-b border-brand-border bg-brand-surface/40 px-4 py-4 sm:px-6" data-timesheet-filter-panel>
            <form method="get" action="{{ route('admin.employees.time-clock') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @if ($selectedWeek)
                    <input type="hidden" name="week" value="{{ $selectedWeek->toDateString() }}">
                @endif

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Status</span>
                    <select name="timesheet_status" class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                        @foreach (['all' => 'All statuses', 'pending' => 'Pending approval', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
                            <option value="{{ $key }}" @selected(($timesheetStatusFilter ?? 'all') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

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

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Employee</span>
                    <select name="employee" class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                        <option value="">All employees</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->public_id }}" @selected($filters['employee'] === $employee->public_id)>
                                {{ $employee->full_legal_name ?: $employee->email }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-primary-dark">
                        Apply
                    </button>
                    @if ($hasActiveFilters)
                        <a href="{{ $clearFiltersUrl }}" class="inline-flex items-center justify-center rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text-secondary transition hover:bg-brand-surface">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-brand-border bg-brand-surface/80 text-xs font-semibold uppercase tracking-wide text-brand-label">
                    <tr>
                        <th class="px-4 py-3 sm:px-6">Pay week</th>
                        <th class="hidden px-4 py-3 text-center sm:table-cell sm:px-6">Employees</th>
                        <th class="hidden px-4 py-3 text-center md:table-cell sm:px-6">Shift rows</th>
                        <th class="px-4 py-3 text-center sm:px-6">Pending days</th>
                        <th class="hidden px-4 py-3 text-center lg:table-cell sm:px-6">Approved days</th>
                        <th class="px-4 py-3 text-right sm:px-6"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border/80">
                    @foreach ($weekIndex as $week)
                        @php
                            $stats = $week['stats'];
                            $isSelected = $selectedWeek?->toDateString() === $week['week_start'];
                        @endphp
                        <tr class="transition {{ $isSelected ? 'bg-brand-primary/[0.05]' : 'hover:bg-brand-surface/40' }}">
                            <td class="px-4 py-4 sm:px-6">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-brand-text">{{ $week['week_label_long'] }}</p>
                                    @if ($week['is_current'])
                                        <span class="inline-flex items-center rounded-full bg-brand-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand-primary ring-1 ring-brand-primary/20">Current week</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-brand-text-secondary">{{ $week['week_label'] }}</p>
                            </td>
                            <td class="hidden px-4 py-4 text-center tabular-nums font-semibold text-brand-text sm:table-cell sm:px-6">{{ $stats['employees'] }}</td>
                            <td class="hidden px-4 py-4 text-center tabular-nums text-brand-text md:table-cell sm:px-6">{{ $stats['rows'] }}</td>
                            <td class="px-4 py-4 text-center sm:px-6">
                                @if (($stats['pending'] ?? 0) > 0)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 ring-1 ring-amber-200">
                                        {{ $stats['pending'] }}
                                    </span>
                                @else
                                    <span class="text-xs text-brand-text-secondary">0</span>
                                @endif
                            </td>
                            <td class="hidden px-4 py-4 text-center tabular-nums text-emerald-700 lg:table-cell sm:px-6">{{ $stats['approved'] }}</td>
                            <td class="px-4 py-4 text-right sm:px-6">
                                <a
                                    href="{{ $weekDetailsUrl($week['week_start']) }}"
                                    class="inline-flex items-center gap-2 rounded-xl {{ $isSelected ? 'bg-brand-primary-dark' : 'bg-brand-primary' }} px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-primary-dark"
                                >
                                    Details
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($selectedWeek)
        <div
            id="timesheet-fullscreen-modal"
            class="fixed inset-0 z-[70] flex bg-brand-primary-dark/50 p-3 sm:p-4 lg:p-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="timesheet-modal-title"
            data-close-url="{{ $listUrl }}"
        >
            <div
                class="flex min-h-0 w-full flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-2xl ring-1 ring-black/[0.06]"
                data-timesheet-modal-panel
            >
                <header class="shrink-0 border-b border-brand-border border-l-4 border-l-brand-primary bg-gradient-to-br from-brand-surface via-white to-white">
                    <div class="flex flex-wrap items-start justify-between gap-4 px-4 py-4 sm:px-6">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold uppercase tracking-[0.14em] text-brand-primary">Timesheets</p>
                            <h2 id="timesheet-modal-title" class="mt-1 truncate text-xl font-bold text-brand-text sm:text-2xl">{{ $selectedWeekLabel }}</h2>
                            <p class="mt-1 text-sm text-brand-text-secondary">
                                {{ count($timesheetGroups) }} employee{{ count($timesheetGroups) === 1 ? '' : 's' }}
                                · {{ $modalShiftRows }} shift row{{ $modalShiftRows === 1 ? '' : 's' }}
                                · Hold Shift + scroll for all columns
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($hasActiveFilters)
                                <a
                                    href="{{ $clearFiltersUrl }}"
                                    class="inline-flex items-center gap-2 rounded-xl border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-text-secondary shadow-sm transition hover:bg-brand-surface"
                                >
                                    Clear filters
                                </a>
                            @endif
                            <a
                                href="{{ $listUrl }}"
                                class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface"
                                data-timesheet-modal-close
                            >
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Close
                            </a>
                        </div>
                    </div>

                    <div class="grid gap-px border-t border-brand-border/80 bg-brand-border/70 sm:grid-cols-3">
                        <div class="bg-white px-4 py-3 sm:px-6">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Employees</p>
                            <p class="mt-1 text-lg font-bold tabular-nums text-brand-text">{{ count($timesheetGroups) }}</p>
                        </div>
                        <div class="bg-white px-4 py-3 sm:px-6">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Shift rows</p>
                            <p class="mt-1 text-lg font-bold tabular-nums text-brand-text">{{ $modalShiftRows }}</p>
                        </div>
                        <div class="bg-white px-4 py-3 sm:px-6">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Pending days</p>
                            <p class="mt-1 text-lg font-bold tabular-nums {{ $modalPendingDays > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $modalPendingDays }}</p>
                        </div>
                    </div>
                </header>

                <div class="min-h-0 flex-1 overflow-auto bg-brand-surface/40 p-4 sm:p-6" data-timesheet-modal-scroll>
                    @if ($timesheetGroups === [])
                        <div class="rounded-2xl border border-dashed border-brand-border bg-white px-6 py-14 text-center shadow-sm">
                            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-brand-primary/10 text-brand-primary ring-1 ring-brand-primary/15">
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="mt-4 text-sm font-semibold text-brand-text">No timesheet activity this week</p>
                            @if ($hasActiveFilters)
                                <a href="{{ $clearFiltersUrl }}" class="mt-4 inline-flex items-center rounded-xl border border-brand-border bg-white px-4 py-2 text-xs font-semibold text-brand-text shadow-sm transition hover:bg-brand-surface">
                                    Clear filters
                                </a>
                            @endif
                        </div>
                    @else
                        <div class="space-y-5">
                            @foreach ($timesheetGroups as $group)
                                @php
                                    $palette = $group['employment_type_palette'];
                                    $summary = $group['summary'];
                                @endphp
                                <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
                                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border border-l-4 border-l-brand-primary-light bg-gradient-to-r from-brand-surface/90 via-white to-white px-4 py-3.5 sm:px-5">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-xs font-bold text-brand-primary ring-1 ring-brand-primary/15">
                                                {{ $group['initials'] }}
                                            </span>
                                            <div class="min-w-0">
                                                <h3 class="truncate text-sm font-bold text-brand-text">{{ $group['name'] }}</h3>
                                                <p class="mt-0.5 text-xs text-brand-text-secondary">
                                                    {{ $group['shift_rows'] }} shift row{{ $group['shift_rows'] === 1 ? '' : 's' }}
                                                    · {{ $summary['worked_duration_hours'] }} worked
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $palette['bg'] }} {{ $palette['border'] }} {{ $palette['text'] }}">
                                                {{ $group['employment_type'] }}
                                            </span>
                                            @if (($group['pending_days'] ?? 0) > 0)
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 ring-1 ring-amber-200">
                                                    {{ $group['pending_days'] }} pending
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-200">
                                                    Signed off
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @include('admin.partials.employee-time-clock-detail-table', [
                                        'group' => $group,
                                        'redirectQuery' => $redirectQuery,
                                        'th' => $th,
                                        'td' => $td,
                                        'tdText' => $tdText,
                                        'embedded' => true,
                                    ])
                                </section>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @include('admin.partials.time-clock-punch-map-modal', [
        'mapDefaultLat' => config('workforce.default_map_lat'),
        'mapDefaultLng' => config('workforce.default_map_lng'),
        'mapDefaultZoom' => config('workforce.default_map_zoom'),
    ])

    @if ($selectedWeek)
        @include('admin.partials.time-clock-row-modal', [
            'redirectQuery' => $redirectQuery,
        ])
    @endif
@endsection
