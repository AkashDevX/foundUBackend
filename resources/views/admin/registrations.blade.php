@extends('layouts.admin')

@section('title', 'Registration requests')

@section('heading', 'Registration requests')

@section('subheading')
    {{ $currentCompany->name }}
@endsection

@section('content')
    @php
        /** @var \App\Models\Company $currentCompany */
        /** @var array<int, array{employee: \App\Models\Employee}> $rows */
        /** @var string|null $tenantError */
        use App\Support\DisplayTimezone;
        /** @var string|null $statusFilter */
        /** @var int $statsTotal */
        /** @var int $statsPending */
        /** @var int $statsActive */
        /** @var int $statsDeclined */
    @endphp

    @if ($tenantError !== null)
        <div data-flash-warning="{{ e('Could not reach this organization\'s database. '.$tenantError) }}" hidden></div>
    @endif

    {{-- Organization + counts: minimal, one accent, no decoration --}}
    <div
        class="mb-6 overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm"
    >
        <div
            class="border-b border-b-brand-border border-l-4 border-l-brand-primary bg-white px-5 py-4 sm:px-6 sm:py-5"
        >
            <h2 class="text-xl font-semibold text-brand-text sm:text-2xl">
                {{ $currentCompany->name }}
            </h2>
            <p class="mt-1.5 text-sm text-brand-text-secondary">
                <time datetime="{{ DisplayTimezone::now()->toDateString() }}">{{ DisplayTimezone::now()->format('l, F j, Y') }}</time>
                <span class="px-1.5 text-brand-border" aria-hidden="true">·</span>
                <span class="font-mono text-xs text-brand-text-secondary/90">{{ $currentCompany->slug }}</span>
            </p>
        </div>

        <div
            class="flex flex-col divide-y divide-brand-border sm:flex-row sm:divide-x sm:divide-y-0"
            role="list"
            aria-label="Registration counts. Select to filter the table below."
        >
            <a
                href="{{ route('admin.registrations.index') }}"
                role="listitem"
                class="min-w-0 flex-1 px-5 py-3.5 text-left transition hover:bg-brand-surface/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-primary sm:px-5 sm:py-4"
            >
                <span class="block text-2xl font-medium tabular-nums text-brand-text sm:text-3xl">{{ $statsTotal }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Total</span>
            </a>
            <a
                href="{{ route('admin.registrations.index', ['status' => 'pending']) }}"
                role="listitem"
                class="min-w-0 flex-1 px-5 py-3.5 text-left transition hover:bg-brand-surface/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-primary sm:px-5 sm:py-4"
            >
                <span class="block text-2xl font-medium tabular-nums text-brand-text sm:text-3xl">{{ $statsPending }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Pending</span>
            </a>
            <a
                href="{{ route('admin.registrations.index', ['status' => 'active']) }}"
                role="listitem"
                class="min-w-0 flex-1 px-5 py-3.5 text-left transition hover:bg-brand-surface/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-primary sm:px-5 sm:py-4"
            >
                <span class="block text-2xl font-medium tabular-nums text-brand-text sm:text-3xl">{{ $statsActive }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Active</span>
            </a>
            <a
                href="{{ route('admin.registrations.index', ['status' => 'declined']) }}"
                role="listitem"
                class="min-w-0 flex-1 px-5 py-3.5 text-left transition hover:bg-brand-surface/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-primary sm:px-5 sm:py-4"
            >
                <span class="block text-2xl font-medium tabular-nums text-brand-text sm:text-3xl">{{ $statsDeclined }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Declined</span>
            </a>
        </div>

        <div
            class="border-t border-brand-border bg-white px-5 py-2.5 sm:px-6"
        >
            <nav
                class="flex flex-wrap items-baseline gap-x-1 gap-y-1.5 text-sm"
                aria-label="Filter by status"
            >
                <span class="pr-1 text-brand-text-secondary">Status</span>
                <a
                    href="{{ route('admin.registrations.index') }}"
                    class="rounded px-1.5 py-0.5 {{ $statusFilter === null || $statusFilter === '' ? 'font-semibold text-brand-primary' : 'text-brand-text-secondary hover:text-brand-text' }}"
                >All</a>
                <span class="text-brand-text-secondary/50" aria-hidden="true">/</span>
                <a
                    href="{{ route('admin.registrations.index', ['status' => 'pending']) }}"
                    class="rounded px-1.5 py-0.5 {{ $statusFilter === 'pending' ? 'font-semibold text-brand-primary' : 'text-brand-text-secondary hover:text-brand-text' }}"
                >Pending</a>
                <span class="text-brand-text-secondary/50" aria-hidden="true">/</span>
                <a
                    href="{{ route('admin.registrations.index', ['status' => 'active']) }}"
                    class="rounded px-1.5 py-0.5 {{ $statusFilter === 'active' ? 'font-semibold text-brand-primary' : 'text-brand-text-secondary hover:text-brand-text' }}"
                >Active</a>
                <span class="text-brand-text-secondary/50" aria-hidden="true">/</span>
                <a
                    href="{{ route('admin.registrations.index', ['status' => 'declined']) }}"
                    class="rounded px-1.5 py-0.5 {{ $statusFilter === 'declined' ? 'font-semibold text-brand-primary' : 'text-brand-text-secondary hover:text-brand-text' }}"
                >Declined</a>
            </nav>
        </div>
    </div>

    {{-- Table card --}}
    <section class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
        <div
            class="flex flex-col gap-1 border-b border-brand-border bg-brand-surface/50 px-6 py-5 sm:flex-row sm:items-end sm:justify-between sm:px-7"
        >
            <div>
                <h3 class="text-lg font-bold text-brand-text">Submitted applications</h3>
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
                                    {{ DisplayTimezone::formatDate($employee->created_at) }}<span class="hidden lg:inline">{{ DisplayTimezone::format($employee->created_at, ' · g:i A') }}</span>
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
