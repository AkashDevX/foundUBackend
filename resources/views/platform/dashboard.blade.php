@extends('layouts.platform')

@section('title', 'Organizations')

@section('heading', 'Tenant organizations')

@section('content')
    @php
        /** @var \Illuminate\Support\Collection<int, array> $organizations */
        /** @var array{total: int, active: int} $stats */
        /** @var \Carbon\CarbonInterface $displayNow */
    @endphp

    <div class="mb-6 overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
        <div class="border-b border-brand-border border-l-4 border-l-brand-primary bg-white px-5 py-4 sm:px-6 sm:py-5">
            <h2 class="text-xl font-semibold text-brand-text sm:text-2xl">Platform overview</h2>
            <p class="mt-1.5 text-sm text-brand-text-secondary">
                <time datetime="{{ $displayNow->toDateString() }}">{{ $displayNow->format('l, F j, Y') }}</time>
            </p>
        </div>

        <div class="flex flex-col divide-y divide-brand-border sm:flex-row sm:divide-x sm:divide-y-0">
            <div class="min-w-0 flex-1 px-5 py-3.5 sm:px-5 sm:py-4">
                <span class="block text-2xl font-medium tabular-nums text-brand-text sm:text-3xl">{{ $stats['total'] }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Total organizations</span>
            </div>
            <div class="min-w-0 flex-1 px-5 py-3.5 sm:px-5 sm:py-4">
                <span class="block text-2xl font-medium tabular-nums text-brand-primary sm:text-3xl">{{ $stats['active'] }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Active</span>
            </div>
            <a
                href="{{ route('platform.organization-requests.index') }}"
                class="min-w-0 flex-1 px-5 py-3.5 text-left transition hover:bg-brand-surface/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-primary sm:px-5 sm:py-4"
            >
                <span class="block text-2xl font-medium tabular-nums text-brand-primary-light sm:text-3xl">{{ $stats['pending_org_requests'] }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Pending access requests</span>
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-border text-left text-sm">
                <thead class="bg-brand-surface text-xs font-semibold uppercase tracking-wider text-brand-text-secondary">
                    <tr>
                        <th scope="col" class="px-5 py-3.5 sm:px-6">Organization</th>
                        <th scope="col" class="px-5 py-3.5 sm:px-6">Status</th>
                        <th scope="col" class="px-5 py-3.5 sm:px-6">Workforce</th>
                        <th scope="col" class="px-5 py-3.5 sm:px-6"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border text-brand-text">
                    @forelse ($organizations as $row)
                        @php
                            /** @var \App\Models\Company $company */
                            $company = $row['company'];
                        @endphp
                        <tr class="transition hover:bg-brand-surface/50">
                            <td class="px-5 py-4 sm:px-6">
                                <p class="font-semibold text-brand-text">{{ $company->name }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                @if ($company->is_active)
                                    <span class="inline-flex items-center rounded-full bg-brand-primary/10 px-2.5 py-1 text-xs font-semibold text-brand-primary ring-1 ring-brand-primary/20">Active</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-brand-surface px-2.5 py-1 text-xs font-semibold text-brand-text-secondary ring-1 ring-brand-border">Inactive</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                @if ($row['operational'])
                                    <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                        <span><span class="text-brand-text-secondary">Total</span> <span class="font-semibold tabular-nums text-brand-text">{{ $row['stats_total'] }}</span></span>
                                        <span><span class="text-brand-text-secondary">Pending</span> <span class="font-semibold tabular-nums text-brand-primary-light">{{ $row['stats_pending'] }}</span></span>
                                        <span><span class="text-brand-text-secondary">Active</span> <span class="font-semibold tabular-nums text-brand-primary">{{ $row['stats_active'] }}</span></span>
                                    </div>
                                @else
                                    <span class="text-brand-text-secondary">Unavailable</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <a
                                    href="{{ route('platform.organizations.show', $company->slug) }}"
                                    class="inline-flex items-center gap-1 rounded-lg bg-brand-primary-light px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary-light/40"
                                >
                                    View
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center text-brand-text-secondary sm:px-6">
                                No tenant organizations are registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
