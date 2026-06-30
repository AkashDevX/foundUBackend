@extends('layouts.admin')

@section('title', 'Employees — Work assignments')

@section('heading', 'Work assignments')

@section('subheading')
    {{ $company->name }} — assign department, work location, and shift for active employees.
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
    @endphp

    <p class="mb-6 rounded-xl border border-brand-border bg-white px-4 py-3 text-sm text-brand-text-secondary shadow-sm">
        Active employees only. 
    </p>

    <div class="space-y-4">
        @forelse ($employees as $employee)
            <section class="rounded-2xl border border-brand-border bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-brand-border pb-4">
                    <div>
                        <h2 class="text-base font-bold text-brand-text">{{ $employee->full_legal_name ?: $employee->email }}</h2>
                        <p class="mt-1 text-xs text-brand-text-secondary">{{ $employee->email }} · {{ $employee->employment_status ?: 'unknown' }}</p>
                        <p class="mt-1 font-mono text-[11px] text-brand-text-secondary">public_id {{ $employee->public_id }}</p>
                    </div>
                    <a href="{{ route('admin.registrations.show', ['companySlug' => $company->slug, 'publicId' => $employee->public_id]) }}" class="inline-flex items-center rounded-lg border border-brand-border px-3 py-2 text-xs font-semibold text-brand-primary hover:bg-brand-surface">
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
@endsection
