@extends('layouts.platform')

@section('title', $summary['company']->name)

@section('heading', $summary['company']->name)

@section('content')
    @php
        /** @var array $summary */
        /** @var \App\Models\Company $company */
        $company = $summary['company'];
        /** @var \Carbon\CarbonInterface $displayNow */
    @endphp

    <div class="mb-6">
        <a href="{{ route('platform.dashboard') }}" class="inline-flex items-center gap-2 text-sm font-medium text-brand-link transition hover:text-brand-primary">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back to all organizations
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
            <div class="border-b border-brand-border border-l-4 border-l-brand-primary px-5 py-4 sm:px-6">
                <h2 class="text-lg font-semibold text-brand-text">Organization</h2>
            </div>
            <dl class="space-y-4 px-5 py-5 text-sm sm:px-6">
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="text-brand-text-secondary">Name</dt>
                    <dd class="font-medium text-brand-text">{{ $company->name }}</dd>
                </div>
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="text-brand-text-secondary">Status</dt>
                    <dd>
                        @if ($company->is_active)
                            <span class="inline-flex items-center rounded-full bg-brand-primary/10 px-2.5 py-1 text-xs font-semibold text-brand-primary ring-1 ring-brand-primary/20">Active</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-brand-surface px-2.5 py-1 text-xs font-semibold text-brand-text-secondary ring-1 ring-brand-border">Inactive</span>
                        @endif
                    </dd>
                </div>
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="text-brand-text-secondary">Registered</dt>
                    <dd class="text-brand-text">
                        <time datetime="{{ $company->created_at?->toDateString() }}">{{ $company->created_at?->format('M j, Y') ?? '—' }}</time>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
            <div class="border-b border-brand-border border-l-4 border-l-brand-primary px-5 py-4 sm:px-6">
                <h2 class="text-lg font-semibold text-brand-text">Workforce summary</h2>
            </div>
            <div class="px-5 py-5 sm:px-6">
                @if ($summary['operational'])
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-brand-border bg-brand-surface/50 px-4 py-4">
                            <p class="text-2xl font-semibold tabular-nums text-brand-text">{{ $summary['stats_total'] }}</p>
                            <p class="mt-1 text-xs text-brand-text-secondary">Total</p>
                        </div>
                        <div class="rounded-lg border border-brand-border bg-brand-surface/50 px-4 py-4">
                            <p class="text-2xl font-semibold tabular-nums text-brand-primary-light">{{ $summary['stats_pending'] }}</p>
                            <p class="mt-1 text-xs text-brand-text-secondary">Pending</p>
                        </div>
                        <div class="rounded-lg border border-brand-border bg-brand-surface/50 px-4 py-4">
                            <p class="text-2xl font-semibold tabular-nums text-brand-primary">{{ $summary['stats_active'] }}</p>
                            <p class="mt-1 text-xs text-brand-text-secondary">Active</p>
                        </div>
                        <div class="rounded-lg border border-brand-border bg-brand-surface/50 px-4 py-4">
                            <p class="text-2xl font-semibold tabular-nums text-brand-text-secondary">{{ $summary['stats_declined'] }}</p>
                            <p class="mt-1 text-xs text-brand-text-secondary">Declined</p>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-brand-text-secondary">Workforce figures are not available for this organization right now.</p>
                @endif

                <p class="mt-6 text-xs text-brand-text-secondary/80">
                    As of <time datetime="{{ $displayNow->toDateString() }}">{{ $displayNow->format('M j, Y') }}</time>
                </p>
            </div>
        </section>
    </div>
@endsection
