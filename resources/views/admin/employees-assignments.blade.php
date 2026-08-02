@extends('layouts.admin')

@section('title', 'Employees — Work assignments')

@section('heading', 'Work assignments')

@section('subheading')
    {{ $company->name }}
@endsection


@section('content')
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\Employee> $employees */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Department> $departments */
        /** @var \Illuminate\Support\Collection<int, \App\Models\WorkLocation> $workLocations */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Shift> $shifts */
        $shiftTimes = static function (?\App\Models\Shift $s): string {
            if ($s === null) {
                return '—';
            }
            $st = $s->start_time instanceof \Carbon\CarbonInterface ? $s->start_time->format('g:i A') : '—';
            $en = $s->end_time instanceof \Carbon\CarbonInterface ? $s->end_time->format('g:i A') : '—';

            return $st.'-'.$en;
        };
        $shiftDays = static function (?\App\Models\Shift $s): string {
            if ($s === null || ! is_array($s->shift_days) || $s->shift_days === []) {
                return 'All days';
            }
            $map = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];

            return collect($s->shift_days)->map(fn ($d) => $map[$d] ?? null)->filter()->join(', ');
        };
        $searchIn = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
    @endphp

    <div data-assignment-search>
        @if ($employees->isNotEmpty())
            <div class="mb-4 flex flex-col gap-3 rounded-2xl border border-brand-border bg-white px-4 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <h2 class="text-sm font-bold text-brand-text">Employees ({{ $employees->count() }})</h2>
                <div class="relative w-full sm:max-w-xs">
                    <input
                        type="text"
                        data-assignment-filter
                        class="{{ $searchIn }} pe-9"
                        placeholder="Search by name, email, or ID…"
                        autocomplete="off"
                        aria-label="Search employees"
                    >
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-brand-text-secondary/70">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                    </span>
                </div>
            </div>
        @endif

        <div class="space-y-4" data-assignment-list>
            @forelse ($employees as $employee)
                @php
                    $assignmentSearch = strtolower(trim(($employee->full_legal_name ?? '').' '.($employee->email ?? '').' '.($employee->public_id ?? '').' '.($employee->employment_status ?? '')));
                @endphp
                <section class="rounded-2xl border border-brand-border bg-white p-5 shadow-sm" data-employee-item data-search="{{ $assignmentSearch }}">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-brand-border pb-4">
                        <div>
                            <h2 class="text-base font-bold text-brand-text">{{ $employee->full_legal_name ?: $employee->email }}</h2>
                            <p class="mt-1 text-xs text-brand-text-secondary">{{ $employee->email }} · {{ $employee->employment_status ?: 'unknown' }}</p>
                            <p class="mt-1 font-mono text-[11px] text-brand-text-secondary">public_id {{ $employee->public_id }}</p>
                        </div>
                        <a href="{{ route('admin.employees.assignments', ['profile' => $employee->public_id]) }}#employee-profile-modal" class="inline-flex items-center rounded-lg border border-brand-border px-3 py-2 text-xs font-semibold text-brand-primary hover:bg-brand-surface">
                            Open full profile
                        </a>
                    </div>

                    @include('admin.partials.employee-assignment-form', [
                        'employee' => $employee,
                        'departments' => $departments,
                        'workLocations' => $workLocations,
                        'shifts' => $shifts,
                        'shiftTimes' => $shiftTimes,
                        'shiftDays' => $shiftDays,
                    ])
                </section>
            @empty
                <div class="rounded-2xl border border-brand-border bg-white px-6 py-10 text-center text-sm text-brand-text-secondary shadow-sm">
                    No employees found for this filter.
                </div>
            @endforelse
        </div>

        <div data-assignment-empty class="hidden rounded-2xl border border-dashed border-brand-border bg-brand-surface/40 px-6 py-10 text-center text-sm text-brand-text-secondary">
            No employees match your search.
        </div>
    </div>

    @if (($selectedProfileEmployee ?? null) !== null)
        <div
            id="employee-profile-modal"
            class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto p-3 sm:p-6"
            data-employee-profile-modal
            role="dialog"
            aria-modal="true"
            aria-label="Employee profile"
        >
            <a href="{{ route('admin.employees.assignments') }}" class="fixed inset-0 bg-brand-primary-dark/60" aria-label="Close profile" data-employee-profile-close></a>
            <div class="relative z-10 my-2 flex max-h-[calc(100vh-1rem)] w-full max-w-7xl flex-col overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-2xl ring-1 ring-black/[0.06] sm:my-3 sm:max-h-[calc(100vh-1.5rem)]">
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-brand-border bg-white px-4 py-4 sm:px-6">
                    <div class="flex min-w-0 flex-wrap items-center gap-2 sm:gap-3">
                        <h2 class="truncate text-lg font-bold text-brand-text sm:text-xl">{{ $selectedProfileEmployee->full_legal_name ?: $selectedProfileEmployee->email }}</h2>
                        @if (($selectedProfileEmployee->employment_status ?? '') !== '')
                            <span class="shrink-0 rounded-full bg-brand-surface px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-text-secondary">{{ $selectedProfileEmployee->employment_status }}</span>
                        @endif
                        @if ($selectedProfileEmployee->created_at)
                            <span class="text-xs text-brand-text-secondary">Registered {{ \App\Support\DisplayTimezone::formatDateTime($selectedProfileEmployee->created_at) }}</span>
                        @endif
                    </div>
                    <a
                        href="{{ route('admin.employees.assignments') }}"
                        class="shrink-0 rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm transition hover:bg-brand-surface hover:text-brand-text"
                        aria-label="Close"
                        data-employee-profile-close
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </a>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 pt-5 pb-0 sm:px-6">
                    @include('admin.partials.employee-profile-detail', [
                        'employee' => $selectedProfileEmployee,
                        'showApprovalActions' => false,
                    ])
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            (function () {
                function normalize(value) {
                    return String(value || '')
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .trim();
                }

                document.querySelectorAll('[data-assignment-search]').forEach(function (root) {
                    var input = root.querySelector('[data-assignment-filter]');
                    var list = root.querySelector('[data-assignment-list]');
                    var empty = root.querySelector('[data-assignment-empty]');
                    if (!input || !list) {
                        return;
                    }

                    var items = Array.prototype.slice.call(list.querySelectorAll('[data-employee-item]'));

                    function applyFilter() {
                        var query = normalize(input.value);
                        var shown = 0;
                        items.forEach(function (item) {
                            var haystack = normalize(item.getAttribute('data-search'));
                            var match = query === '' || haystack.indexOf(query) !== -1;
                            item.classList.toggle('hidden', !match);
                            if (match) {
                                shown += 1;
                            }
                        });
                        if (empty) {
                            empty.classList.toggle('hidden', shown !== 0);
                        }
                    }

                    input.addEventListener('input', applyFilter);
                });

                var modal = document.querySelector('[data-employee-profile-modal]');
                if (modal) {
                    var closeEl = modal.querySelector('[data-employee-profile-close]');
                    var closeUrl = closeEl ? closeEl.getAttribute('href') : null;
                    document.body.classList.add('overflow-hidden');
                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && closeUrl) {
                            window.location.href = closeUrl;
                        }
                    });
                }
            })();
        </script>
    @endpush
@endsection
