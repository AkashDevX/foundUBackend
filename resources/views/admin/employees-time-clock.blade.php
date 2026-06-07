@extends('layouts.admin')

@section('title', 'Employees — Time clock')

@section('heading', 'Time clock records')

@section('subheading')
    {{ $company->name }} — mobile clock in/out history from the app (GPS geofence verified).
@endsection

@section('content')
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\Employee> $employees */
        /** @var \App\Models\Employee|null $selectedEmployee */
        use App\Models\TimeClockEntry;
        use App\Support\DisplayTimezone;
    @endphp

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200/90 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-950 shadow-sm ring-1 ring-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <p class="mb-6 rounded-xl border border-brand-border bg-white px-4 py-3 text-sm text-brand-text-secondary shadow-sm">
        Select an employee below to view their punch history.
    </p>

    <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
        <header class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4 sm:px-6">
            <h2 class="text-sm font-bold text-brand-text">Active employees</h2>
            <p class="mt-1 text-xs text-brand-text-secondary">Click a row to open clock in/out details.</p>
        </header>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-brand-border bg-brand-surface/80 text-xs font-semibold uppercase tracking-wide text-brand-label">
                    <tr>
                        <th class="px-5 py-3">Employee</th>
                        <th class="hidden px-5 py-3 md:table-cell">Assignment</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Punches</th>
                        <th class="hidden px-5 py-3 lg:table-cell">Last activity</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border/80">
                    @forelse ($employees as $employee)
                        @php
                            $lastPunch = $employee->timeClockEntries->first();
                            $isClockedIn = $lastPunch instanceof TimeClockEntry && $lastPunch->event_type === TimeClockEntry::EVENT_CLOCK_IN;
                            $isSelected = $selectedEmployee?->public_id === $employee->public_id;
                        @endphp
                        <tr class="{{ $isSelected ? 'bg-brand-primary/[0.06]' : 'hover:bg-brand-surface/50' }} transition">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-brand-text">{{ $employee->full_legal_name ?: $employee->email }}</p>
                                <p class="mt-0.5 text-xs text-brand-text-secondary">{{ $employee->email }}</p>
                            </td>
                            <td class="hidden px-5 py-4 text-xs text-brand-text-secondary md:table-cell">
                                {{ $employee->assignedDepartment?->name ?? '—' }} · {{ $employee->workLocation?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-4">
                                @if ($isClockedIn)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-200">Clocked in</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600 ring-1 ring-slate-200">Off shift</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 tabular-nums font-semibold text-brand-text">{{ $employee->timeClockEntries->count() }}</td>
                            <td class="hidden whitespace-nowrap px-5 py-4 text-xs tabular-nums text-brand-text-secondary lg:table-cell">
                                {{ DisplayTimezone::formatDateTime($lastPunch?->clocked_at) }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a
                                    href="{{ route('admin.employees.time-clock', ['employee' => $employee->public_id]) }}"
                                    class="inline-flex items-center rounded-lg border border-brand-border px-3 py-2 text-xs font-semibold {{ $isSelected ? 'bg-brand-primary text-white border-brand-primary' : 'text-brand-primary hover:bg-brand-surface' }}"
                                >
                                    {{ $isSelected ? 'Viewing' : 'View records' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-brand-text-secondary">No active employees.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($selectedEmployee)
        <section class="mt-8 overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4 sm:px-6">
                <div>
                    <h2 class="text-base font-bold text-brand-text">{{ $selectedEmployee->full_legal_name ?: $selectedEmployee->email }}</h2>
                    <p class="mt-1 text-xs text-brand-text-secondary">Clock in/out details · latest 100 punches</p>
                </div>
                <a href="{{ route('admin.employees.time-clock') }}" class="inline-flex items-center rounded-lg border border-brand-border px-3 py-2 text-xs font-semibold text-brand-text-secondary hover:bg-brand-surface">
                    Clear selection
                </a>
            </header>
            <div class="p-5 sm:p-6">
                @include('admin.partials.employee-time-clock-records', [
                    'employee' => $selectedEmployee,
                    'eventFilter' => $eventFilter ?? 'all',
                ])
            </div>
        </section>
    @elseif ($employees->isNotEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-brand-border bg-brand-surface/40 px-6 py-10 text-center">
            <p class="text-sm font-semibold text-brand-text">Select an employee</p>
            <p class="mt-2 text-xs text-brand-text-secondary">Choose <strong>View records</strong> on any row above to see their clock in/out history.</p>
        </div>
    @endif
@endsection
