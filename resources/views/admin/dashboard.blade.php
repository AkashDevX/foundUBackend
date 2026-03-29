@extends('layouts.admin')

@section('title', 'Dashboard')

@section('heading', 'Attendance overview')

@section('subheading', 'Snapshot for today — connect your API when ready.')

@section('content')
    {{-- Hero --}}
    <div class="relative mb-8 overflow-hidden rounded-2xl border border-brand-border bg-brand-card shadow-sm">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-primary via-brand-primary-dark to-brand-primary-dark opacity-[0.97]"></div>
        <div class="absolute -right-16 -top-24 size-72 rounded-full bg-brand-primary-light/25 blur-3xl"></div>
        <div class="absolute -bottom-20 left-1/4 size-56 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative px-5 py-8 sm:px-8 sm:py-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-xl text-brand-white">
                    <p class="text-sm font-medium text-white/80">Sunday, March 29, 2026</p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">Good morning, Admin</h2>
                    <p class="mt-2 text-sm text-white/85 sm:text-base">
                        You’re viewing sample data. Wire these cards and tables to your attendance service when you’re ready.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="text-xs font-medium text-white/80 sm:text-sm">Date range</label>
                    <select class="rounded-lg border border-white/25 bg-white/10 px-3 py-2 text-sm text-brand-white backdrop-blur focus:border-white/40 focus:outline-none focus:ring-2 focus:ring-white/30">
                        <option>Today</option>
                        <option>This week</option>
                        <option>This month</option>
                    </select>
                    <button type="button" class="rounded-lg bg-brand-white px-4 py-2 text-sm font-semibold text-brand-primary shadow-sm transition hover:bg-white/95">
                        Export report
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl border border-brand-border bg-brand-card p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Present today</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-brand-text">142</p>
                    <p class="mt-1 text-sm text-brand-text-secondary">of 156 expected</p>
                </div>
                <span class="flex size-11 items-center justify-center rounded-xl bg-brand-primary/10 text-brand-primary">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
            </div>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-brand-input">
                <div class="h-full w-[91%] rounded-full bg-brand-primary"></div>
            </div>
        </article>
        <article class="rounded-xl border border-brand-border bg-brand-card p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Absent</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-brand-text">8</p>
                    <p class="mt-1 text-sm text-brand-text-secondary">Unexcused: 3</p>
                </div>
                <span class="flex size-11 items-center justify-center rounded-xl bg-brand-primary-dark/10 text-brand-primary-dark">
                    <svg class="size-6 text-brand-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
            </div>
            <p class="mt-4 text-xs text-brand-text-secondary">Follow up with <a href="#" class="font-medium text-brand-link hover:underline">absence list</a></p>
        </article>
        <article class="rounded-xl border border-brand-border bg-brand-card p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Late arrivals</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-brand-text">12</p>
                    <p class="mt-1 text-sm text-brand-text-secondary">After 9:15 cutoff</p>
                </div>
                <span class="flex size-11 items-center justify-center rounded-xl bg-brand-primary-light/15 text-brand-primary-light">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
            </div>
            <p class="mt-4 text-xs text-brand-text-secondary">Peak window: 8:55–9:10</p>
        </article>
        <article class="rounded-xl border border-brand-border bg-brand-card p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">On leave</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-brand-text">6</p>
                    <p class="mt-1 text-sm text-brand-text-secondary">Approved requests</p>
                </div>
                <span class="flex size-11 items-center justify-center rounded-xl bg-brand-input text-brand-icon">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </span>
            </div>
            <p class="mt-4 text-xs text-brand-text-secondary">PTO: 4 · Sick: 2</p>
        </article>
    </div>

    <div class="mb-8 grid gap-6 lg:grid-cols-5">
        {{-- Weekly chart (static bars) --}}
        <section class="lg:col-span-3 rounded-xl border border-brand-border bg-brand-card p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-brand-text">This week at a glance</h3>
                    <p class="mt-1 text-sm text-brand-text-secondary">Check-ins vs. expected headcount (sample)</p>
                </div>
                <a href="#" class="text-sm font-medium text-brand-link hover:underline">Open weekly report</a>
            </div>
            <div class="mt-8 flex h-48 items-end justify-between gap-2 border-b border-brand-border pb-1 pl-1 pr-1 sm:gap-3" role="img" aria-label="Bar chart: attendance by weekday">
                @php
                    $bars = [
                        ['label' => 'Mon', 'pct' => 72],
                        ['label' => 'Tue', 'pct' => 88],
                        ['label' => 'Wed', 'pct' => 65],
                        ['label' => 'Thu', 'pct' => 92],
                        ['label' => 'Fri', 'pct' => 78],
                    ];
                @endphp
                @foreach ($bars as $bar)
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <div class="flex w-full max-w-[3.5rem] flex-1 items-end justify-center">
                            <div
                                class="w-full rounded-t-md bg-gradient-to-t from-brand-primary-dark to-brand-primary-light transition-all"
                                style="height: {{ $bar['pct'] }}%"
                            ></div>
                        </div>
                        <span class="text-xs font-medium text-brand-text-secondary">{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap gap-4 text-xs text-brand-text-secondary">
                <span class="inline-flex items-center gap-2">
                    <span class="size-2 rounded-full bg-brand-primary"></span> Actual check-ins
                </span>
                <span class="inline-flex items-center gap-2">
                    <span class="size-2 rounded-full bg-brand-input"></span> Expected (not shown to scale)
                </span>
            </div>
        </section>

        {{-- Side panel --}}
        <section class="lg:col-span-2 flex flex-col gap-4">
            <div class="rounded-xl border border-brand-border bg-brand-card p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-semibold text-brand-text">Quick filters</h3>
                <p class="mt-1 text-sm text-brand-text-secondary">UI only — no backend wired.</p>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-brand-label" for="dept">Department</label>
                        <select id="dept" class="w-full rounded-lg border border-brand-border bg-brand-input px-3 py-2 text-sm text-brand-text focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                            <option>All departments</option>
                            <option>Operations</option>
                            <option>Engineering</option>
                            <option>People</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-brand-label" for="site">Site</label>
                        <select id="site" class="w-full rounded-lg border border-brand-border bg-brand-input px-3 py-2 text-sm text-brand-text focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                            <option>Main campus</option>
                            <option>Remote</option>
                        </select>
                    </div>
                    <button type="button" class="w-full rounded-lg bg-brand-primary py-2.5 text-sm font-semibold text-brand-white transition hover:bg-brand-primary-light">
                        Apply filters
                    </button>
                </div>
            </div>
            <div class="rounded-xl border border-brand-border bg-brand-card p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-semibold text-brand-text">By department</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-brand-text-secondary">Operations</span>
                        <span class="font-semibold tabular-nums text-brand-text">96%</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-brand-text-secondary">Engineering</span>
                        <span class="font-semibold tabular-nums text-brand-text">94%</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-brand-text-secondary">People</span>
                        <span class="font-semibold tabular-nums text-brand-text">100%</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-brand-text-secondary">Sales</span>
                        <span class="font-semibold tabular-nums text-brand-text">89%</span>
                    </li>
                </ul>
            </div>
        </section>
    </div>

    {{-- Table --}}
    <section class="overflow-hidden rounded-xl border border-brand-border bg-brand-card shadow-sm">
        <div class="flex flex-col gap-4 border-b border-brand-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <h3 class="text-base font-semibold text-brand-text">Recent check-ins</h3>
                <p class="mt-0.5 text-sm text-brand-text-secondary">Latest arrivals (mock rows)</p>
            </div>
            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg border border-brand-border px-3 py-2 text-sm font-medium text-brand-label transition hover:border-brand-primary/40 hover:text-brand-primary">
                <svg class="size-4 text-brand-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                Download CSV
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-brand-input/60 text-xs font-semibold uppercase tracking-wide text-brand-label">
                    <tr>
                        <th class="px-5 py-3 sm:px-6">Person</th>
                        <th class="px-5 py-3 sm:px-6">Department</th>
                        <th class="px-5 py-3 sm:px-6">Checked in</th>
                        <th class="px-5 py-3 sm:px-6">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border text-brand-text">
                    @php
                        $rows = [
                            ['name' => 'Jordan Lee', 'dept' => 'Engineering', 'time' => '8:42 AM', 'status' => 'On time', 'ok' => true],
                            ['name' => 'Sam Rivera', 'dept' => 'Operations', 'time' => '9:18 AM', 'status' => 'Late', 'ok' => false],
                            ['name' => 'Alex Morgan', 'dept' => 'People', 'time' => '8:55 AM', 'status' => 'On time', 'ok' => true],
                            ['name' => 'Casey Kim', 'dept' => 'Sales', 'time' => '—', 'status' => 'Absent', 'ok' => false],
                            ['name' => 'Riley Chen', 'dept' => 'Engineering', 'time' => '8:31 AM', 'status' => 'On time', 'ok' => true],
                        ];
                    @endphp
                    @foreach ($rows as $row)
                        <tr class="hover:bg-brand-input/30">
                            <td class="whitespace-nowrap px-5 py-3.5 font-medium sm:px-6">{{ $row['name'] }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-brand-text-secondary sm:px-6">{{ $row['dept'] }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 tabular-nums text-brand-text-secondary sm:px-6">{{ $row['time'] }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 sm:px-6">
                                @if ($row['ok'])
                                    <span class="inline-flex items-center rounded-full bg-brand-primary/10 px-2.5 py-0.5 text-xs font-semibold text-brand-primary-dark">On time</span>
                                @elseif ($row['status'] === 'Late')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900">Late</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-700">Absent</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-brand-border px-5 py-3 text-sm text-brand-text-secondary sm:px-6">
            <p>Showing 5 of 5 mock records</p>
            <a href="#" class="font-medium text-brand-link hover:underline">View full log</a>
        </div>
    </section>
@endsection
