@extends('layouts.admin')

@section('title', 'Dashboard')

@section('heading', 'Attendance overview')

@section('subheading', 'Today’s snapshot — sample data until your API is connected.')

@section('content')
    @php
        $kpis = [
            [
                'label' => 'Present today',
                'value' => '142',
                'hint' => 'of 156 expected',
                'bar' => 91,
                'icon' => 'check',
            ],
            [
                'label' => 'Absent',
                'value' => '8',
                'hint' => '3 unexcused · 6 on leave',
                'bar' => null,
                'icon' => 'x',
            ],
            [
                'label' => 'Late arrivals',
                'value' => '12',
                'hint' => 'after 9:15',
                'bar' => null,
                'icon' => 'clock',
            ],
        ];
        $bars = [
            ['label' => 'Mon', 'pct' => 72],
            ['label' => 'Tue', 'pct' => 88],
            ['label' => 'Wed', 'pct' => 65],
            ['label' => 'Thu', 'pct' => 92],
            ['label' => 'Fri', 'pct' => 78],
        ];
        $departments = [
            ['name' => 'Operations', 'pct' => 96],
            ['name' => 'Engineering', 'pct' => 94],
            ['name' => 'People', 'pct' => 100],
            ['name' => 'Sales', 'pct' => 89],
        ];
        $rows = [
            ['name' => 'Jordan Lee', 'dept' => 'Engineering', 'time' => '8:42 AM', 'status' => 'On time', 'ok' => true],
            ['name' => 'Sam Rivera', 'dept' => 'Operations', 'time' => '9:18 AM', 'status' => 'Late', 'ok' => false],
            ['name' => 'Alex Morgan', 'dept' => 'People', 'time' => '8:55 AM', 'status' => 'On time', 'ok' => true],
            ['name' => 'Casey Kim', 'dept' => 'Sales', 'time' => '—', 'status' => 'Absent', 'ok' => false],
            ['name' => 'Riley Chen', 'dept' => 'Engineering', 'time' => '8:31 AM', 'status' => 'On time', 'ok' => true],
        ];
    @endphp

    {{-- Welcome --}}
    <div class="relative mb-10 overflow-hidden rounded-2xl border border-brand-border bg-brand-card shadow-sm">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-primary via-brand-primary-dark to-brand-primary-dark opacity-[0.96]"></div>
        <div class="absolute -right-20 -top-28 size-80 rounded-full bg-brand-primary-light/20 blur-3xl"></div>
        <div class="relative px-6 py-8 sm:px-8 sm:py-9">
            <p class="text-sm font-medium text-white/80">{{ now()->format('l, F j, Y') }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-brand-white sm:text-3xl">Good morning, Admin</h2>
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/85">
                You’re viewing sample data. Wire these numbers to your attendance service when you’re ready.
            </p>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="mb-10 grid gap-5 sm:grid-cols-3">
        @foreach ($kpis as $kpi)
            <article class="rounded-xl border border-brand-border bg-brand-card p-6 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">{{ $kpi['label'] }}</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-brand-text">{{ $kpi['value'] }}</p>
                        <p class="mt-1 text-sm text-brand-text-secondary">{{ $kpi['hint'] }}</p>
                    </div>
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-primary/10 text-brand-primary">
                        @if ($kpi['icon'] === 'check')
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        @elseif ($kpi['icon'] === 'x')
                            <svg class="size-6 text-brand-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        @else
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        @endif
                    </span>
                </div>
                @if ($kpi['bar'] !== null)
                    <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-brand-input">
                        <div class="h-full rounded-full bg-brand-primary" style="width: {{ $kpi['bar'] }}%"></div>
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    {{-- Week + departments (single panel) --}}
    <section class="mb-10 rounded-xl border border-brand-border bg-brand-card p-6 shadow-sm sm:p-8">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-brand-text">This week</h3>
                <p class="text-sm text-brand-text-secondary">Check-ins by weekday (sample)</p>
            </div>
        </div>
        <div
            class="mt-8 flex h-52 items-end justify-between gap-2 border-b border-brand-border pb-2 sm:gap-4"
            role="img"
            aria-label="Bar chart: attendance by weekday"
        >
            @foreach ($bars as $bar)
                <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                    <div class="flex h-full w-full max-w-[4rem] flex-1 items-end justify-center">
                        <div
                            class="w-full rounded-t-lg bg-gradient-to-t from-brand-primary-dark to-brand-primary-light"
                            style="height: {{ $bar['pct'] }}%"
                        ></div>
                    </div>
                    <span class="text-xs font-medium text-brand-text-secondary">{{ $bar['label'] }}</span>
                </div>
            @endforeach
        </div>
        <div class="mt-8 border-t border-brand-border pt-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Attendance by department</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($departments as $d)
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-brand-input/50 px-4 py-3">
                        <span class="truncate text-sm text-brand-text-secondary">{{ $d['name'] }}</span>
                        <span class="shrink-0 text-sm font-semibold tabular-nums text-brand-text">{{ $d['pct'] }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Recent activity --}}
    <section class="overflow-hidden rounded-xl border border-brand-border bg-brand-card shadow-sm">
        <div class="border-b border-brand-border px-6 py-5 sm:px-8">
            <h3 class="text-base font-semibold text-brand-text">Recent check-ins</h3>
            <p class="mt-0.5 text-sm text-brand-text-secondary">Latest arrivals (mock)</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-brand-input/50 text-xs font-semibold uppercase tracking-wide text-brand-label">
                    <tr>
                        <th class="px-6 py-3.5">Person</th>
                        <th class="px-6 py-3.5">Department</th>
                        <th class="px-6 py-3.5">Checked in</th>
                        <th class="px-6 py-3.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border text-brand-text">
                    @foreach ($rows as $row)
                        <tr class="transition hover:bg-brand-input/25">
                            <td class="whitespace-nowrap px-6 py-3.5 font-medium">{{ $row['name'] }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-brand-text-secondary">{{ $row['dept'] }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 tabular-nums text-brand-text-secondary">{{ $row['time'] }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5">
                                @if ($row['ok'])
                                    <span class="inline-flex rounded-full bg-brand-primary/10 px-2.5 py-0.5 text-xs font-semibold text-brand-primary-dark">On time</span>
                                @elseif ($row['status'] === 'Late')
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900">Late</span>
                                @else
                                    <span class="inline-flex rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-700">Absent</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-border px-6 py-3.5 text-sm text-brand-text-secondary sm:px-8">
            Showing {{ count($rows) }} mock records
        </div>
    </section>
@endsection
