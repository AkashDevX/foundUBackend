@extends('layouts.admin')

@section('title', 'Registration requests')

@section('heading', 'Registration requests')

@section('subheading')
    {{ $currentCompany->name }} — employee applications from the mobile app (your organization only).
@endsection

@section('content')
    @php
        /** @var \App\Models\Company $currentCompany */
        /** @var array<int, array{employee: \App\Models\Employee}> $rows */
        /** @var string|null $tenantError */
        /** @var string|null $statusFilter */
        /** @var int $statsTotal */
        /** @var int $statsPending */
        /** @var int $statsActive */
        /** @var int $statsDeclined */
    @endphp

    @if ($tenantError !== null)
        <div class="mb-8 rounded-2xl border border-amber-200/90 bg-gradient-to-br from-amber-50 to-amber-100/80 px-5 py-4 text-sm text-amber-950 shadow-sm ring-1 ring-amber-200/60">
            <p class="font-semibold">Could not reach this organization’s database</p>
            <p class="mt-2 font-mono text-xs leading-relaxed text-amber-900/90">{{ $tenantError }}</p>
        </div>
    @endif

    {{-- Hero --}}
    <div class="relative mb-10 overflow-hidden rounded-3xl border border-brand-primary-dark/15 shadow-lg shadow-brand-primary/10 ring-1 ring-black/[0.04]">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-primary via-brand-primary-dark to-[#001428] opacity-[0.97]"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image: url(&quot;data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E&quot;);"></div>
        <div class="pointer-events-none absolute -right-24 top-1/2 size-[420px] -translate-y-1/2 rounded-full bg-brand-primary-light/25 blur-3xl"></div>
        <div class="relative px-6 py-8 sm:px-10 sm:py-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-xl">
                    <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white/90 ring-1 ring-white/20">
                        <svg class="size-3.5 text-emerald-300" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        {{ now()->format('l · F j, Y') }}
                    </div>
                    <h2 class="mt-4 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $currentCompany->name }}</h2>
                    <p class="mt-2 font-mono text-xs text-white/65">{{ $currentCompany->slug }}</p>
                    <p class="mt-5 text-sm leading-relaxed text-white/85">
                        Review registrations submitted through your workforce mobile app. Numbers below reflect your full tenant database.
                    </p>
                </div>
                <div class="grid w-full max-w-2xl shrink-0 grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4 lg:max-w-3xl lg:gap-5">
                    <div class="rounded-2xl bg-white/12 px-3 py-4 text-center ring-1 ring-white/20 backdrop-blur-sm sm:px-4">
                        <p class="text-2xl font-bold tabular-nums text-white sm:text-3xl">{{ $statsTotal }}</p>
                        <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-white/75">Total</p>
                    </div>
                    <div class="rounded-2xl bg-amber-400/15 px-3 py-4 text-center ring-1 ring-amber-300/35 backdrop-blur-sm sm:px-4">
                        <p class="text-2xl font-bold tabular-nums text-amber-100 sm:text-3xl">{{ $statsPending }}</p>
                        <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-amber-100/90">Pending</p>
                    </div>
                    <div class="rounded-2xl bg-emerald-400/10 px-3 py-4 text-center ring-1 ring-emerald-400/25 backdrop-blur-sm sm:px-4">
                        <p class="text-2xl font-bold tabular-nums text-emerald-100 sm:text-3xl">{{ $statsActive }}</p>
                        <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-100/90">Active</p>
                    </div>
                    <div class="rounded-2xl bg-red-400/10 px-3 py-4 text-center ring-1 ring-red-300/30 backdrop-blur-sm sm:px-4">
                        <p class="text-2xl font-bold tabular-nums text-red-100 sm:text-3xl">{{ $statsDeclined }}</p>
                        <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-red-100/90">Declined</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="mb-8 flex flex-col gap-4 rounded-2xl border border-brand-border bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div>
            <p class="text-sm font-semibold text-brand-text">Filter applications</p>
            <p class="mt-0.5 text-sm text-brand-text-secondary">Choose a status or view everyone in the table.</p>
        </div>
        <div class="inline-flex flex-wrap rounded-xl bg-brand-surface p-1 ring-1 ring-brand-border">
            <a
                href="{{ route('admin.dashboard') }}"
                class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ $statusFilter === null || $statusFilter === '' ? 'bg-white text-brand-primary shadow-sm ring-1 ring-brand-border' : 'text-brand-text-secondary hover:text-brand-text' }}"
            >All</a>
            <a
                href="{{ route('admin.dashboard', ['status' => 'pending']) }}"
                class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ $statusFilter === 'pending' ? 'bg-white text-brand-primary shadow-sm ring-1 ring-brand-border' : 'text-brand-text-secondary hover:text-brand-text' }}"
            >Pending</a>
            <a
                href="{{ route('admin.dashboard', ['status' => 'active']) }}"
                class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ $statusFilter === 'active' ? 'bg-white text-brand-primary shadow-sm ring-1 ring-brand-border' : 'text-brand-text-secondary hover:text-brand-text' }}"
            >Active</a>
            <a
                href="{{ route('admin.dashboard', ['status' => 'declined']) }}"
                class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ $statusFilter === 'declined' ? 'bg-white text-brand-primary shadow-sm ring-1 ring-brand-border' : 'text-brand-text-secondary hover:text-brand-text' }}"
            >Declined</a>
        </div>
    </div>

    {{-- Table card --}}
    <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-md shadow-black/[0.04] ring-1 ring-black/[0.03]">
        <div class="flex flex-col gap-1 border-b border-brand-border bg-gradient-to-r from-brand-surface to-white px-6 py-6 sm:flex-row sm:items-end sm:justify-between sm:px-8">
            <div>
                <h3 class="text-lg font-bold text-brand-text">Submitted applications</h3>
                <p class="mt-1 text-sm text-brand-text-secondary">Sorted newest first · Select a row to open full registration detail.</p>
            </div>
            @if ($rows !== [])
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">{{ count($rows) }} shown</p>
            @endif
        </div>

        @if ($rows === [])
            <div class="flex flex-col items-center px-8 py-20 text-center">
                <div class="flex size-16 items-center justify-center rounded-2xl bg-brand-surface ring-1 ring-brand-border">
                    <svg class="size-8 text-brand-primary-light/90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <p class="mt-6 text-base font-semibold text-brand-text">No applications yet</p>
                <p class="mt-2 max-w-md text-sm leading-relaxed text-brand-text-secondary">
                    {{ $statusFilter ? 'No employees match this filter right now.' : 'When staff complete registration in the app for '.$currentCompany->name.', they will appear in this list.' }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-brand-border bg-brand-surface/80 text-xs font-bold uppercase tracking-wider text-brand-label">
                            <th class="px-6 py-4 sm:px-8">Applicant</th>
                            <th class="px-6 py-4 sm:px-8">Email</th>
                            <th class="hidden px-6 py-4 md:table-cell sm:px-8">Phone</th>
                            <th class="px-6 py-4 sm:px-8">Status</th>
                            <th class="px-6 py-4 sm:px-8">Submitted</th>
                            <th class="px-6 py-4 text-right sm:px-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        @foreach ($rows as $row)
                            @php
                                $employee = $row['employee'];
                                $st = $employee->employment_status ?? '';
                            @endphp
                            <tr class="bg-white transition hover:bg-brand-surface/70">
                                <td class="max-w-[220px] px-6 py-4 sm:px-8">
                                    <span class="font-semibold text-brand-text">{{ $employee->full_legal_name ?: $employee->first_name.' '.$employee->last_name }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-brand-text-secondary sm:px-8">{{ $employee->email }}</td>
                                <td class="hidden whitespace-nowrap px-6 py-4 text-brand-text-secondary md:table-cell sm:px-8">{{ $employee->phone ?: '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 sm:px-8">
                                    @if ($st === 'pending')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-950 ring-1 ring-amber-200/80">
                                            <span class="size-1.5 rounded-full bg-amber-500"></span>
                                            Pending
                                        </span>
                                    @elseif ($st === 'active')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-900 ring-1 ring-emerald-200/80">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            Active
                                        </span>
                                    @elseif ($st === 'declined' || $st === 'rejected')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-900 ring-1 ring-red-200/80">
                                            <span class="size-1.5 rounded-full bg-red-500"></span>
                                            Declined
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700">{{ $st ?: '—' }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 tabular-nums text-brand-text-secondary sm:px-8">
                                    {{ $employee->created_at?->timezone(config('app.timezone'))->format('M j, Y') }}<span class="hidden lg:inline">{{ $employee->created_at?->timezone(config('app.timezone'))->format(' · g:i A') }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right sm:px-8">
                                    <a
                                        href="{{ route('admin.registrations.show', ['companySlug' => $currentCompany->slug, 'publicId' => $employee->public_id]) }}"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-brand-border bg-white px-3 py-2 text-xs font-bold text-brand-primary shadow-sm transition hover:border-brand-primary/40 hover:bg-brand-surface"
                                    >View <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-brand-border bg-brand-surface/40 px-6 py-4 text-sm text-brand-text-secondary sm:px-8">
                Showing <span class="font-semibold text-brand-text">{{ count($rows) }}</span> record(s) for <span class="font-medium text-brand-label">{{ $currentCompany->name }}</span>
                @if ($statusFilter)
                    · filter: <span class="font-semibold text-brand-text">{{ $statusFilter }}</span>
                @endif
            </div>
        @endif
    </section>
@endsection
