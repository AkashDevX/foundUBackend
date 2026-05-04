@extends('layouts.admin')

@section('title', 'Employees')

@section('heading', 'Employees')

@section('subheading')
    {{ $company->name }} — assign department, work location, and shift without opening each registration.
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

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200/90 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-950 shadow-sm ring-1 ring-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-red-200/90 bg-red-50 px-5 py-4 text-sm text-red-950 shadow-sm ring-1 ring-red-100">
            <p class="font-semibold">Could not save assignment</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 rounded-xl border border-brand-border bg-white px-4 py-3 text-sm font-medium text-brand-text shadow-sm">
        Active employees only. Pending employees cannot be assigned.
    </div>

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

                <form method="post" action="{{ route('admin.employees.assignment.update', ['publicId' => $employee->public_id]) }}" class="grid gap-4 lg:grid-cols-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Department</label>
                        <select name="department_id" class="mt-1.5 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25">
                            <option value="">— None —</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}" @selected((string) $employee->department_id === (string) $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Work location</label>
                        <select name="work_location_id" class="mt-1.5 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25">
                            <option value="">— None —</option>
                            @foreach ($workLocations as $loc)
                                <option value="{{ $loc->id }}" @selected((string) $employee->work_location_id === (string) $loc->id)>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Shift</label>
                        <select name="shift_id" class="mt-1.5 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25">
                            <option value="">— None —</option>
                            @foreach ($shifts as $sh)
                                <option value="{{ $sh->id }}" @selected((string) $employee->shift_id === (string) $sh->id)>{{ $sh->name }} ({{ $shiftTimes($sh) }}, {{ $shiftDays($sh) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Effective from</label>
                        @php
                            $effFrom = \App\Support\RegistrationDisplay::toHtmlDateInput(old('assignment_effective_from', $employee->assignment_effective_from));
                        @endphp
                        <input type="date" name="assignment_effective_from" value="{{ $effFrom }}" class="mt-1.5 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm [color-scheme:light] focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" />
                    </div>

                    <div class="lg:col-span-4">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Assignment notes</label>
                        <textarea name="assignment_notes" rows="2" maxlength="5000" class="mt-1.5 w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" placeholder="Parking bay, supervisor, uniform, etc.">{{ $employee->assignment_notes }}</textarea>
                    </div>

                    <div class="lg:col-span-4 flex flex-wrap items-center justify-between gap-3 border-t border-brand-border pt-3">
                        <p class="text-xs text-brand-text-secondary">
                            Current: {{ $employee->assignedDepartment?->name ?? 'No department' }} · {{ $employee->workLocation?->name ?? 'No location' }} · {{ $employee->assignedShift?->name ?? 'No shift' }}
                        </p>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                            Save assignment
                        </button>
                    </div>
                </form>
            </section>
        @empty
            <div class="rounded-2xl border border-brand-border bg-white px-6 py-10 text-center text-sm text-brand-text-secondary shadow-sm">
                No employees found for this filter.
            </div>
        @endforelse
    </div>
@endsection
