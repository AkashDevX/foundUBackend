@extends('layouts.platform')

@section('title', 'Organisation access requests')

@section('heading', 'Organisation access requests')

@section('subheading')
    New organisation enquiries from the mobile app — CruLynk platform only.
@endsection

@section('content')
    @php
        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\OrganizationRequest> $requests */
        /** @var int $pendingCount */
        /** @var \Carbon\CarbonInterface $displayNow */
    @endphp

    <div class="mb-6 overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
        <div class="border-b border-brand-border border-l-4 border-l-brand-primary bg-white px-5 py-4 sm:px-6 sm:py-5">
            <h2 class="text-xl font-semibold text-brand-text sm:text-2xl">Incoming requests</h2>
            <p class="mt-1.5 text-sm text-brand-text-secondary">
                <time datetime="{{ $displayNow->toDateString() }}">{{ $displayNow->format('l, F j, Y') }}</time>
            </p>
        </div>

        <div class="flex flex-col divide-y divide-brand-border sm:flex-row sm:divide-x sm:divide-y-0">
            <div class="min-w-0 flex-1 px-5 py-3.5 sm:px-5 sm:py-4">
                <span class="block text-2xl font-medium tabular-nums text-brand-text sm:text-3xl">{{ $requests->total() }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Total requests</span>
            </div>
            <div class="min-w-0 flex-1 px-5 py-3.5 sm:px-5 sm:py-4">
                <span class="block text-2xl font-medium tabular-nums text-brand-primary sm:text-3xl">{{ $pendingCount }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Pending review</span>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-border text-left text-sm">
                <thead class="bg-brand-surface text-xs font-semibold uppercase tracking-wider text-brand-text-secondary">
                    <tr>
                        <th scope="col" class="px-5 py-3.5 sm:px-6">Company</th>
                        <th scope="col" class="px-5 py-3.5 sm:px-6">Industry</th>
                        <th scope="col" class="px-5 py-3.5 sm:px-6">Contact</th>
                        <th scope="col" class="px-5 py-3.5 sm:px-6">Submitted</th>
                        <th scope="col" class="px-5 py-3.5 sm:px-6">Status</th>
                        <th scope="col" class="px-5 py-3.5 sm:px-6"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border text-brand-text">
                    @forelse ($requests as $request)
                        <tr class="transition hover:bg-brand-surface/50">
                            <td class="px-5 py-4 sm:px-6">
                                <p class="font-semibold text-brand-text">{{ $request->company_name }}</p>
                                <p class="mt-0.5 text-xs text-brand-text-secondary">{{ $request->postcode }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <p class="text-brand-text">{{ $request->industryLabel() }}</p>
                                <p class="mt-0.5 text-xs text-brand-text-secondary">{{ $request->employeeBandLabel() }} employees</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <p class="font-medium text-brand-text">{{ $request->contact_full_name }}</p>
                                <p class="mt-0.5 text-xs text-brand-text-secondary">{{ $request->contact_email }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6 text-brand-text-secondary">
                                <time datetime="{{ $request->created_at?->toIso8601String() }}">
                                    {{ \App\Support\DisplayTimezone::formatDateTime($request->created_at) }}
                                </time>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                @if ($request->status === \App\Models\OrganizationRequest::STATUS_PENDING)
                                    <span class="inline-flex items-center rounded-full bg-brand-primary/10 px-2.5 py-1 text-xs font-semibold text-brand-primary ring-1 ring-brand-primary/20">Pending</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-brand-surface px-2.5 py-1 text-xs font-semibold text-brand-text-secondary ring-1 ring-brand-border">{{ ucfirst($request->status) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <a
                                    href="{{ route('platform.organization-requests.show', $request) }}"
                                    class="inline-flex items-center gap-1 rounded-lg bg-brand-primary-light px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary-light/40"
                                >
                                    View
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-brand-text-secondary sm:px-6">
                                No organisation access requests yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($requests->hasPages())
            <div class="border-t border-brand-border px-5 py-4 sm:px-6">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
@endsection
